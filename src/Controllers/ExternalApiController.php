<?php

namespace App\Controllers;

use App\Repositories\ProjectRepository;
use App\Repositories\ServiceRepository;
use App\Repositories\ActivityLogRepository;
use Exception;

class ExternalApiController extends BaseController
{
    private ProjectRepository $projectRepo;
    private ServiceRepository $serviceRepo;
    private ActivityLogRepository $activityLog;
    private \App\Services\UserService $userService;

    public function __construct(
        ProjectRepository $projectRepo,
        ServiceRepository $serviceRepo,
        ActivityLogRepository $activityLog,
        \App\Services\UserService $userService
    ) {
        $this->projectRepo = $projectRepo;
        $this->serviceRepo = $serviceRepo;
        $this->activityLog = $activityLog;
        $this->userService = $userService;
    }

    /**
     * POST /api/v1/register-user
     * Registers a user and increments the active count.
     */
    public function registerUser(): void
    {
        try {
            $data = $this->getRequestData();
            $apiKey = $data['api_key'] ?? '';
            $domain = $data['domain'] ?? '';
            $username = $data['username'] ?? 'ext_user_' . time();
            $name = $data['name'] ?? null;
            $email = $data['email'] ?? null;

            if (empty($apiKey) || empty($domain)) {
                $this->errorResponse("API Key and Domain are required.", 401);
            }

            // 1. Auth Project
            $project = $this->findProjectByAuth($apiKey, $domain);
            if (!$project) {
                $this->errorResponse("Unauthorized: Invalid API Key or Domain.", 401);
            }

            // 2. Find available service matching the requested type
            $requestedType = strtolower($data['service_type'] ?? 'web');
            $services = $this->serviceRepo->findByProjectId($project['id']);
            $targetService = null;
            foreach ($services as $s) {
                // Check if service matches type (case-insensitive), is not expired, and has capacity
                if (strtolower($s->serviceType) === $requestedType && strtotime($s->endDate) >= time() && $s->activeUserCount < $s->userLimit) {
                    $targetService = $s;
                    break;
                }
            }

            if (!$targetService) {
                $this->errorResponse("No available subscription capacity.", 403);
            }

            // 3. Register User (This increments count automatically)
            $user = $this->userService->registerUser([
                'service_id' => $targetService->id,
                'user_identifier' => $username,
                'name' => $name,
                'email' => $email
            ]);

            $this->activityLog->log('external_api', $project['id'], 'user_registered', 
                "User \"{$username}\" registered via API for Project \"{$project['name']}\"",
                null, ['username' => $username, 'service_id' => $targetService->id]
            );

            $this->jsonResponse([
                'status' => 'success',
                'message' => "User registered successfully.",
                'data' => [
                    'id' => $user->id,
                    'username' => $user->userIdentifier,
                    'service_id' => $targetService->id,
                    'new_count' => $targetService->activeUserCount + 1
                ]
            ]);

        } catch (\Throwable $e) {
            $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * POST /api/v1/deactivate-user
     * Deactivates a user by username/email and decrements the active count.
     */
    public function deactivateUser(): void
    {
        try {
            $data = $this->getRequestData();
            $apiKey = $data['api_key'] ?? '';
            $domain = $data['domain'] ?? '';
            $username = $data['username'] ?? '';

            if (empty($apiKey) || empty($domain) || empty($username)) {
                $this->errorResponse("API Key, Domain and Username are required.", 400);
            }

            // 1. Auth Project
            $project = $this->findProjectByAuth($apiKey, $domain);
            if (!$project) {
                $this->errorResponse("Unauthorized: Invalid API Key or Domain.", 401);
            }

            // 2. Find the user in the subscription database for this project's services
            $db = \App\Database::getInstance();
            $stmt = $db->prepare("
                SELECT u.* FROM users u 
                JOIN services s ON u.service_id = s.id 
                WHERE s.project_id = :project_id 
                AND u.user_identifier = :username 
                AND u.status = 'active'
                LIMIT 1
            ");
            $stmt->execute(['project_id' => $project['id'], 'username' => $username]);
            $userRow = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$userRow) {
                $this->errorResponse("Active user \"{$username}\" not found for this project.", 404);
            }

            // 3. Deactivate User (This decrements count automatically)
            $success = $this->userService->deactivateUser((int)$userRow['id']);

            if ($success) {
                $this->activityLog->log('external_api', $project['id'], 'user_deactivated', 
                    "User \"{$username}\" deactivated via API for Project \"{$project['name']}\"",
                    null, ['username' => $username]
                );

                $this->jsonResponse([
                    'status' => 'success',
                    'message' => "User deactivated successfully."
                ]);
            } else {
                $this->errorResponse("Failed to deactivate user.");
            }

        } catch (Exception $e) {
            $this->errorResponse($e->getMessage());
        }
    }

    /**
     * POST /api/v1/validate-subscription
     * Validates if a project can register a new user based on subscription limits.
     */
    public function validateSubscription(): void
    {
        try {
            $data = $this->getRequestData();
            $apiKey = $data['api_key'] ?? '';
            $domain = $data['domain'] ?? '';

            if (empty($apiKey) || empty($domain)) {
                $this->errorResponse("API Key and Domain are required.", 401);
            }

            // 1. Find Project by API Key and Domain
            // We'll need a new method in ProjectRepository or do it here via PDO
            $project = $this->findProjectByAuth($apiKey, $domain);

            if (!$project) {
                $this->activityLog->log('external_api', null, 'auth_failed', 
                    "Validation failed: Invalid API Key or Domain match ({$domain})", 
                    null, ['provided_domain' => $domain]
                );
                $this->errorResponse("Unauthorized: Invalid API Key or Domain.", 401);
            }

            // 2. Get Services for this project
            $requestedType = strtolower($data['service_type'] ?? 'web');
            $services = $this->serviceRepo->findByProjectId($project['id']);
            
            if (empty($services)) {
                $this->errorResponse("No active subscription found for this project.", 404);
            }

            // Check if any service matching the type has room
            $canCreate = false;
            $details = [];

            foreach ($services as $service) {
                if (strtolower($service->serviceType) !== $requestedType) continue;

                $isExpired = strtotime($service->endDate) < time();
                $limitReached = $service->activeUserCount >= $service->userLimit;

                if (!$isExpired && !$limitReached) {
                    $canCreate = true;
                    $details = [
                        'service_id' => $service->id,
                        'type' => $service->serviceType,
                        'limit' => $service->userLimit,
                        'current' => $service->activeUserCount,
                        'expiry' => $service->endDate
                    ];
                    break;
                }
            }

            if ($canCreate) {
                $this->jsonResponse([
                    'status' => 'success',
                    'allowed' => true,
                    'message' => "User creation allowed.",
                    'data' => $details
                ]);
            } else {
                $this->activityLog->log('external_api', $project['id'], 'limit_reached', 
                    "Project \"{$project['name']}\" reached user limit.", 
                    null, $details
                );
                $this->jsonResponse([
                    'status' => 'error',
                    'allowed' => false,
                    'message' => "Subscription limit reached or expired.",
                    'data' => $details
                ], 403);
            }

        } catch (Exception $e) {
            $this->errorResponse($e->getMessage());
        }
    }

    private function findProjectByAuth(string $key, string $domain): ?array
    {
        $domain = rtrim($domain, '/');
        // Simple PDO check since it's a specific auth case
        $db = \App\Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM projects WHERE api_key = :key AND (TRIM(TRAILING '/' FROM domain) = :domain OR domain IS NULL)");
        $stmt->execute(['key' => $key, 'domain' => $domain]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}

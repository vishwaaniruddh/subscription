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
            $email = $data['email'] ?? null;

            if (empty($apiKey) || empty($domain)) {
                $this->errorResponse("API Key and Domain are required.", 401);
            }

            // 1. Auth Project
            $project = $this->findProjectByAuth($apiKey, $domain);
            if (!$project) {
                $this->errorResponse("Unauthorized: Invalid API Key or Domain.", 401);
            }

            // 2. Find first available service
            $services = $this->serviceRepo->findByProjectId($project['id']);
            $targetService = null;
            foreach ($services as $s) {
                if (strtotime($s->endDate) >= time() && $s->activeUserCount < $s->userLimit) {
                    $targetService = $s;
                    break;
                }
            }

            if (!$targetService) {
                $this->errorResponse("No available subscription capacity.", 403);
            }

            // 3. Register User (This increments count automatically)
            $user = $this->userService->registerUser($targetService->id, [
                'username' => $username,
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
                    'username' => $user->username,
                    'service_id' => $targetService->id,
                    'new_count' => $targetService->activeUserCount + 1
                ]
            ]);

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
            $services = $this->serviceRepo->findByProjectId($project['id']);
            
            if (empty($services)) {
                $this->errorResponse("No active subscription found for this project.", 404);
            }

            // Check if any service has room
            $canCreate = false;
            $details = [];

            foreach ($services as $service) {
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

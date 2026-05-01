<?php

namespace App\Controllers;

use App\Services\UserService;
use App\Repositories\ActivityLogRepository;
use Exception;

class UserController extends BaseController
{
    private UserService $userService;
    private ActivityLogRepository $activityLog;
    private \App\Services\JwtService $jwtService;

    public function __construct(
        UserService $userService, 
        ActivityLogRepository $activityLog,
        \App\Services\JwtService $jwtService
    ) {
        $this->userService = $userService;
        $this->activityLog = $activityLog;
        $this->jwtService = $jwtService;
    }

    public function register(): void
    {
        $this->checkAuth($this->jwtService);
        try {
            $data = $this->getJsonInput();
            $user = $this->userService->registerUser($data);
            $result = $user->toArray();

            $this->activityLog->log('user', $result['id'], 'user_registered',
                "User \"{$result['user_identifier']}\" registered to Service #{$result['service_id']}",
                null, $result
            );

            $this->jsonResponse($result, 201);
        } catch (Exception $e) {
            $this->activityLog->log('user', null, 'register_failed',
                "User registration failed: {$e->getMessage()}", null, $data ?? null);
            $this->errorResponse($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function listByService(int $serviceId): void
    {
        $users = $this->userService->listActiveUsers($serviceId);
        $usersArray = array_map(fn($user) => $user->toArray(), $users);
        $this->jsonResponse($usersArray);
    }

    public function deactivate(int $userId): void
    {
        $success = $this->userService->deactivateUser($userId);
        if ($success) {
            $this->activityLog->log('user', $userId, 'user_deactivated',
                "User #{$userId} was deactivated");
            $this->jsonResponse(['success' => true]);
        } else {
            $this->errorResponse("User not found or already deactivated", 404);
        }
    }
}

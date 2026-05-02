<?php

namespace App\Services;

use App\Repositories\UserRepository;
use App\Repositories\ServiceRepository;
use App\Repositories\SubscriptionHistoryRepository;
use App\Models\User;
use App\Models\SubscriptionHistory;
use Exception;

class UserService
{
    private UserRepository $userRepository;
    private ServiceRepository $serviceRepository;
    private ValidationService $validationService;
    private SubscriptionHistoryRepository $historyRepository;

    public function __construct(
        UserRepository $userRepository,
        ServiceRepository $serviceRepository,
        ValidationService $validationService,
        ?SubscriptionHistoryRepository $historyRepository = null
    ) {
        $this->userRepository = $userRepository;
        $this->serviceRepository = $serviceRepository;
        $this->validationService = $validationService;
        // Allow null for backward compatibility — construct a default if not provided
        $this->historyRepository = $historyRepository ?? new SubscriptionHistoryRepository(\App\Database::getInstance());
    }

    public function registerUser(array $data): User
    {
        $serviceId = (int)$data['service_id'];
        
        return $this->userRepository->transactional(function() use ($serviceId, $data) {
            // Lock the service row for update
            $service = $this->serviceRepository->findById($serviceId, true);
            
            $validation = $this->validationService->validateUserCreation($serviceId);
            if (!$validation['success']) {
                throw new Exception($validation['message'], 400);
            }

            $oldCount = $service->activeUserCount;

            $user = User::fromArray($data);
            $newUser = $this->userRepository->create($user);
            
            $this->serviceRepository->incrementActiveUserCount($serviceId);

            // Log to subscription_history
            $this->historyRepository->create(new SubscriptionHistory(
                $serviceId,
                'user_registered',
                json_encode(['active_user_count' => $oldCount, 'username' => $data['user_identifier'] ?? 'unknown']),
                json_encode(['active_user_count' => $oldCount + 1, 'user_id' => $newUser->id])
            ));
            
            return $newUser;
        });
    }

    public function deactivateUser(int $userId): bool
    {
        $user = $this->userRepository->findById($userId);
        if (!$user || $user->status === 'deactivated') {
            return false;
        }

        return $this->userRepository->transactional(function() use ($user) {
            $service = $this->serviceRepository->findById($user->serviceId, true);
            $oldCount = $service->activeUserCount;

            $this->userRepository->deactivate($user->id);
            $this->serviceRepository->decrementActiveUserCount($user->serviceId);

            // Log to subscription_history
            $this->historyRepository->create(new SubscriptionHistory(
                $user->serviceId,
                'user_deactivated',
                json_encode(['active_user_count' => $oldCount, 'username' => $user->userIdentifier]),
                json_encode(['active_user_count' => max(0, $oldCount - 1), 'user_id' => $user->id])
            ));

            return true;
        });
    }

    public function listActiveUsers(int $serviceId): array
    {
        return $this->userRepository->findActiveByServiceId($serviceId);
    }
}


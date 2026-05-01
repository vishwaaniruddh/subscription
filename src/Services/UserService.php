<?php

namespace App\Services;

use App\Repositories\UserRepository;
use App\Repositories\ServiceRepository;
use App\Models\User;
use Exception;

class UserService
{
    private UserRepository $userRepository;
    private ServiceRepository $serviceRepository;
    private ValidationService $validationService;

    public function __construct(
        UserRepository $userRepository,
        ServiceRepository $serviceRepository,
        ValidationService $validationService
    ) {
        $this->userRepository = $userRepository;
        $this->serviceRepository = $serviceRepository;
        $this->validationService = $validationService;
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

            $user = User::fromArray($data);
            $newUser = $this->userRepository->create($user);
            
            $this->serviceRepository->incrementActiveUserCount($serviceId);
            
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
            $this->userRepository->deactivate($user->id);
            $this->serviceRepository->decrementActiveUserCount($user->serviceId);
            return true;
        });
    }

    public function listActiveUsers(int $serviceId): array
    {
        return $this->userRepository->findActiveByServiceId($serviceId);
    }
}

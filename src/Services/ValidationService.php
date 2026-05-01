<?php

namespace App\Services;

use App\Repositories\ServiceRepository;
use Exception;

class ValidationService
{
    private ServiceRepository $serviceRepository;
    private Logger $logger;

    public function __construct(ServiceRepository $serviceRepository, ?Logger $logger = null)
    {
        $this->serviceRepository = $serviceRepository;
        $this->logger = $logger ?? new Logger();
    }

    public function validateUserCreation(int $serviceId, ?string $currentDate = null): array
    {
        $service = $this->serviceRepository->findById($serviceId);
        if (!$service) {
            return [
                'success' => false,
                'error_code' => 'SERVICE_NOT_FOUND',
                'message' => 'The specified service does not exist.'
            ];
        }

        if (!$service->isActive($currentDate)) {
            $result = [
                'success' => false,
                'error_code' => 'SUBSCRIPTION_EXPIRED',
                'message' => 'The subscription for this service has expired or is not yet active.',
                'context' => [
                    'start_date' => $service->startDate,
                    'end_date' => $service->endDate
                ]
            ];
            
            // Log validation failure
            $this->logger->logValidationFailure(
                'SUBSCRIPTION_EXPIRED',
                $result['message'],
                [
                    'service_id' => $serviceId,
                    'start_date' => $service->startDate,
                    'end_date' => $service->endDate,
                    'current_date' => $currentDate ?? date('Y-m-d')
                ]
            );
            
            return $result;
        }

        if (!$service->canAddUser()) {
            $result = [
                'success' => false,
                'error_code' => 'USER_LIMIT_EXCEEDED',
                'message' => 'The user limit for this service has been reached.',
                'context' => [
                    'user_limit' => $service->userLimit,
                    'active_user_count' => $service->activeUserCount
                ]
            ];
            
            // Log validation failure
            $this->logger->logValidationFailure(
                'USER_LIMIT_EXCEEDED',
                $result['message'],
                [
                    'service_id' => $serviceId,
                    'user_limit' => $service->userLimit,
                    'active_user_count' => $service->activeUserCount
                ]
            );
            
            return $result;
        }

        return [
            'success' => true,
            'message' => 'User can be created.'
        ];
    }
}

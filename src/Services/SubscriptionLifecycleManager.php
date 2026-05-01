<?php

namespace App\Services;

use App\Repositories\ServiceRepository;
use App\Repositories\SubscriptionHistoryRepository;
use App\Models\SubscriptionHistory;
use Exception;

class SubscriptionLifecycleManager
{
    private ServiceRepository $serviceRepository;
    private SubscriptionHistoryRepository $historyRepository;

    public function __construct(
        ServiceRepository $serviceRepository,
        SubscriptionHistoryRepository $historyRepository
    ) {
        $this->serviceRepository = $serviceRepository;
        $this->historyRepository = $historyRepository;
    }

    public function renewSubscription(int $serviceId, string $newEndDate): bool
    {
        return $this->serviceRepository->transactional(function() use ($serviceId, $newEndDate) {
            $service = $this->serviceRepository->findById($serviceId, true);
            if (!$service) {
                throw new Exception("Service not found.");
            }

            $oldEndDate = $service->endDate;
            $service->endDate = $newEndDate;
            
            $this->serviceRepository->update($service);

            $history = new SubscriptionHistory(
                $serviceId,
                'RENEWAL',
                $oldEndDate,
                $newEndDate
            );
            $this->historyRepository->create($history);

            return true;
        });
    }

    public function extendSubscription(int $serviceId, int $newUserLimit, ?string $newEndDate = null): bool
    {
        return $this->serviceRepository->transactional(function() use ($serviceId, $newUserLimit, $newEndDate) {
            $service = $this->serviceRepository->findById($serviceId, true);
            if (!$service) {
                throw new Exception("Service not found.");
            }

            if ($newUserLimit < $service->activeUserCount) {
                throw new Exception("New user limit cannot be less than current active user count.", 400);
            }

            $oldLimit = $service->userLimit;
            $service->userLimit = $newUserLimit;
            
            $oldEndDate = $service->endDate;
            if ($newEndDate) {
                $service->endDate = $newEndDate;
            }

            $this->serviceRepository->update($service);

            $history = new SubscriptionHistory(
                $serviceId,
                'EXTENSION',
                "Limit: $oldLimit, End: $oldEndDate",
                "Limit: $newUserLimit, End: " . ($newEndDate ?? $oldEndDate)
            );
            $this->historyRepository->create($history);

            return true;
        });
    }
}

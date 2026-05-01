<?php

namespace App\Controllers;

use App\Services\SubscriptionLifecycleManager;
use App\Services\ServiceManager;
use App\Repositories\ActivityLogRepository;
use Exception;

class SubscriptionController extends BaseController
{
    private SubscriptionLifecycleManager $lifecycleManager;
    private ServiceManager $serviceManager;
    private ActivityLogRepository $activityLog;
    private \App\Services\JwtService $jwtService;

    public function __construct(
        SubscriptionLifecycleManager $lifecycleManager,
        ServiceManager $serviceManager,
        ActivityLogRepository $activityLog,
        \App\Services\JwtService $jwtService
    ) {
        $this->lifecycleManager = $lifecycleManager;
        $this->serviceManager = $serviceManager;
        $this->activityLog = $activityLog;
        $this->jwtService = $jwtService;
    }

    public function renew(int $serviceId): void
    {
        $this->checkAuth($this->jwtService);
        try {
            $data = $this->getJsonInput();
            if (!isset($data['new_end_date'])) {
                throw new Exception("New end date is required.");
            }

            $old = $this->serviceManager->getService($serviceId);
            $oldStartDate = $old ? $old->startDate : 'unknown';
            $oldEndDate = $old ? $old->endDate : 'unknown';

            $this->lifecycleManager->renewSubscription($serviceId, $data['new_end_date']);

            $this->activityLog->log('subscription', $serviceId, 'renewed',
                "Service #{$serviceId} renewed: Period [{$oldStartDate} to {$oldEndDate}] → [{$oldStartDate} to {$data['new_end_date']}]",
                ['start_date' => $oldStartDate, 'end_date' => $oldEndDate],
                ['start_date' => $oldStartDate, 'end_date' => $data['new_end_date']]
            );

            $this->jsonResponse(['success' => true]);
        } catch (Exception $e) {
            $this->activityLog->log('subscription', $serviceId, 'renew_failed',
                "Service #{$serviceId} renewal failed: {$e->getMessage()}");
            $this->errorResponse($e->getMessage());
        }
    }

    public function extend(int $serviceId): void
    {
        try {
            $data = $this->getJsonInput();
            if (!isset($data['new_user_limit'])) {
                throw new Exception("New user limit is required.");
            }

            $old = $this->serviceManager->getService($serviceId);
            $oldLimit = $old ? $old->userLimit : 'unknown';
            $oldStartDate = $old ? $old->startDate : 'unknown';
            $oldEndDate = $old ? $old->endDate : null;

            $this->lifecycleManager->extendSubscription(
                $serviceId,
                (int)$data['new_user_limit'],
                $data['new_end_date'] ?? null
            );

            $newEndDate = $data['new_end_date'] ?? $oldEndDate;
            $desc = "Service #{$serviceId} extended: Limit {$oldLimit} → {$data['new_user_limit']}";
            if ($newEndDate !== $oldEndDate) {
                $desc .= ", Period [{$oldStartDate} to {$oldEndDate}] → [{$oldStartDate} to {$newEndDate}]";
            }

            $this->activityLog->log('subscription', $serviceId, 'extended',
                $desc,
                ['user_limit' => $oldLimit, 'start_date' => $oldStartDate, 'end_date' => $oldEndDate],
                ['user_limit' => (int)$data['new_user_limit'], 'start_date' => $oldStartDate, 'end_date' => $newEndDate]
            );

            $this->jsonResponse(['success' => true]);
        } catch (Exception $e) {
            $this->activityLog->log('subscription', $serviceId, 'extend_failed',
                "Service #{$serviceId} extension failed: {$e->getMessage()}");
            $this->errorResponse($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function status(int $serviceId): void
    {
        $service = $this->serviceManager->getService($serviceId);
        if (!$service) {
            $this->errorResponse("Service not found", 404);
        }
        $this->jsonResponse([
            'id' => $service->id,
            'status' => $service->isActive() ? 'active' : 'expired',
            'user_limit' => $service->userLimit,
            'active_user_count' => $service->activeUserCount,
            'start_date' => $service->startDate,
            'end_date' => $service->endDate
        ]);
    }
}

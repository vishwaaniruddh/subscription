<?php

namespace App\Controllers;

use App\Services\ServiceManager;
use App\Repositories\ActivityLogRepository;
use Exception;

class ServiceController extends BaseController
{
    private ServiceManager $serviceManager;
    private ActivityLogRepository $activityLog;
    private \App\Services\JwtService $jwtService;

    public function __construct(
        ServiceManager $serviceManager, 
        ActivityLogRepository $activityLog,
        \App\Services\JwtService $jwtService
    ) {
        $this->serviceManager = $serviceManager;
        $this->activityLog = $activityLog;
        $this->jwtService = $jwtService;
    }

    public function listByProject(int $projectId): void
    {
        $this->checkAuth($this->jwtService);
        $services = $this->serviceManager->listServicesByProject($projectId);
        $servicesArray = array_map(fn($service) => $service->toArray(), $services);
        $this->jsonResponse($servicesArray);
    }

    public function get(int $id): void
    {
        $service = $this->serviceManager->getService($id);
        if (!$service) {
            $this->errorResponse("Service not found", 404);
        }
        $this->jsonResponse($service->toArray());
    }

    public function create(): void
    {
        try {
            $data = $this->getJsonInput();
            $service = $this->serviceManager->createService($data);
            $result = $service->toArray();

            $type = strtoupper($result['service_type'] ?? 'SERVICE');
            $this->activityLog->log('service', $result['id'], 'created',
                "{$type} service created (Project #{$result['project_id']}, Limit: {$result['user_limit']}, {$result['start_date']} → {$result['end_date']})",
                null, $result
            );

            $this->jsonResponse($result, 201);
        } catch (Exception $e) {
            $this->activityLog->log('service', null, 'create_failed',
                "Service creation failed: {$e->getMessage()}", null, $data ?? null);
            $this->errorResponse($e->getMessage());
        }
    }

    public function update(int $id): void
    {
        try {
            $old = $this->serviceManager->getService($id);
            $oldData = $old ? $old->toArray() : null;

            $data = $this->getJsonInput();
            $this->serviceManager->updateService($id, $data);

            $this->activityLog->log('service', $id, 'updated',
                "Service #{$id} was updated",
                $oldData, $data
            );

            $this->jsonResponse(['success' => true]);
        } catch (Exception $e) {
            $this->activityLog->log('service', $id, 'update_failed',
                "Service #{$id} update failed: {$e->getMessage()}");
            $this->errorResponse($e->getMessage());
        }
    }

    public function delete(int $id): void
    {
        $old = $this->serviceManager->getService($id);
        $oldData = $old ? $old->toArray() : null;

        $this->serviceManager->deleteService($id);

        $this->activityLog->log('service', $id, 'deleted',
            "Service #{$id} was deleted",
            $oldData, null
        );

        $this->jsonResponse(['success' => true]);
    }
}

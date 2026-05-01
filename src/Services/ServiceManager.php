<?php

namespace App\Services;

use App\Repositories\ServiceRepository;
use App\Models\Service;
use Exception;

class ServiceManager
{
    private ServiceRepository $repository;

    public function __construct(ServiceRepository $repository)
    {
        $this->repository = $repository;
    }

    public function createService(array $data): Service
    {
        $service = Service::fromArray($data);
        $errors = $service->validate();
        if (!empty($errors)) {
            throw new Exception(json_encode($errors));
        }
        return $this->repository->create($service);
    }

    public function getService(int $id): ?Service
    {
        return $this->repository->findById($id);
    }

    public function listServicesByProject(int $projectId): array
    {
        return $this->repository->findByProjectId($projectId);
    }

    public function updateService(int $id, array $data): bool
    {
        $service = $this->repository->findById($id);
        if (!$service) {
            throw new Exception("Service not found.");
        }
        
        if (isset($data['service_type'])) $service->serviceType = $data['service_type'];
        if (isset($data['user_limit'])) $service->userLimit = (int)$data['user_limit'];
        if (isset($data['start_date'])) $service->startDate = $data['start_date'];
        if (isset($data['end_date'])) $service->endDate = $data['end_date'];
        
        $errors = $service->validate();
        if (!empty($errors)) {
            throw new Exception(json_encode($errors));
        }
        return $this->repository->update($service);
    }

    public function deleteService(int $id): bool
    {
        return $this->repository->delete($id);
    }

    public function getExpiringServices(int $days): array
    {
        return $this->repository->findExpiring($days);
    }

    public function getHighUtilizationServices(float $threshold): array
    {
        return $this->repository->findHighUtilization($threshold);
    }
}

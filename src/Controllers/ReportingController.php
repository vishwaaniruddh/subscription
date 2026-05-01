<?php

namespace App\Controllers;

use App\Services\ServiceManager;
use App\Services\ClientService;
use App\Services\ProjectService;

class ReportingController extends BaseController
{
    private ServiceManager $serviceManager;
    private ClientService $clientService;
    private ProjectService $projectService;
    private \App\Services\JwtService $jwtService;

    public function __construct(
        ServiceManager $serviceManager,
        ClientService $clientService,
        ProjectService $projectService,
        \App\Services\JwtService $jwtService
    ) {
        $this->serviceManager = $serviceManager;
        $this->clientService = $clientService;
        $this->projectService = $projectService;
        $this->jwtService = $jwtService;
    }

    public function clientUtilization(int $clientId): void
    {
        $this->checkAuth($this->jwtService);
        $projects = $this->projectService->listProjectsByClient($clientId);
        $report = [];
        foreach ($projects as $project) {
            $services = $this->serviceManager->listServicesByProject($project->id);
            foreach ($services as $service) {
                $report[] = $service->toArray();
            }
        }
        $this->jsonResponse($report);
    }

    public function projectUtilization(int $projectId): void
    {
        $services = $this->serviceManager->listServicesByProject($projectId);
        $report = array_map(fn($s) => $s->toArray(), $services);
        $this->jsonResponse($report);
    }

    public function serviceUtilization(int $serviceId): void
    {
        $service = $this->serviceManager->getService($serviceId);
        if (!$service) {
            $this->errorResponse("Service not found", 404);
        }
        $this->jsonResponse($service->toArray());
    }

    public function expiringServices(): void
    {
        $days = (int)($_GET['days'] ?? 30);
        $services = $this->serviceManager->getExpiringServices($days);
        $this->jsonResponse(array_map(fn($s) => $s->toArray(), $services));
    }

    public function highUtilization(): void
    {
        $threshold = (float)($_GET['threshold'] ?? 90.0);
        $services = $this->serviceManager->getHighUtilizationServices($threshold);
        $this->jsonResponse(array_map(fn($s) => $s->toArray(), $services));
    }
}

<?php

namespace App\Controllers;

use App\Repositories\ActivityLogRepository;

class ActivityLogController extends BaseController
{
    private ActivityLogRepository $activityLog;
    private \App\Services\JwtService $jwtService;

    public function __construct(ActivityLogRepository $activityLog, \App\Services\JwtService $jwtService)
    {
        $this->activityLog = $activityLog;
        $this->jwtService = $jwtService;
    }

    public function list(): void
    {
        $this->checkAuth($this->jwtService);
        $limit  = (int)($_GET['limit'] ?? 50);
        $offset = (int)($_GET['offset'] ?? 0);
        $logs   = $this->activityLog->listRecent($limit, $offset);
        $total  = $this->activityLog->count();

        $this->jsonResponse([
            'data'  => $logs,
            'total' => $total,
            'limit' => $limit,
            'offset'=> $offset
        ]);
    }

    public function listByEntity(string $entityType, int $entityId): void
    {
        $logs = $this->activityLog->listByEntity($entityType, $entityId);
        $this->jsonResponse($logs);
    }
}

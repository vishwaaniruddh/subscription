<?php

namespace App\Controllers;

use App\Services\ProjectService;
use App\Repositories\ActivityLogRepository;
use Exception;

class ProjectController extends BaseController
{
    private ProjectService $projectService;
    private ActivityLogRepository $activityLog;
    private \App\Services\JwtService $jwtService;

    public function __construct(
        ProjectService $projectService, 
        ActivityLogRepository $activityLog,
        \App\Services\JwtService $jwtService
    ) {
        $this->projectService = $projectService;
        $this->activityLog = $activityLog;
        $this->jwtService = $jwtService;
    }

    public function listByClient(int $clientId): void
    {
        $this->checkAuth($this->jwtService);
        $projects = $this->projectService->listProjectsByClient($clientId);
        $projectsArray = array_map(fn($project) => $project->toArray(), $projects);
        $this->jsonResponse($projectsArray);
    }

    public function get(int $id): void
    {
        $project = $this->projectService->getProject($id);
        if (!$project) {
            $this->errorResponse("Project not found", 404);
        }
        $this->jsonResponse($project->toArray());
    }

    public function create(): void
    {
        try {
            $data = $this->getJsonInput();
            $project = $this->projectService->createProject($data);
            $result = $project->toArray();

            $this->activityLog->log('project', $result['id'], 'created',
                "Project \"{$result['name']}\" was created (Client #{$result['client_id']})",
                null, $result
            );

            $this->jsonResponse($result, 201);
        } catch (Exception $e) {
            $this->activityLog->log('project', null, 'create_failed',
                "Project creation failed: {$e->getMessage()}", null, $data ?? null);
            $this->errorResponse($e->getMessage());
        }
    }

    public function update(int $id): void
    {
        try {
            $old = $this->projectService->getProject($id);
            $oldData = $old ? $old->toArray() : null;

            $data = $this->getJsonInput();
            $this->projectService->updateProject($id, $data);

            $this->activityLog->log('project', $id, 'updated',
                "Project #{$id} was updated",
                $oldData, $data
            );

            $this->jsonResponse(['success' => true]);
        } catch (Exception $e) {
            $this->activityLog->log('project', $id, 'update_failed',
                "Project #{$id} update failed: {$e->getMessage()}");
            $this->errorResponse($e->getMessage());
        }
    }

    public function delete(int $id): void
    {
        $old = $this->projectService->getProject($id);
        $oldData = $old ? $old->toArray() : null;
        $name = $oldData['name'] ?? "#{$id}";

        $this->projectService->deleteProject($id);

        $this->activityLog->log('project', $id, 'deleted',
            "Project \"{$name}\" was deleted",
            $oldData, null
        );

        $this->jsonResponse(['success' => true]);
    }
}

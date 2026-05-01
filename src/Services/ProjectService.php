<?php

namespace App\Services;

use App\Repositories\ProjectRepository;
use App\Models\Project;
use Exception;

class ProjectService
{
    private ProjectRepository $repository;

    public function __construct(ProjectRepository $repository)
    {
        $this->repository = $repository;
    }

    public function createProject(array $data): Project
    {
        $project = Project::fromArray($data);
        $errors = $project->validate();
        if (!empty($errors)) {
            throw new Exception(json_encode($errors));
        }
        return $this->repository->create($project);
    }

    public function getProject(int $id): ?Project
    {
        return $this->repository->findById($id);
    }

    public function listProjectsByClient(int $clientId): array
    {
        return $this->repository->findByClientId($clientId);
    }

    public function updateProject(int $id, array $data): bool
    {
        $project = $this->repository->findById($id);
        if (!$project) {
            throw new Exception("Project not found.");
        }
        if (isset($data['name'])) $project->name = $data['name'];
        if (isset($data['description'])) $project->description = $data['description'];
        if (isset($data['api_key'])) $project->apiKey = $data['api_key'];
        if (isset($data['domain'])) $project->domain = $data['domain'];
        
        $errors = $project->validate();
        if (!empty($errors)) {
            throw new Exception(json_encode($errors));
        }
        return $this->repository->update($project);
    }

    public function deleteProject(int $id): bool
    {
        return $this->repository->delete($id);
    }
}

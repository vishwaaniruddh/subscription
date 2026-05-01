<?php

namespace App\Repositories;

use App\Models\Project;
use PDO;

class ProjectRepository extends BaseRepository
{
    public function create(Project $project): Project
    {
        $stmt = $this->db->prepare("INSERT INTO projects (client_id, name, description, api_key, domain) VALUES (:client_id, :name, :description, :api_key, :domain)");
        $stmt->execute([
            'client_id' => $project->clientId,
            'name' => $project->name,
            'description' => $project->description,
            'api_key' => $project->apiKey,
            'domain' => $project->domain
        ]);
        $project->id = (int)$this->db->lastInsertId();
        return $project;
    }

    public function findById(int $id): ?Project
    {
        $stmt = $this->db->prepare("SELECT * FROM projects WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $data ? Project::fromArray($data) : null;
    }

    public function findByClientId(int $clientId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM projects WHERE client_id = :client_id ORDER BY name ASC");
        $stmt->execute(['client_id' => $clientId]);
        $results = [];
        while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[] = Project::fromArray($data);
        }
        return $results;
    }

    public function update(Project $project): bool
    {
        $stmt = $this->db->prepare("UPDATE projects SET name = :name, description = :description, api_key = :api_key, domain = :domain WHERE id = :id");
        return $stmt->execute([
            'name' => $project->name,
            'description' => $project->description,
            'api_key' => $project->apiKey,
            'domain' => $project->domain,
            'id' => $project->id
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM projects WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }
}

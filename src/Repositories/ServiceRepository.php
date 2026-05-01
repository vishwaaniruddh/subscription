<?php

namespace App\Repositories;

use App\Models\Service;
use PDO;

class ServiceRepository extends BaseRepository
{
    public function create(Service $service): Service
    {
        $stmt = $this->db->prepare("
            INSERT INTO services (project_id, service_type, user_limit, active_user_count, start_date, end_date) 
            VALUES (:project_id, :service_type, :user_limit, :active_user_count, :start_date, :end_date)
        ");
        $stmt->execute([
            'project_id' => $service->projectId,
            'service_type' => $service->serviceType,
            'user_limit' => $service->userLimit,
            'active_user_count' => $service->activeUserCount,
            'start_date' => $service->startDate,
            'end_date' => $service->endDate
        ]);
        $service->id = (int)$this->db->lastInsertId();
        return $service;
    }

    public function findById(int $id, bool $lock = false): ?Service
    {
        $sql = "SELECT * FROM services WHERE id = :id";
        if ($lock) {
            $sql .= " FOR UPDATE";
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $data ? Service::fromArray($data) : null;
    }

    public function findByProjectId(int $projectId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM services WHERE project_id = :project_id");
        $stmt->execute(['project_id' => $projectId]);
        $results = [];
        while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[] = Service::fromArray($data);
        }
        return $results;
    }

    public function update(Service $service): bool
    {
        $stmt = $this->db->prepare("
            UPDATE services 
            SET service_type = :service_type, 
                user_limit = :user_limit, 
                active_user_count = :active_user_count, 
                start_date = :start_date, 
                end_date = :end_date 
            WHERE id = :id
        ");
        return $stmt->execute([
            'service_type' => $service->serviceType,
            'user_limit' => $service->userLimit,
            'active_user_count' => $service->activeUserCount,
            'start_date' => $service->startDate,
            'end_date' => $service->endDate,
            'id' => $service->id
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM services WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    public function incrementActiveUserCount(int $id): bool
    {
        $stmt = $this->db->prepare("UPDATE services SET active_user_count = active_user_count + 1 WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    public function decrementActiveUserCount(int $id): bool
    {
        $stmt = $this->db->prepare("UPDATE services SET active_user_count = active_user_count - 1 WHERE id = :id AND active_user_count > 0");
        return $stmt->execute(['id' => $id]);
    }

    public function findExpiring(int $days): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM services 
            WHERE end_date >= CURDATE() AND end_date <= DATE_ADD(CURDATE(), INTERVAL :days DAY)
        ");
        $stmt->execute(['days' => $days]);
        $results = [];
        while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[] = Service::fromArray($data);
        }
        return $results;
    }

    public function findHighUtilization(float $threshold): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM services 
            WHERE (CAST(active_user_count AS DECIMAL(10,2)) / CAST(user_limit AS DECIMAL(10,2))) * 100 >= :threshold
        ");
        $stmt->execute(['threshold' => $threshold]);
        $results = [];
        while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[] = Service::fromArray($data);
        }
        return $results;
    }
}

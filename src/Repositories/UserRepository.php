<?php

namespace App\Repositories;

use App\Models\User;
use PDO;

class UserRepository extends BaseRepository
{
    public function create(User $user): User
    {
        $stmt = $this->db->prepare("INSERT INTO users (service_id, user_identifier, status) VALUES (:service_id, :user_identifier, :status)");
        $stmt->execute([
            'service_id' => $user->serviceId,
            'user_identifier' => $user->userIdentifier,
            'status' => $user->status
        ]);
        $user->id = (int)$this->db->lastInsertId();
        return $user;
    }

    public function findById(int $id): ?User
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $data ? User::fromArray($data) : null;
    }

    public function findByServiceId(int $serviceId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE service_id = :service_id");
        $stmt->execute(['service_id' => $serviceId]);
        $results = [];
        while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[] = User::fromArray($data);
        }
        return $results;
    }

    public function findActiveByServiceId(int $serviceId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE service_id = :service_id AND status = 'active'");
        $stmt->execute(['service_id' => $serviceId]);
        $results = [];
        while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[] = User::fromArray($data);
        }
        return $results;
    }

    public function deactivate(int $id): bool
    {
        $stmt = $this->db->prepare("UPDATE users SET status = 'deactivated', deactivated_at = CURRENT_TIMESTAMP WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    public function countActiveByServiceId(int $serviceId): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM users WHERE service_id = :service_id AND status = 'active'");
        $stmt->execute(['service_id' => $serviceId]);
        return (int)$stmt->fetchColumn();
    }
}

<?php

namespace App\Repositories;

use PDO;

class ActivityLogRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Record an activity log entry
     */
    public function log(
        string $entityType,
        ?int $entityId,
        string $action,
        string $description,
        ?array $oldData = null,
        ?array $newData = null
    ): int {
        $stmt = $this->db->prepare(
            "INSERT INTO activity_log (entity_type, entity_id, action, description, old_data, new_data, ip_address)
             VALUES (:entity_type, :entity_id, :action, :description, :old_data, :new_data, :ip_address)"
        );
        $stmt->execute([
            ':entity_type' => $entityType,
            ':entity_id'   => $entityId,
            ':action'      => $action,
            ':description' => $description,
            ':old_data'    => $oldData ? json_encode($oldData) : null,
            ':new_data'    => $newData ? json_encode($newData) : null,
            ':ip_address'  => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * List recent activity log entries
     */
    public function listRecent(int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM activity_log ORDER BY created_at DESC LIMIT :limit OFFSET :offset"
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Decode JSON columns
        foreach ($rows as &$row) {
            if ($row['old_data']) $row['old_data'] = json_decode($row['old_data'], true);
            if ($row['new_data']) $row['new_data'] = json_decode($row['new_data'], true);
        }
        return $rows;
    }

    /**
     * List activity for a specific entity
     */
    public function listByEntity(string $entityType, int $entityId): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM activity_log WHERE entity_type = :type AND entity_id = :id ORDER BY created_at DESC"
        );
        $stmt->execute([':type' => $entityType, ':id' => $entityId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            if ($row['old_data']) $row['old_data'] = json_decode($row['old_data'], true);
            if ($row['new_data']) $row['new_data'] = json_decode($row['new_data'], true);
        }
        return $rows;
    }

    /**
     * Get total count of log entries
     */
    public function count(): int
    {
        $stmt = $this->db->query("SELECT COUNT(*) FROM activity_log");
        return (int) $stmt->fetchColumn();
    }
}

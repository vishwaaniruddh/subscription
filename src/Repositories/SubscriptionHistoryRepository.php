<?php

namespace App\Repositories;

use App\Models\SubscriptionHistory;
use PDO;

class SubscriptionHistoryRepository extends BaseRepository
{
    public function create(SubscriptionHistory $history): SubscriptionHistory
    {
        $stmt = $this->db->prepare("
            INSERT INTO subscription_history (service_id, action_type, old_value, new_value) 
            VALUES (:service_id, :action_type, :old_value, :new_value)
        ");
        $stmt->execute([
            'service_id' => $history->serviceId,
            'action_type' => $history->actionType,
            'old_value' => $history->oldValue,
            'new_value' => $history->newValue
        ]);
        $history->id = (int)$this->db->lastInsertId();
        return $history;
    }

    public function findByServiceId(int $serviceId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM subscription_history WHERE service_id = :service_id ORDER BY timestamp DESC");
        $stmt->execute(['service_id' => $serviceId]);
        $results = [];
        while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[] = SubscriptionHistory::fromArray($data);
        }
        return $results;
    }
}

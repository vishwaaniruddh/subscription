<?php

namespace App\Models;

class SubscriptionHistory
{
    public ?int $id;
    public int $serviceId;
    public string $actionType;
    public ?string $oldValue;
    public ?string $newValue;
    public ?string $timestamp;

    public function __construct(
        int $serviceId,
        string $actionType,
        ?string $oldValue = null,
        ?string $newValue = null,
        ?int $id = null,
        ?string $timestamp = null
    ) {
        $this->id = $id;
        $this->serviceId = $serviceId;
        $this->actionType = $actionType;
        $this->oldValue = $oldValue;
        $this->newValue = $newValue;
        $this->timestamp = $timestamp;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            (int)$data['service_id'],
            $data['action_type'],
            $data['old_value'] ?? null,
            $data['new_value'] ?? null,
            isset($data['id']) ? (int)$data['id'] : null,
            $data['timestamp'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'service_id' => $this->serviceId,
            'action_type' => $this->actionType,
            'old_value' => $this->oldValue,
            'new_value' => $this->newValue,
            'timestamp' => $this->timestamp,
        ];
    }
}

<?php

namespace App\Models;

class User
{
    public ?int $id;
    public int $serviceId;
    public string $userIdentifier;
    public string $status;
    public ?string $createdAt;
    public ?string $deactivatedAt;

    public function __construct(
        int $serviceId,
        string $userIdentifier,
        string $status = 'active',
        ?int $id = null,
        ?string $createdAt = null,
        ?string $deactivatedAt = null
    ) {
        $this->id = $id;
        $this->serviceId = $serviceId;
        $this->userIdentifier = $userIdentifier;
        $this->status = $status;
        $this->createdAt = $createdAt;
        $this->deactivatedAt = $deactivatedAt;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            (int)$data['service_id'],
            $data['user_identifier'],
            $data['status'] ?? 'active',
            isset($data['id']) ? (int)$data['id'] : null,
            $data['created_at'] ?? null,
            $data['deactivated_at'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'service_id' => $this->serviceId,
            'user_identifier' => $this->userIdentifier,
            'status' => $this->status,
            'created_at' => $this->createdAt,
            'deactivated_at' => $this->deactivatedAt,
        ];
    }
}

<?php

namespace App\Models;

use DateTime;

class Service
{
    public ?int $id;
    public int $projectId;
    public string $serviceType;
    public int $userLimit;
    public int $activeUserCount;
    public string $startDate;
    public string $endDate;
    public ?string $createdAt;
    public ?string $updatedAt;

    public function __construct(
        int $projectId,
        string $serviceType,
        int $userLimit,
        string $startDate,
        string $endDate,
        int $activeUserCount = 0,
        ?int $id = null,
        ?string $createdAt = null,
        ?string $updatedAt = null
    ) {
        $this->id = $id;
        $this->projectId = $projectId;
        $this->serviceType = $serviceType;
        $this->userLimit = $userLimit;
        $this->activeUserCount = $activeUserCount;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public function isActive(?string $currentDate = null): bool
    {
        $today = $currentDate ? new DateTime($currentDate) : new DateTime();
        $start = new DateTime($this->startDate);
        $end = new DateTime($this->endDate);

        return $today >= $start && $today <= $end;
    }

    public function canAddUser(): bool
    {
        return $this->activeUserCount < $this->userLimit;
    }

    public function getUtilizationPercentage(): float
    {
        if ($this->userLimit === 0) return 0.0;
        return ($this->activeUserCount / $this->userLimit) * 100;
    }

    public function validate(): array
    {
        $errors = [];
        if ($this->projectId <= 0) {
            $errors['project_id'] = "Valid Project ID is required.";
        }
        if (!in_array($this->serviceType, ['web', 'mobile', 'other'])) {
            $errors['service_type'] = "Service type must be web, mobile, or other.";
        }
        if ($this->userLimit <= 0) {
            $errors['user_limit'] = "User limit must be greater than zero.";
        }
        if (strtotime($this->endDate) < strtotime($this->startDate)) {
            $errors['end_date'] = "End date cannot be before start date.";
        }
        return $errors;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            (int)$data['project_id'],
            $data['service_type'],
            (int)$data['user_limit'],
            $data['start_date'],
            $data['end_date'],
            (int)($data['active_user_count'] ?? 0),
            isset($data['id']) ? (int)$data['id'] : null,
            $data['created_at'] ?? null,
            $data['updated_at'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->projectId,
            'service_type' => $this->serviceType,
            'user_limit' => $this->userLimit,
            'active_user_count' => $this->activeUserCount,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'is_active' => $this->isActive(),
            'utilization_percentage' => $this->getUtilizationPercentage()
        ];
    }
}

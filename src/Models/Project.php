<?php

namespace App\Models;

class Project
{
    public ?int $id;
    public int $clientId;
    public string $name;
    public ?string $description;
    public ?string $apiKey;
    public ?string $domain;
    public ?string $createdAt;
    public ?string $updatedAt;

    public function __construct(
        int $clientId,
        string $name,
        ?string $description = null,
        ?string $apiKey = null,
        ?string $domain = null,
        ?int $id = null,
        ?string $createdAt = null,
        ?string $updatedAt = null
    ) {
        $this->id = $id;
        $this->clientId = $clientId;
        $this->name = $name;
        $this->description = $description;
        $this->apiKey = $apiKey;
        $this->domain = $domain;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public function validate(): array
    {
        $errors = [];
        if ($this->clientId <= 0) {
            $errors['client_id'] = "Valid Client ID is required.";
        }
        if (empty(trim($this->name))) {
            $errors['name'] = "Project name is required.";
        }
        return $errors;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            (int)$data['client_id'],
            $data['name'],
            $data['description'] ?? null,
            $data['api_key'] ?? null,
            $data['domain'] ?? null,
            isset($data['id']) ? (int)$data['id'] : null,
            $data['created_at'] ?? null,
            $data['updated_at'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->clientId,
            'name' => $this->name,
            'description' => $this->description,
            'api_key' => $this->apiKey,
            'domain' => $this->domain,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}

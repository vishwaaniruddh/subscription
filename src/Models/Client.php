<?php

namespace App\Models;

class Client
{
    public ?int $id;
    public string $name;
    public ?string $domain;
    public ?string $contactInfo;
    public ?string $createdAt;
    public ?string $updatedAt;

    public function __construct(
        string $name,
        ?string $domain = null,
        ?string $contactInfo = null,
        ?int $id = null,
        ?string $createdAt = null,
        ?string $updatedAt = null
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->domain = $domain;
        $this->contactInfo = $contactInfo;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public function validate(): array
    {
        $errors = [];
        if (empty(trim($this->name))) {
            $errors['name'] = "Client name is required.";
        }
        return $errors;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['name'],
            $data['domain'] ?? null,
            $data['contact_info'] ?? null,
            isset($data['id']) ? (int)$data['id'] : null,
            $data['created_at'] ?? null,
            $data['updated_at'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'domain' => $this->domain,
            'contact_info' => $this->contactInfo,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}

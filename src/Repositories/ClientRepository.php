<?php

namespace App\Repositories;

use App\Models\Client;
use PDO;

class ClientRepository extends BaseRepository
{
    public function create(Client $client): Client
    {
        $stmt = $this->db->prepare("INSERT INTO clients (name, domain, contact_info) VALUES (:name, :domain, :contact_info)");
        $stmt->execute([
            'name' => $client->name,
            'domain' => $client->domain,
            'contact_info' => $client->contactInfo
        ]);
        $client->id = (int)$this->db->lastInsertId();
        return $client;
    }

    public function findById(int $id): ?Client
    {
        $stmt = $this->db->prepare("SELECT * FROM clients WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $data ? Client::fromArray($data) : null;
    }

    public function findAll(): array
    {
        $stmt = $this->db->query("SELECT * FROM clients ORDER BY name ASC");
        $results = [];
        while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[] = Client::fromArray($data);
        }
        return $results;
    }

    public function update(Client $client): bool
    {
        $stmt = $this->db->prepare("UPDATE clients SET name = :name, domain = :domain, contact_info = :contact_info WHERE id = :id");
        return $stmt->execute([
            'name' => $client->name,
            'domain' => $client->domain,
            'contact_info' => $client->contactInfo,
            'id' => $client->id
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM clients WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }
}

<?php

namespace App\Services;

use App\Repositories\ClientRepository;
use App\Models\Client;
use Exception;

class ClientService
{
    private ClientRepository $repository;

    public function __construct(ClientRepository $repository)
    {
        $this->repository = $repository;
    }

    public function createClient(array $data): Client
    {
        $client = Client::fromArray($data);
        $errors = $client->validate();
        if (!empty($errors)) {
            throw new Exception(json_encode($errors));
        }
        return $this->repository->create($client);
    }

    public function getClient(int $id): ?Client
    {
        return $this->repository->findById($id);
    }

    public function listClients(): array
    {
        return $this->repository->findAll();
    }

    public function updateClient(int $id, array $data): bool
    {
        $client = $this->repository->findById($id);
        if (!$client) {
            throw new Exception("Client not found.");
        }
        if (isset($data['name'])) $client->name = $data['name'];
        if (isset($data['domain'])) $client->domain = $data['domain'];
        if (isset($data['contact_info'])) $client->contactInfo = $data['contact_info'];
        
        $errors = $client->validate();
        if (!empty($errors)) {
            throw new Exception(json_encode($errors));
        }
        return $this->repository->update($client);
    }

    public function deleteClient(int $id): bool
    {
        return $this->repository->delete($id);
    }
}

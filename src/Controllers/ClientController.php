<?php

namespace App\Controllers;

use App\Services\ClientService;
use App\Repositories\ActivityLogRepository;
use Exception;

class ClientController extends BaseController
{
    private ClientService $clientService;
    private ActivityLogRepository $activityLog;
    private \App\Services\JwtService $jwtService;

    public function __construct(
        ClientService $clientService, 
        ActivityLogRepository $activityLog,
        \App\Services\JwtService $jwtService
    ) {
        $this->clientService = $clientService;
        $this->activityLog = $activityLog;
        $this->jwtService = $jwtService;
    }

    public function list(): void
    {
        $this->checkAuth($this->jwtService);
        $clients = $this->clientService->listClients();
        $clientsArray = array_map(fn($client) => $client->toArray(), $clients);
        $this->jsonResponse($clientsArray);
    }

    public function get(int $id): void
    {
        $client = $this->clientService->getClient($id);
        if (!$client) {
            $this->errorResponse("Client not found", 404);
        }
        $this->jsonResponse($client->toArray());
    }

    public function create(): void
    {
        try {
            $data = $this->getJsonInput();
            $client = $this->clientService->createClient($data);
            $result = $client->toArray();

            $this->activityLog->log('client', $result['id'], 'created',
                "Client \"{$result['name']}\" was created",
                null, $result
            );

            $this->jsonResponse($result, 201);
        } catch (Exception $e) {
            $this->activityLog->log('client', null, 'create_failed',
                "Client creation failed: {$e->getMessage()}", null, $data ?? null);
            $this->errorResponse($e->getMessage());
        }
    }

    public function update(int $id): void
    {
        try {
            $old = $this->clientService->getClient($id);
            $oldData = $old ? $old->toArray() : null;

            $data = $this->getJsonInput();
            $this->clientService->updateClient($id, $data);

            $this->activityLog->log('client', $id, 'updated',
                "Client #{$id} was updated",
                $oldData, $data
            );

            $this->jsonResponse(['success' => true]);
        } catch (Exception $e) {
            $this->activityLog->log('client', $id, 'update_failed',
                "Client #{$id} update failed: {$e->getMessage()}");
            $this->errorResponse($e->getMessage());
        }
    }

    public function delete(int $id): void
    {
        $old = $this->clientService->getClient($id);
        $oldData = $old ? $old->toArray() : null;
        $name = $oldData['name'] ?? "#{$id}";

        $this->clientService->deleteClient($id);

        $this->activityLog->log('client', $id, 'deleted',
            "Client \"{$name}\" was deleted",
            $oldData, null
        );

        $this->jsonResponse(['success' => true]);
    }
}

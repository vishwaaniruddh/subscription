<?php

namespace App\Controllers;

use Exception;

abstract class BaseController
{
    protected function jsonResponse(mixed $data, int $statusCode = 200): void
    {
        header('Content-Type: application/json');
        http_response_code($statusCode);
        echo json_encode($data);
        exit;
    }

    protected function errorResponse(string $message, int $statusCode = 400, ?string $errorCode = null, mixed $context = null): void
    {
        $response = [
            'success' => false,
            'error' => $message,
        ];
        
        if ($errorCode) $response['error_code'] = $errorCode;
        if ($context) $response['context'] = $context;

        $this->jsonResponse($response, $statusCode);
    }

    protected function getJsonInput(): array
    {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        return $data ?? [];
    }

    protected function checkAuth(\App\Services\JwtService $jwtService): array
    {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        
        if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            $token = $matches[1];
            $payload = $jwtService->validateToken($token);
            if ($payload) {
                return $payload;
            }
        }
        
        $this->errorResponse("Unauthorized. Please login to access this resource.", 401);
        exit;
    }
}

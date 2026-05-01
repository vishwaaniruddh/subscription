<?php

namespace App\Controllers;

use App\Database;
use App\Services\JwtService;
use PDO;
use Exception;

class AuthController extends BaseController
{
    private JwtService $jwtService;

    public function __construct(JwtService $jwtService)
    {
        $this->jwtService = $jwtService;
    }

    public function login(): void
    {
        try {
            $data = $this->getJsonInput();
            $username = $data['username'] ?? '';
            $password = $data['password'] ?? '';

            if (empty($username) || empty($password)) {
                $this->errorResponse("Username and password are required.", 400);
            }

            $db = Database::getInstance();
            $stmt = $db->prepare("SELECT * FROM admins WHERE username = :u");
            $stmt->execute(['u' => $username]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$admin || !password_verify($password, $admin['password_hash'])) {
                $this->errorResponse("Invalid credentials.", 401);
            }

            $token = $this->jwtService->generateToken([
                'admin_id' => $admin['id'],
                'username' => $admin['username'],
                'name' => $admin['full_name']
            ]);

            $this->jsonResponse([
                'status' => 'success',
                'token' => $token,
                'admin' => [
                    'username' => $admin['username'],
                    'name' => $admin['full_name']
                ]
            ]);

        } catch (Exception $e) {
            $this->errorResponse($e->getMessage());
        }
    }

    public function validateToken(): void
    {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        
        if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            $token = $matches[1];
            $payload = $this->jwtService->validateToken($token);
            if ($payload) {
                $this->jsonResponse(['status' => 'success', 'admin' => $payload]);
                return;
            }
        }
        $this->errorResponse("Invalid token.", 401);
    }
}

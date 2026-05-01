<?php

namespace App\Services;

use Exception;

class JwtService
{
    private string $secret = "sub_manager_secret_key_2024_!@#"; // In production, move to .env
    private string $alg = 'HS256';

    public function generateToken(array $payload): string
    {
        $header = json_encode(['typ' => 'JWT', 'alg' => $this->alg]);
        
        $payload['iat'] = time();
        $payload['exp'] = time() + (60 * 60 * 24); // 24 hours
        
        $base64UrlHeader = $this->base64UrlEncode($header);
        $base64UrlPayload = $this->base64UrlEncode(json_encode($payload));
        
        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $this->secret, true);
        $base64UrlSignature = $this->base64UrlEncode($signature);
        
        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }

    public function validateToken(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) return null;

        list($header, $payload, $signature) = $parts;

        $validSignature = hash_hmac('sha256', $header . "." . $payload, $this->secret, true);
        if (!$this->hashEquals($this->base64UrlEncode($validSignature), $signature)) {
            return null;
        }

        $decodedPayload = json_decode($this->base64UrlDecode($payload), true);
        if (!$decodedPayload || (isset($decodedPayload['exp']) && $decodedPayload['exp'] < time())) {
            return null;
        }

        return $decodedPayload;
    }

    private function base64UrlEncode($data): string
    {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
    }

    private function base64UrlDecode($data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $padlen = 4 - $remainder;
            $data .= str_repeat('=', $padlen);
        }
        return base64_decode(str_replace(['-', '_'], ['+', '/'], $data));
    }

    private function hashEquals($str1, $str2): bool
    {
        if (strlen($str1) !== strlen($str2)) return false;
        $res = $str1 ^ $str2;
        $ret = 0;
        for ($i = strlen($res) - 1; $i >= 0; $i--) $ret |= ord($res[$i]);
        return $ret === 0;
    }
}

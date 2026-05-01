<?php

namespace App\Controllers;

use App\Services\ValidationService;

class ValidationController extends BaseController
{
    private ValidationService $validationService;

    public function __construct(ValidationService $validationService)
    {
        $this->validationService = $validationService;
    }

    public function validate(int $serviceId): void
    {
        $result = $this->validationService->validateUserCreation($serviceId);
        if ($result['success']) {
            $this->jsonResponse($result);
        } else {
            $this->errorResponse(
                $result['message'],
                400,
                $result['error_code'],
                $result['context'] ?? null
            );
        }
    }
}

<?php

use App\Services\Logger;
use App\Services\ValidationService;
use App\Services\UserService;
use App\Repositories\ServiceRepository;
use App\Repositories\UserRepository;
use App\Models\Service;
use App\Database;

beforeEach(function () {
    $this->db = Database::getInstance();
    
    // Use a temporary directory for test logs
    $this->testLogDir = sys_get_temp_dir() . '/subscription_integration_logs_' . uniqid();
    $this->logger = new Logger($this->testLogDir);
    
    // Create repositories with logger
    $this->serviceRepo = new ServiceRepository($this->db, $this->logger);
    $this->userRepo = new UserRepository($this->db, $this->logger);
    
    // Create services with logger
    $this->validationService = new ValidationService($this->serviceRepo, $this->logger);
    $this->userService = new UserService($this->userRepo, $this->serviceRepo, $this->validationService);
    
    // Clean up any existing test data
    $this->db->exec("DELETE FROM users WHERE service_id IN (SELECT id FROM services WHERE project_id = 9999)");
    $this->db->exec("DELETE FROM services WHERE project_id = 9999");
    $this->db->exec("DELETE FROM projects WHERE id = 9999");
    $this->db->exec("DELETE FROM clients WHERE id = 9999");
    
    // Create test data
    $this->db->exec("INSERT INTO clients (id, name, contact_info) VALUES (9999, 'Test Client', 'test@example.com')");
    $this->db->exec("INSERT INTO projects (id, client_id, name, description) VALUES (9999, 9999, 'Test Project', 'Test Description')");
});

afterEach(function () {
    // Clean up test data
    $this->db->exec("DELETE FROM users WHERE service_id IN (SELECT id FROM services WHERE project_id = 9999)");
    $this->db->exec("DELETE FROM services WHERE project_id = 9999");
    $this->db->exec("DELETE FROM projects WHERE id = 9999");
    $this->db->exec("DELETE FROM clients WHERE id = 9999");
    
    // Clean up test logs
    if ($this->logger) {
        $this->logger->clearLog();
    }
    if (is_dir($this->testLogDir)) {
        rmdir($this->testLogDir);
    }
});

test('validation failure for USER_LIMIT_EXCEEDED is logged', function () {
    // Create a service with user_limit = 1
    $service = Service::fromArray([
        'project_id' => 9999,
        'service_type' => 'web',
        'user_limit' => 1,
        'active_user_count' => 0,
        'start_date' => date('Y-m-d', strtotime('-1 day')),
        'end_date' => date('Y-m-d', strtotime('+30 days'))
    ]);
    $createdService = $this->serviceRepo->create($service);
    $serviceId = $createdService->id;
    
    // Register first user (should succeed)
    $this->userService->registerUser([
        'service_id' => $serviceId,
        'user_identifier' => 'user1@example.com',
        'status' => 'active'
    ]);
    
    // Try to validate second user creation (should fail and log)
    $validation = $this->validationService->validateUserCreation($serviceId);
    
    expect($validation['success'])->toBeFalse();
    expect($validation['error_code'])->toBe('USER_LIMIT_EXCEEDED');
    
    // Check that the validation failure was logged
    $logContent = file_get_contents($this->logger->getLogFile());
    $logLines = explode(PHP_EOL, trim($logContent));
    
    // Find the validation failure log entry
    $validationLog = null;
    foreach ($logLines as $line) {
        $entry = json_decode($line, true);
        if (isset($entry['level']) && $entry['level'] === 'VALIDATION_FAILURE' && 
            isset($entry['error_code']) && $entry['error_code'] === 'USER_LIMIT_EXCEEDED') {
            $validationLog = $entry;
            break;
        }
    }
    
    expect($validationLog)->not->toBeNull();
    expect($validationLog['error_code'])->toBe('USER_LIMIT_EXCEEDED');
    expect($validationLog['request_details']['service_id'])->toBe($serviceId);
    expect($validationLog['request_details']['user_limit'])->toBe(1);
    expect($validationLog['request_details']['active_user_count'])->toBe(1);
});

test('validation failure for SUBSCRIPTION_EXPIRED is logged', function () {
    // Create an expired service
    $service = Service::fromArray([
        'project_id' => 9999,
        'service_type' => 'mobile',
        'user_limit' => 10,
        'active_user_count' => 0,
        'start_date' => date('Y-m-d', strtotime('-30 days')),
        'end_date' => date('Y-m-d', strtotime('-1 day'))
    ]);
    $createdService = $this->serviceRepo->create($service);
    $serviceId = $createdService->id;
    
    // Try to validate user creation (should fail and log)
    $validation = $this->validationService->validateUserCreation($serviceId);
    
    expect($validation['success'])->toBeFalse();
    expect($validation['error_code'])->toBe('SUBSCRIPTION_EXPIRED');
    
    // Check that the validation failure was logged
    $logContent = file_get_contents($this->logger->getLogFile());
    $logLines = explode(PHP_EOL, trim($logContent));
    
    // Find the validation failure log entry
    $validationLog = null;
    foreach ($logLines as $line) {
        $entry = json_decode($line, true);
        if (isset($entry['level']) && $entry['level'] === 'VALIDATION_FAILURE' && 
            isset($entry['error_code']) && $entry['error_code'] === 'SUBSCRIPTION_EXPIRED') {
            $validationLog = $entry;
            break;
        }
    }
    
    expect($validationLog)->not->toBeNull();
    expect($validationLog['error_code'])->toBe('SUBSCRIPTION_EXPIRED');
    expect($validationLog['request_details']['service_id'])->toBe($serviceId);
    expect($validationLog['request_details'])->toHaveKey('start_date');
    expect($validationLog['request_details'])->toHaveKey('end_date');
});

test('database errors during user registration are logged', function () {
    // Create a service
    $service = Service::fromArray([
        'project_id' => 9999,
        'service_type' => 'web',
        'user_limit' => 10,
        'active_user_count' => 0,
        'start_date' => date('Y-m-d', strtotime('-1 day')),
        'end_date' => date('Y-m-d', strtotime('+30 days'))
    ]);
    $createdService = $this->serviceRepo->create($service);
    
    // Try to register a user with invalid service_id (should cause database error)
    try {
        $this->userService->registerUser([
            'service_id' => 999999, // Non-existent service
            'user_identifier' => 'user@example.com',
            'status' => 'active'
        ]);
        expect(false)->toBeTrue('Should have thrown an exception');
    } catch (Exception $e) {
        // Expected to fail
        expect($e)->toBeInstanceOf(Exception::class);
    }
    
    // Check that the database error was logged
    $logContent = file_get_contents($this->logger->getLogFile());
    $logLines = explode(PHP_EOL, trim($logContent));
    
    // Find an error log entry
    $errorLog = null;
    foreach ($logLines as $line) {
        $entry = json_decode($line, true);
        if (isset($entry['level']) && $entry['level'] === 'ERROR') {
            $errorLog = $entry;
            break;
        }
    }
    
    expect($errorLog)->not->toBeNull();
    expect($errorLog)->toHaveKey('message');
    expect($errorLog)->toHaveKey('context');
});

test('multiple validation failures are logged sequentially', function () {
    // Create a service at capacity
    $service = Service::fromArray([
        'project_id' => 9999,
        'service_type' => 'web',
        'user_limit' => 1,
        'active_user_count' => 1,
        'start_date' => date('Y-m-d', strtotime('-1 day')),
        'end_date' => date('Y-m-d', strtotime('+30 days'))
    ]);
    $createdService = $this->serviceRepo->create($service);
    $serviceId = $createdService->id;
    
    // Trigger multiple validation failures
    $this->validationService->validateUserCreation($serviceId);
    $this->validationService->validateUserCreation($serviceId);
    $this->validationService->validateUserCreation($serviceId);
    
    // Check that all validation failures were logged
    $logContent = file_get_contents($this->logger->getLogFile());
    $logLines = explode(PHP_EOL, trim($logContent));
    
    $validationFailureCount = 0;
    foreach ($logLines as $line) {
        $entry = json_decode($line, true);
        if (isset($entry['level']) && $entry['level'] === 'VALIDATION_FAILURE') {
            $validationFailureCount++;
        }
    }
    
    expect($validationFailureCount)->toBe(3);
});

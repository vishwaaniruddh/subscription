<?php

use App\Services\ValidationService;
use App\Services\Logger;
use App\Repositories\ServiceRepository;

/**
 * Feature: subscription-management-module
 * Property 41: Validation Logging
 * 
 * For any validation failure, an entry should be created in the system logs 
 * containing the timestamp, error type, and request details.
 * 
 * **Validates: Requirements 13.5**
 * 
 * This test verifies that ValidationService logs all validation failures:
 * - USER_LIMIT_EXCEEDED errors are logged with service_id, user_limit, active_user_count
 * - SUBSCRIPTION_EXPIRED errors are logged with service_id, start_date, end_date, current_date
 * - Log entries contain timestamp, error_code, message, and request_details
 * - All validation failures result in log entries
 */

test('validation failures are logged with timestamp, error code, and request details', function () {
    $faker = Faker\Factory::create();
    $pdo = getTestDatabase();
    $repository = new ServiceRepository($pdo);
    
    // Create a temporary log directory for testing
    $tempLogDir = sys_get_temp_dir() . '/validation_logging_test_' . uniqid();
    mkdir($tempLogDir, 0755, true);
    
    $logger = new Logger($tempLogDir);
    $validationService = new ValidationService($repository, $logger);
    
    // Clean up test data
    $pdo->exec("DELETE FROM services WHERE project_id >= 9000 AND project_id < 9100");
    $pdo->exec("DELETE FROM projects WHERE id >= 9000 AND id < 9100");
    
    // Ensure test client exists
    $pdo->exec("INSERT IGNORE INTO clients (id, name, contact_info) VALUES (1, 'Test Client', 'test@example.com')");
    
    // Create test projects for foreign key constraint
    for ($i = 0; $i < 100; $i++) {
        $pdo->exec("INSERT INTO projects (id, client_id, name, description) VALUES (" . (9000 + $i) . ", 1, 'Test Project $i', 'Test')");
    }
    
    // Clear log file before test
    $logger->clearLog();
    
    $loggedFailures = 0;
    
    // Run 100 iterations testing various validation failure scenarios
    for ($i = 0; $i < 100; $i++) {
        // Randomly choose validation failure scenario
        $scenario = $faker->randomElement(['USER_LIMIT_EXCEEDED', 'SUBSCRIPTION_EXPIRED']);
        
        if ($scenario === 'USER_LIMIT_EXCEEDED') {
            // Create service at or over capacity with active subscription
            $userLimit = $faker->numberBetween(10, 1000);
            $activeUserCount = $faker->numberBetween($userLimit, $userLimit + 100);
            
            $startDate = $faker->dateTimeBetween('-1 year', '-1 day');
            $endDate = $faker->dateTimeBetween('+1 day', '+1 year');
            $currentDate = $faker->dateTimeBetween($startDate, $endDate);
            
            $startDateStr = $startDate->format('Y-m-d');
            $endDateStr = $endDate->format('Y-m-d');
            $currentDateStr = $currentDate->format('Y-m-d');
            
            $serviceData = [
                'project_id' => 9000 + $i,
                'service_type' => $faker->randomElement(['web', 'mobile', 'other']),
                'user_limit' => $userLimit,
                'active_user_count' => $activeUserCount,
                'start_date' => $startDateStr,
                'end_date' => $endDateStr,
            ];
            
            $stmt = $pdo->prepare("
                INSERT INTO services (project_id, service_type, user_limit, active_user_count, start_date, end_date)
                VALUES (:project_id, :service_type, :user_limit, :active_user_count, :start_date, :end_date)
            ");
            $stmt->execute($serviceData);
            $serviceId = (int) $pdo->lastInsertId();
            
            // Trigger validation failure
            $result = $validationService->validateUserCreation($serviceId, $currentDateStr);
            
            // Verify validation failed
            expect($result['success'])->toBeFalse(
                "Validation should fail for USER_LIMIT_EXCEEDED scenario (iteration $i)"
            );
            
            expect($result['error_code'])->toBe('USER_LIMIT_EXCEEDED');
            
            $loggedFailures++;
            
        } else { // SUBSCRIPTION_EXPIRED
            // Create service with available capacity but expired subscription
            $userLimit = $faker->numberBetween(10, 1000);
            $activeUserCount = $faker->numberBetween(0, $userLimit - 1);
            
            $startDate = $faker->dateTimeBetween('-2 years', '-1 year');
            $endDate = $faker->dateTimeBetween($startDate, '-6 months');
            
            $startDateStr = $startDate->format('Y-m-d');
            $endDateStr = $endDate->format('Y-m-d');
            
            // Generate current date after end date
            $currentDate = $faker->dateTimeBetween((clone $endDate)->modify('+1 day'), 'now');
            $currentDateStr = $currentDate->format('Y-m-d');
            
            $serviceData = [
                'project_id' => 9000 + $i,
                'service_type' => $faker->randomElement(['web', 'mobile', 'other']),
                'user_limit' => $userLimit,
                'active_user_count' => $activeUserCount,
                'start_date' => $startDateStr,
                'end_date' => $endDateStr,
            ];
            
            $stmt = $pdo->prepare("
                INSERT INTO services (project_id, service_type, user_limit, active_user_count, start_date, end_date)
                VALUES (:project_id, :service_type, :user_limit, :active_user_count, :start_date, :end_date)
            ");
            $stmt->execute($serviceData);
            $serviceId = (int) $pdo->lastInsertId();
            
            // Trigger validation failure
            $result = $validationService->validateUserCreation($serviceId, $currentDateStr);
            
            // Verify validation failed
            expect($result['success'])->toBeFalse(
                "Validation should fail for SUBSCRIPTION_EXPIRED scenario (iteration $i)"
            );
            
            expect($result['error_code'])->toBe('SUBSCRIPTION_EXPIRED');
            
            $loggedFailures++;
        }
    }
    
    // Read log file and verify entries
    $logFile = $logger->getLogFile();
    expect(file_exists($logFile))->toBeTrue(
        "Log file should exist after validation failures"
    );
    
    $logContents = file_get_contents($logFile);
    $logLines = array_filter(explode(PHP_EOL, $logContents));
    
    // Verify we have log entries for all failures
    expect(count($logLines))->toBe($loggedFailures,
        "Should have exactly $loggedFailures log entries for $loggedFailures validation failures"
    );
    
    // Verify each log entry has required fields
    foreach ($logLines as $index => $logLine) {
        $logEntry = json_decode($logLine, true);
        
        expect($logEntry)->toBeArray(
            "Log entry $index should be valid JSON"
        );
        
        // Verify timestamp exists and is in ISO 8601 format
        expect($logEntry)->toHaveKey('timestamp');
        expect($logEntry['timestamp'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/',
            "Timestamp should be in ISO 8601 format"
        );
        
        // Verify level is VALIDATION_FAILURE
        expect($logEntry)->toHaveKey('level');
        expect($logEntry['level'])->toBe('VALIDATION_FAILURE',
            "Log entry $index should have level VALIDATION_FAILURE"
        );
        
        // Verify error_code exists and is valid
        expect($logEntry)->toHaveKey('error_code');
        expect($logEntry['error_code'])->toBeIn(['USER_LIMIT_EXCEEDED', 'SUBSCRIPTION_EXPIRED'],
            "Error code should be USER_LIMIT_EXCEEDED or SUBSCRIPTION_EXPIRED"
        );
        
        // Verify message exists
        expect($logEntry)->toHaveKey('message');
        expect($logEntry['message'])->toBeString();
        expect(strlen($logEntry['message']))->toBeGreaterThan(0,
            "Message should not be empty"
        );
        
        // Verify request_details exists and contains required fields
        expect($logEntry)->toHaveKey('request_details');
        expect($logEntry['request_details'])->toBeArray();
        
        // Verify service_id is present in request_details
        expect($logEntry['request_details'])->toHaveKey('service_id');
        
        // Verify error-specific context
        if ($logEntry['error_code'] === 'USER_LIMIT_EXCEEDED') {
            expect($logEntry['request_details'])->toHaveKey('user_limit');
            expect($logEntry['request_details'])->toHaveKey('active_user_count');
        } elseif ($logEntry['error_code'] === 'SUBSCRIPTION_EXPIRED') {
            expect($logEntry['request_details'])->toHaveKey('start_date');
            expect($logEntry['request_details'])->toHaveKey('end_date');
            expect($logEntry['request_details'])->toHaveKey('current_date');
        }
    }
    
    // Clean up
    $pdo->exec("DELETE FROM services WHERE project_id >= 9000 AND project_id < 9100");
    $pdo->exec("DELETE FROM projects WHERE id >= 9000 AND id < 9100");
    
    // Clean up log directory
    $logger->clearLog();
    rmdir($tempLogDir);
    
})->group('property', 'validation', 'validation-logging');

test('successful validations are not logged as failures', function () {
    $faker = Faker\Factory::create();
    $pdo = getTestDatabase();
    $repository = new ServiceRepository($pdo);
    
    // Create a temporary log directory for testing
    $tempLogDir = sys_get_temp_dir() . '/validation_logging_success_test_' . uniqid();
    mkdir($tempLogDir, 0755, true);
    
    $logger = new Logger($tempLogDir);
    $validationService = new ValidationService($repository, $logger);
    
    // Clean up test data
    $pdo->exec("DELETE FROM services WHERE project_id >= 9100 AND project_id < 9200");
    $pdo->exec("DELETE FROM projects WHERE id >= 9100 AND id < 9200");
    
    // Ensure test client exists
    $pdo->exec("INSERT IGNORE INTO clients (id, name, contact_info) VALUES (1, 'Test Client', 'test@example.com')");
    
    // Create test projects for foreign key constraint
    for ($i = 0; $i < 100; $i++) {
        $pdo->exec("INSERT INTO projects (id, client_id, name, description) VALUES (" . (9100 + $i) . ", 1, 'Test Project $i', 'Test')");
    }
    
    // Clear log file before test
    $logger->clearLog();
    
    // Run 100 iterations with successful validations
    for ($i = 0; $i < 100; $i++) {
        // Create service with available capacity and active subscription
        $userLimit = $faker->numberBetween(10, 1000);
        $activeUserCount = $faker->numberBetween(0, $userLimit - 1);
        
        $startDate = $faker->dateTimeBetween('-1 year', '-1 day');
        $endDate = $faker->dateTimeBetween('+1 day', '+1 year');
        $currentDate = $faker->dateTimeBetween($startDate, $endDate);
        
        $startDateStr = $startDate->format('Y-m-d');
        $endDateStr = $endDate->format('Y-m-d');
        $currentDateStr = $currentDate->format('Y-m-d');
        
        $serviceData = [
            'project_id' => 9100 + $i,
            'service_type' => $faker->randomElement(['web', 'mobile', 'other']),
            'user_limit' => $userLimit,
            'active_user_count' => $activeUserCount,
            'start_date' => $startDateStr,
            'end_date' => $endDateStr,
        ];
        
        $stmt = $pdo->prepare("
            INSERT INTO services (project_id, service_type, user_limit, active_user_count, start_date, end_date)
            VALUES (:project_id, :service_type, :user_limit, :active_user_count, :start_date, :end_date)
        ");
        $stmt->execute($serviceData);
        $serviceId = (int) $pdo->lastInsertId();
        
        // Trigger successful validation
        $result = $validationService->validateUserCreation($serviceId, $currentDateStr);
        
        // Verify validation succeeded
        expect($result['success'])->toBeTrue(
            "Validation should succeed (iteration $i)"
        );
    }
    
    // Verify log file is empty or doesn't exist (no validation failures logged)
    $logFile = $logger->getLogFile();
    
    if (file_exists($logFile)) {
        $logContents = file_get_contents($logFile);
        $logLines = array_filter(explode(PHP_EOL, $logContents));
        
        expect(count($logLines))->toBe(0,
            "No log entries should exist for successful validations"
        );
    } else {
        // Log file doesn't exist, which is also acceptable
        expect(true)->toBeTrue(
            "Log file doesn't exist, which is acceptable for no failures"
        );
    }
    
    // Clean up
    $pdo->exec("DELETE FROM services WHERE project_id >= 9100 AND project_id < 9200");
    $pdo->exec("DELETE FROM projects WHERE id >= 9100 AND id < 9200");
    
    // Clean up log directory
    if (file_exists($logFile)) {
        $logger->clearLog();
    }
    rmdir($tempLogDir);
    
})->group('property', 'validation', 'validation-logging');

test('log entries are written atomically and persist across multiple failures', function () {
    $faker = Faker\Factory::create();
    $pdo = getTestDatabase();
    $repository = new ServiceRepository($pdo);
    
    // Create a temporary log directory for testing
    $tempLogDir = sys_get_temp_dir() . '/validation_logging_atomic_test_' . uniqid();
    mkdir($tempLogDir, 0755, true);
    
    $logger = new Logger($tempLogDir);
    $validationService = new ValidationService($repository, $logger);
    
    // Clean up test data
    $pdo->exec("DELETE FROM services WHERE project_id >= 9200 AND project_id < 9300");
    $pdo->exec("DELETE FROM projects WHERE id >= 9200 AND id < 9300");
    
    // Ensure test client exists
    $pdo->exec("INSERT IGNORE INTO clients (id, name, contact_info) VALUES (1, 'Test Client', 'test@example.com')");
    
    // Create test projects for foreign key constraint
    for ($i = 0; $i < 100; $i++) {
        $pdo->exec("INSERT INTO projects (id, client_id, name, description) VALUES (" . (9200 + $i) . ", 1, 'Test Project $i', 'Test')");
    }
    
    // Clear log file before test
    $logger->clearLog();
    
    // Run 100 iterations with validation failures
    for ($i = 0; $i < 100; $i++) {
        // Create service at capacity
        $userLimit = $faker->numberBetween(10, 100);
        $activeUserCount = $userLimit; // At capacity
        
        $startDate = $faker->dateTimeBetween('-1 year', '-1 day');
        $endDate = $faker->dateTimeBetween('+1 day', '+1 year');
        $currentDate = $faker->dateTimeBetween($startDate, $endDate);
        
        $startDateStr = $startDate->format('Y-m-d');
        $endDateStr = $endDate->format('Y-m-d');
        $currentDateStr = $currentDate->format('Y-m-d');
        
        $serviceData = [
            'project_id' => 9200 + $i,
            'service_type' => $faker->randomElement(['web', 'mobile', 'other']),
            'user_limit' => $userLimit,
            'active_user_count' => $activeUserCount,
            'start_date' => $startDateStr,
            'end_date' => $endDateStr,
        ];
        
        $stmt = $pdo->prepare("
            INSERT INTO services (project_id, service_type, user_limit, active_user_count, start_date, end_date)
            VALUES (:project_id, :service_type, :user_limit, :active_user_count, :start_date, :end_date)
        ");
        $stmt->execute($serviceData);
        $serviceId = (int) $pdo->lastInsertId();
        
        // Trigger validation failure
        $result = $validationService->validateUserCreation($serviceId, $currentDateStr);
        
        // Verify validation failed
        expect($result['success'])->toBeFalse();
        
        // Verify log file exists and has correct number of entries so far
        $logFile = $logger->getLogFile();
        expect(file_exists($logFile))->toBeTrue(
            "Log file should exist after validation failure $i"
        );
        
        $logContents = file_get_contents($logFile);
        $logLines = array_filter(explode(PHP_EOL, $logContents));
        
        expect(count($logLines))->toBe($i + 1,
            "Should have " . ($i + 1) . " log entries after " . ($i + 1) . " failures"
        );
        
        // Verify each line is valid JSON
        foreach ($logLines as $logLine) {
            $logEntry = json_decode($logLine, true);
            expect($logEntry)->toBeArray(
                "Each log line should be valid JSON"
            );
        }
    }
    
    // Clean up
    $pdo->exec("DELETE FROM services WHERE project_id >= 9200 AND project_id < 9300");
    $pdo->exec("DELETE FROM projects WHERE id >= 9200 AND id < 9300");
    
    // Clean up log directory
    $logger->clearLog();
    rmdir($tempLogDir);
    
})->group('property', 'validation', 'validation-logging');

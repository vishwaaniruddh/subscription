<?php

use App\Services\ValidationService;
use App\Repositories\ServiceRepository;
use App\Models\Service;

/**
 * Feature: subscription-management-module
 * Property 11: Validation Correctness
 * 
 * For any service, validation should return success if and only if 
 * active_user_count < user_limit AND the subscription is active 
 * (current date is between start_date and end_date).
 * 
 * **Validates: Requirements 5.2, 5.3, 5.4, 7.7**
 * 
 * This test verifies that ValidationService.validateUserCreation correctly 
 * validates both capacity and subscription status in all combinations:
 * - Valid capacity + active subscription → success
 * - No capacity + active subscription → failure (USER_LIMIT_EXCEEDED)
 * - Valid capacity + expired subscription → failure (SUBSCRIPTION_EXPIRED)
 * - No capacity + expired subscription → failure
 */

test('validation succeeds when service has capacity and active subscription', function () {
    $faker = Faker\Factory::create();
    $pdo = getTestDatabase();
    $repository = new ServiceRepository($pdo);
    $validationService = new ValidationService($repository);
    
    // Clean up test data
    $pdo->exec("DELETE FROM services WHERE project_id >= 8000 AND project_id < 8100");
    $pdo->exec("DELETE FROM projects WHERE id >= 8000 AND id < 8100");
    
    // Ensure test client exists
    $pdo->exec("INSERT IGNORE INTO clients (id, name, contact_info) VALUES (1, 'Test Client', 'test@example.com')");
    
    // Create test projects for foreign key constraint
    for ($i = 0; $i < 100; $i++) {
        $pdo->exec("INSERT INTO projects (id, client_id, name, description) VALUES (" . (8000 + $i) . ", 1, 'Test Project $i', 'Test')");
    }
    
    // Run 100 iterations
    for ($i = 0; $i < 100; $i++) {
        // Generate service with available capacity
        $userLimit = $faker->numberBetween(10, 1000);
        $activeUserCount = $faker->numberBetween(0, $userLimit - 1); // Ensure capacity available
        
        // Generate active subscription (current date within range)
        $startDate = $faker->dateTimeBetween('-1 year', '-1 day');
        $endDate = $faker->dateTimeBetween('+1 day', '+1 year');
        $currentDate = $faker->dateTimeBetween($startDate, $endDate);
        
        $startDateStr = $startDate->format('Y-m-d');
        $endDateStr = $endDate->format('Y-m-d');
        $currentDateStr = $currentDate->format('Y-m-d');
        
        // Create service in database
        $serviceData = [
            'project_id' => 8000 + $i,
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
        
        // Validate user creation
        $result = $validationService->validateUserCreation($serviceId, $currentDateStr);
        
        // Verify validation succeeds
        expect($result['success'])->toBeTrue(
            "Validation should succeed when service has capacity ($activeUserCount < $userLimit) " .
            "and subscription is active (current: $currentDateStr, range: $startDateStr to $endDateStr)"
        );
        
        expect($result)->toHaveKey('message');
        expect($result)->not->toHaveKey('error_code');
    }
    
    // Clean up
    $pdo->exec("DELETE FROM services WHERE project_id >= 8000 AND project_id < 8100");
    $pdo->exec("DELETE FROM projects WHERE id >= 8000 AND id < 8100");
})->group('property', 'validation', 'validation-correctness');

test('validation fails with USER_LIMIT_EXCEEDED when at capacity with active subscription', function () {
    $faker = Faker\Factory::create();
    $pdo = getTestDatabase();
    $repository = new ServiceRepository($pdo);
    $validationService = new ValidationService($repository);
    
    // Clean up test data
    $pdo->exec("DELETE FROM services WHERE project_id >= 8100 AND project_id < 8200");
    $pdo->exec("DELETE FROM projects WHERE id >= 8100 AND id < 8200");
    
    // Ensure test client exists
    $pdo->exec("INSERT IGNORE INTO clients (id, name, contact_info) VALUES (1, 'Test Client', 'test@example.com')");
    
    // Create test projects for foreign key constraint
    for ($i = 0; $i < 100; $i++) {
        $pdo->exec("INSERT INTO projects (id, client_id, name, description) VALUES (" . (8100 + $i) . ", 1, 'Test Project $i', 'Test')");
    }
    
    // Run 100 iterations
    for ($i = 0; $i < 100; $i++) {
        // Generate service at or over capacity
        $userLimit = $faker->numberBetween(10, 1000);
        $activeUserCount = $faker->numberBetween($userLimit, $userLimit + 100); // At or over capacity
        
        // Generate active subscription
        $startDate = $faker->dateTimeBetween('-1 year', '-1 day');
        $endDate = $faker->dateTimeBetween('+1 day', '+1 year');
        $currentDate = $faker->dateTimeBetween($startDate, $endDate);
        
        $startDateStr = $startDate->format('Y-m-d');
        $endDateStr = $endDate->format('Y-m-d');
        $currentDateStr = $currentDate->format('Y-m-d');
        
        // Create service in database
        $serviceData = [
            'project_id' => 8100 + $i,
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
        
        // Validate user creation
        $result = $validationService->validateUserCreation($serviceId, $currentDateStr);
        
        // Verify validation fails with correct error code
        expect($result['success'])->toBeFalse(
            "Validation should fail when service is at capacity ($activeUserCount >= $userLimit) " .
            "even with active subscription"
        );
        
        expect($result)->toHaveKey('error_code');
        expect($result['error_code'])->toBe('USER_LIMIT_EXCEEDED',
            "Error code should be USER_LIMIT_EXCEEDED when at capacity"
        );
        
        expect($result)->toHaveKey('context');
        expect($result['context'])->toHaveKey('user_limit');
        expect($result['context'])->toHaveKey('active_user_count');
    }
    
    // Clean up
    $pdo->exec("DELETE FROM services WHERE project_id >= 8100 AND project_id < 8200");
    $pdo->exec("DELETE FROM projects WHERE id >= 8100 AND id < 8200");
})->group('property', 'validation', 'validation-correctness');

test('validation fails with SUBSCRIPTION_EXPIRED when subscription is expired with capacity', function () {
    $faker = Faker\Factory::create();
    $pdo = getTestDatabase();
    $repository = new ServiceRepository($pdo);
    $validationService = new ValidationService($repository);
    
    // Clean up test data
    $pdo->exec("DELETE FROM services WHERE project_id >= 8200 AND project_id < 8300");
    $pdo->exec("DELETE FROM projects WHERE id >= 8200 AND id < 8300");
    
    // Ensure test client exists
    $pdo->exec("INSERT IGNORE INTO clients (id, name, contact_info) VALUES (1, 'Test Client', 'test@example.com')");
    
    // Create test projects for foreign key constraint
    for ($i = 0; $i < 100; $i++) {
        $pdo->exec("INSERT INTO projects (id, client_id, name, description) VALUES (" . (8200 + $i) . ", 1, 'Test Project $i', 'Test')");
    }
    
    // Run 100 iterations
    for ($i = 0; $i < 100; $i++) {
        // Generate service with available capacity
        $userLimit = $faker->numberBetween(10, 1000);
        $activeUserCount = $faker->numberBetween(0, $userLimit - 1);
        
        // Generate expired subscription (current date after end_date)
        $startDate = $faker->dateTimeBetween('-2 years', '-1 year');
        $endDate = $faker->dateTimeBetween($startDate, '-6 months');
        
        // Store formatted strings before modifying dates
        $startDateStr = $startDate->format('Y-m-d');
        $endDateStr = $endDate->format('Y-m-d');
        
        // Generate current date after end date (clone to avoid mutation)
        $currentDate = $faker->dateTimeBetween((clone $endDate)->modify('+1 day'), 'now');
        $currentDateStr = $currentDate->format('Y-m-d');
        
        // Create service in database
        $serviceData = [
            'project_id' => 8200 + $i,
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
        
        // Validate user creation
        $result = $validationService->validateUserCreation($serviceId, $currentDateStr);
        
        // Verify validation fails with correct error code
        expect($result['success'])->toBeFalse(
            "Validation should fail when subscription is expired (current: $currentDateStr > end: $endDateStr) " .
            "even with available capacity ($activeUserCount < $userLimit)"
        );
        
        expect($result)->toHaveKey('error_code');
        expect($result['error_code'])->toBe('SUBSCRIPTION_EXPIRED',
            "Error code should be SUBSCRIPTION_EXPIRED when subscription is expired"
        );
        
        expect($result)->toHaveKey('context');
        expect($result['context'])->toHaveKey('start_date');
        expect($result['context'])->toHaveKey('end_date');
    }
    
    // Clean up
    $pdo->exec("DELETE FROM services WHERE project_id >= 8200 AND project_id < 8300");
    $pdo->exec("DELETE FROM projects WHERE id >= 8200 AND id < 8300");
})->group('property', 'validation', 'validation-correctness');

test('validation fails when subscription is not yet active', function () {
    $faker = Faker\Factory::create();
    $pdo = getTestDatabase();
    $repository = new ServiceRepository($pdo);
    $validationService = new ValidationService($repository);
    
    // Clean up test data
    $pdo->exec("DELETE FROM services WHERE project_id >= 8300 AND project_id < 8400");
    $pdo->exec("DELETE FROM projects WHERE id >= 8300 AND id < 8400");
    
    // Ensure test client exists
    $pdo->exec("INSERT IGNORE INTO clients (id, name, contact_info) VALUES (1, 'Test Client', 'test@example.com')");
    
    // Create test projects for foreign key constraint
    for ($i = 0; $i < 100; $i++) {
        $pdo->exec("INSERT INTO projects (id, client_id, name, description) VALUES (" . (8300 + $i) . ", 1, 'Test Project $i', 'Test')");
    }
    
    // Run 100 iterations
    for ($i = 0; $i < 100; $i++) {
        // Generate service with available capacity
        $userLimit = $faker->numberBetween(10, 1000);
        $activeUserCount = $faker->numberBetween(0, $userLimit - 1);
        
        // Generate future subscription (current date before start_date)
        $startDate = $faker->dateTimeBetween('+1 month', '+1 year');
        $endDate = $faker->dateTimeBetween($startDate, '+2 years');
        $currentDate = $faker->dateTimeBetween('-1 year', $startDate->modify('-1 day'));
        
        $startDateStr = $startDate->format('Y-m-d');
        $endDateStr = $endDate->format('Y-m-d');
        $currentDateStr = $currentDate->format('Y-m-d');
        
        // Create service in database
        $serviceData = [
            'project_id' => 8300 + $i,
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
        
        // Validate user creation
        $result = $validationService->validateUserCreation($serviceId, $currentDateStr);
        
        // Verify validation fails
        expect($result['success'])->toBeFalse(
            "Validation should fail when subscription is not yet active (current: $currentDateStr < start: $startDateStr)"
        );
        
        expect($result)->toHaveKey('error_code');
        expect($result['error_code'])->toBe('SUBSCRIPTION_EXPIRED',
            "Error code should be SUBSCRIPTION_EXPIRED when subscription is not yet active"
        );
    }
    
    // Clean up
    $pdo->exec("DELETE FROM services WHERE project_id >= 8300 AND project_id < 8400");
    $pdo->exec("DELETE FROM projects WHERE id >= 8300 AND id < 8400");
})->group('property', 'validation', 'validation-correctness');

test('validation fails when both at capacity and subscription expired', function () {
    $faker = Faker\Factory::create();
    $pdo = getTestDatabase();
    $repository = new ServiceRepository($pdo);
    $validationService = new ValidationService($repository);
    
    // Clean up test data
    $pdo->exec("DELETE FROM services WHERE project_id >= 8400 AND project_id < 8500");
    $pdo->exec("DELETE FROM projects WHERE id >= 8400 AND id < 8500");
    
    // Ensure test client exists
    $pdo->exec("INSERT IGNORE INTO clients (id, name, contact_info) VALUES (1, 'Test Client', 'test@example.com')");
    
    // Create test projects for foreign key constraint
    for ($i = 0; $i < 100; $i++) {
        $pdo->exec("INSERT INTO projects (id, client_id, name, description) VALUES (" . (8400 + $i) . ", 1, 'Test Project $i', 'Test')");
    }
    
    // Run 100 iterations
    for ($i = 0; $i < 100; $i++) {
        // Generate service at capacity
        $userLimit = $faker->numberBetween(10, 1000);
        $activeUserCount = $faker->numberBetween($userLimit, $userLimit + 100);
        
        // Generate expired subscription
        $startDate = $faker->dateTimeBetween('-2 years', '-1 year');
        $endDate = $faker->dateTimeBetween($startDate, '-6 months');
        $currentDate = $faker->dateTimeBetween($endDate->modify('+1 day'), 'now');
        
        $startDateStr = $startDate->format('Y-m-d');
        $endDateStr = $endDate->format('Y-m-d');
        $currentDateStr = $currentDate->format('Y-m-d');
        
        // Create service in database
        $serviceData = [
            'project_id' => 8400 + $i,
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
        
        // Validate user creation
        $result = $validationService->validateUserCreation($serviceId, $currentDateStr);
        
        // Verify validation fails (should fail for subscription expired first)
        expect($result['success'])->toBeFalse(
            "Validation should fail when both at capacity and subscription expired"
        );
        
        expect($result)->toHaveKey('error_code');
        // The error code should be SUBSCRIPTION_EXPIRED since that's checked first in ValidationService
        expect($result['error_code'])->toBeIn(['SUBSCRIPTION_EXPIRED', 'USER_LIMIT_EXCEEDED'],
            "Error code should be either SUBSCRIPTION_EXPIRED or USER_LIMIT_EXCEEDED"
        );
    }
    
    // Clean up
    $pdo->exec("DELETE FROM services WHERE project_id >= 8400 AND project_id < 8500");
    $pdo->exec("DELETE FROM projects WHERE id >= 8400 AND id < 8500");
})->group('property', 'validation', 'validation-correctness');

test('validation succeeds at boundary: one slot available with active subscription', function () {
    $faker = Faker\Factory::create();
    $pdo = getTestDatabase();
    $repository = new ServiceRepository($pdo);
    $validationService = new ValidationService($repository);
    
    // Clean up test data
    $pdo->exec("DELETE FROM services WHERE project_id >= 8500 AND project_id < 8600");
    $pdo->exec("DELETE FROM projects WHERE id >= 8500 AND id < 8600");
    
    // Ensure test client exists
    $pdo->exec("INSERT IGNORE INTO clients (id, name, contact_info) VALUES (1, 'Test Client', 'test@example.com')");
    
    // Create test projects for foreign key constraint
    for ($i = 0; $i < 100; $i++) {
        $pdo->exec("INSERT INTO projects (id, client_id, name, description) VALUES (" . (8500 + $i) . ", 1, 'Test Project $i', 'Test')");
    }
    
    // Run 100 iterations
    for ($i = 0; $i < 100; $i++) {
        // Generate service with exactly one slot available
        $userLimit = $faker->numberBetween(10, 1000);
        $activeUserCount = $userLimit - 1; // Exactly one slot available
        
        // Generate active subscription
        $startDate = $faker->dateTimeBetween('-1 year', '-1 day');
        $endDate = $faker->dateTimeBetween('+1 day', '+1 year');
        $currentDate = $faker->dateTimeBetween($startDate, $endDate);
        
        $startDateStr = $startDate->format('Y-m-d');
        $endDateStr = $endDate->format('Y-m-d');
        $currentDateStr = $currentDate->format('Y-m-d');
        
        // Create service in database
        $serviceData = [
            'project_id' => 8500 + $i,
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
        
        // Validate user creation
        $result = $validationService->validateUserCreation($serviceId, $currentDateStr);
        
        // Verify validation succeeds at boundary
        expect($result['success'])->toBeTrue(
            "Validation should succeed when exactly one slot is available ($activeUserCount = $userLimit - 1)"
        );
    }
    
    // Clean up
    $pdo->exec("DELETE FROM services WHERE project_id >= 8500 AND project_id < 8600");
    $pdo->exec("DELETE FROM projects WHERE id >= 8500 AND id < 8600");
})->group('property', 'validation', 'validation-correctness');

test('validation succeeds on subscription boundary dates', function () {
    $faker = Faker\Factory::create();
    $pdo = getTestDatabase();
    $repository = new ServiceRepository($pdo);
    $validationService = new ValidationService($repository);
    
    // Clean up test data
    $pdo->exec("DELETE FROM services WHERE project_id >= 8600 AND project_id < 8700");
    $pdo->exec("DELETE FROM projects WHERE id >= 8600 AND id < 8700");
    
    // Ensure test client exists
    $pdo->exec("INSERT IGNORE INTO clients (id, name, contact_info) VALUES (1, 'Test Client', 'test@example.com')");
    
    // Create test projects for foreign key constraint
    for ($i = 0; $i < 100; $i++) {
        $pdo->exec("INSERT INTO projects (id, client_id, name, description) VALUES (" . (8600 + $i) . ", 1, 'Test Project $i', 'Test')");
    }
    
    // Run 50 iterations for start date boundary
    for ($i = 0; $i < 50; $i++) {
        $userLimit = $faker->numberBetween(10, 1000);
        $activeUserCount = $faker->numberBetween(0, $userLimit - 1);
        
        $startDate = $faker->dateTimeBetween('-1 year', '+1 year');
        $endDate = $faker->dateTimeBetween($startDate, '+2 years');
        
        $startDateStr = $startDate->format('Y-m-d');
        $endDateStr = $endDate->format('Y-m-d');
        
        // Test on start date
        $serviceData = [
            'project_id' => 8600 + $i,
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
        
        // Validate on start date
        $result = $validationService->validateUserCreation($serviceId, $startDateStr);
        
        expect($result['success'])->toBeTrue(
            "Validation should succeed on start date boundary ($startDateStr)"
        );
    }
    
    // Run 50 iterations for end date boundary
    for ($i = 50; $i < 100; $i++) {
        $userLimit = $faker->numberBetween(10, 1000);
        $activeUserCount = $faker->numberBetween(0, $userLimit - 1);
        
        $startDate = $faker->dateTimeBetween('-1 year', '+1 year');
        $endDate = $faker->dateTimeBetween($startDate, '+2 years');
        
        $startDateStr = $startDate->format('Y-m-d');
        $endDateStr = $endDate->format('Y-m-d');
        
        // Test on end date
        $serviceData = [
            'project_id' => 8600 + $i,
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
        
        // Validate on end date
        $result = $validationService->validateUserCreation($serviceId, $endDateStr);
        
        expect($result['success'])->toBeTrue(
            "Validation should succeed on end date boundary ($endDateStr)"
        );
    }
    
    // Clean up
    $pdo->exec("DELETE FROM services WHERE project_id >= 8600 AND project_id < 8700");
    $pdo->exec("DELETE FROM projects WHERE id >= 8600 AND id < 8700");
})->group('property', 'validation', 'validation-correctness');

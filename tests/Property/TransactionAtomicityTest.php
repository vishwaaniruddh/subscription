<?php

use App\Services\UserService;
use App\Services\ValidationService;
use App\Repositories\UserRepository;
use App\Repositories\ServiceRepository;
use App\Models\User;
use App\Models\Service;

/**
 * Feature: subscription-management-module
 * Property 36: Transaction Atomicity
 * 
 * For any failed user registration operation, the active_user_count should remain 
 * unchanged and no user record should be created.
 * 
 * **Validates: Requirements 15.4**
 */

test('failed user registration due to user limit leaves no partial state', function () {
    $faker = Faker\Factory::create();
    $pdo = getTestDatabase();
    $serviceRepository = new ServiceRepository($pdo);
    $userRepository = new UserRepository($pdo);
    $validationService = new ValidationService($serviceRepository);
    $userService = new UserService($userRepository, $serviceRepository, $validationService);
    
    // Clean up test data
    $pdo->exec("DELETE FROM users WHERE service_id IN (SELECT id FROM services WHERE project_id >= 10000 AND project_id < 10100)");
    $pdo->exec("DELETE FROM services WHERE project_id >= 10000 AND project_id < 10100");
    $pdo->exec("DELETE FROM projects WHERE id >= 10000 AND id < 10100");
    
    // Ensure test client exists
    $pdo->exec("INSERT IGNORE INTO clients (id, name, contact_info) VALUES (1, 'Test Client', 'test@example.com')");
    
    // Create test projects
    for ($i = 0; $i < 100; $i++) {
        $pdo->exec("INSERT INTO projects (id, client_id, name, description) VALUES (" . (10000 + $i) . ", 1, 'Test Project $i', 'Test')");
    }
    
    // Run 100 iterations
    for ($i = 0; $i < 100; $i++) {
        // Create a service at capacity (user_limit = active_user_count)
        $userLimit = $faker->numberBetween(5, 50);
        $activeUserCount = $userLimit; // At capacity - registration should fail
        $startDate = $faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d');
        $endDate = $faker->dateTimeBetween('+1 day', '+1 year')->format('Y-m-d');
        
        $service = new Service(
            10000 + $i,
            $faker->randomElement(['web', 'mobile', 'other']),
            $userLimit,
            $startDate,
            $endDate,
            $activeUserCount
        );
        
        $service = $serviceRepository->create($service);
        
        // Record state before failed registration attempt
        $countBefore = $serviceRepository->findById($service->id)->activeUserCount;
        $userCountBefore = $userRepository->countActiveByServiceId($service->id);
        
        // Attempt to register a user (should fail due to limit)
        $userIdentifier = $faker->uuid();
        $userData = [
            'service_id' => $service->id,
            'user_identifier' => $userIdentifier,
            'status' => 'active'
        ];
        
        $exceptionThrown = false;
        try {
            $userService->registerUser($userData);
        } catch (Exception $e) {
            $exceptionThrown = true;
        }
        
        // Verify the operation failed
        expect($exceptionThrown)->toBeTrue(
            "User registration should fail when service is at capacity (Service ID: {$service->id}, Limit: $userLimit)"
        );
        
        // Verify: active_user_count should remain unchanged
        $countAfter = $serviceRepository->findById($service->id)->activeUserCount;
        expect($countAfter)->toBe($countBefore,
            "Active user count should remain unchanged after failed registration (before: $countBefore, after: $countAfter)"
        );
        
        // Verify: no user record should be created
        $userCountAfter = $userRepository->countActiveByServiceId($service->id);
        expect($userCountAfter)->toBe($userCountBefore,
            "No user record should be created after failed registration (before: $userCountBefore, after: $userCountAfter)"
        );
        
        // Verify: the specific user identifier should not exist in database
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE service_id = :service_id AND user_identifier = :user_identifier");
        $stmt->execute(['service_id' => $service->id, 'user_identifier' => $userIdentifier]);
        $userExists = (int)$stmt->fetchColumn();
        
        expect($userExists)->toBe(0,
            "No user record with identifier '$userIdentifier' should exist after failed registration"
        );
    }
    
    // Clean up
    $pdo->exec("DELETE FROM users WHERE service_id IN (SELECT id FROM services WHERE project_id >= 10000 AND project_id < 10100)");
    $pdo->exec("DELETE FROM services WHERE project_id >= 10000 AND project_id < 10100");
    $pdo->exec("DELETE FROM projects WHERE id >= 10000 AND id < 10100");
})->group('property', 'transaction-atomicity', 'data-integrity');

test('failed user registration due to expired subscription leaves no partial state', function () {
    $faker = Faker\Factory::create();
    $pdo = getTestDatabase();
    $serviceRepository = new ServiceRepository($pdo);
    $userRepository = new UserRepository($pdo);
    $validationService = new ValidationService($serviceRepository);
    $userService = new UserService($userRepository, $serviceRepository, $validationService);
    
    // Clean up test data
    $pdo->exec("DELETE FROM users WHERE service_id IN (SELECT id FROM services WHERE project_id >= 10100 AND project_id < 10200)");
    $pdo->exec("DELETE FROM services WHERE project_id >= 10100 AND project_id < 10200");
    $pdo->exec("DELETE FROM projects WHERE id >= 10100 AND id < 10200");
    
    // Ensure test client exists
    $pdo->exec("INSERT IGNORE INTO clients (id, name, contact_info) VALUES (1, 'Test Client', 'test@example.com')");
    
    // Create test projects
    for ($i = 0; $i < 100; $i++) {
        $pdo->exec("INSERT INTO projects (id, client_id, name, description) VALUES (" . (10100 + $i) . ", 1, 'Test Project $i', 'Test')");
    }
    
    // Run 100 iterations
    for ($i = 0; $i < 100; $i++) {
        // Create a service with expired subscription
        $userLimit = $faker->numberBetween(10, 100);
        $activeUserCount = $faker->numberBetween(0, $userLimit - 1); // Has capacity but expired
        $startDate = $faker->dateTimeBetween('-2 years', '-1 year')->format('Y-m-d');
        $endDate = $faker->dateTimeBetween('-1 year', '-1 day')->format('Y-m-d'); // Expired
        
        $service = new Service(
            10100 + $i,
            $faker->randomElement(['web', 'mobile', 'other']),
            $userLimit,
            $startDate,
            $endDate,
            $activeUserCount
        );
        
        $service = $serviceRepository->create($service);
        
        // Record state before failed registration attempt
        $countBefore = $serviceRepository->findById($service->id)->activeUserCount;
        $userCountBefore = $userRepository->countActiveByServiceId($service->id);
        
        // Attempt to register a user (should fail due to expired subscription)
        $userIdentifier = $faker->uuid();
        $userData = [
            'service_id' => $service->id,
            'user_identifier' => $userIdentifier,
            'status' => 'active'
        ];
        
        $exceptionThrown = false;
        try {
            $userService->registerUser($userData);
        } catch (Exception $e) {
            $exceptionThrown = true;
        }
        
        // Verify the operation failed
        expect($exceptionThrown)->toBeTrue(
            "User registration should fail when subscription is expired (Service ID: {$service->id}, End Date: $endDate)"
        );
        
        // Verify: active_user_count should remain unchanged
        $countAfter = $serviceRepository->findById($service->id)->activeUserCount;
        expect($countAfter)->toBe($countBefore,
            "Active user count should remain unchanged after failed registration (before: $countBefore, after: $countAfter)"
        );
        
        // Verify: no user record should be created
        $userCountAfter = $userRepository->countActiveByServiceId($service->id);
        expect($userCountAfter)->toBe($userCountBefore,
            "No user record should be created after failed registration (before: $userCountBefore, after: $userCountAfter)"
        );
        
        // Verify: the specific user identifier should not exist in database
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE service_id = :service_id AND user_identifier = :user_identifier");
        $stmt->execute(['service_id' => $service->id, 'user_identifier' => $userIdentifier]);
        $userExists = (int)$stmt->fetchColumn();
        
        expect($userExists)->toBe(0,
            "No user record with identifier '$userIdentifier' should exist after failed registration"
        );
    }
    
    // Clean up
    $pdo->exec("DELETE FROM users WHERE service_id IN (SELECT id FROM services WHERE project_id >= 10100 AND project_id < 10200)");
    $pdo->exec("DELETE FROM services WHERE project_id >= 10100 AND project_id < 10200");
    $pdo->exec("DELETE FROM projects WHERE id >= 10100 AND id < 10200");
})->group('property', 'transaction-atomicity', 'data-integrity');

test('failed user registration due to non-existent service leaves no partial state', function () {
    $faker = Faker\Factory::create();
    $pdo = getTestDatabase();
    $serviceRepository = new ServiceRepository($pdo);
    $userRepository = new UserRepository($pdo);
    $validationService = new ValidationService($serviceRepository);
    $userService = new UserService($userRepository, $serviceRepository, $validationService);
    
    // Clean up test data
    $pdo->exec("DELETE FROM users WHERE service_id IN (SELECT id FROM services WHERE project_id >= 10200 AND project_id < 10300)");
    $pdo->exec("DELETE FROM services WHERE project_id >= 10200 AND project_id < 10300");
    $pdo->exec("DELETE FROM projects WHERE id >= 10200 AND id < 10300");
    
    // Ensure test client exists
    $pdo->exec("INSERT IGNORE INTO clients (id, name, contact_info) VALUES (1, 'Test Client', 'test@example.com')");
    
    // Create test projects
    for ($i = 0; $i < 100; $i++) {
        $pdo->exec("INSERT INTO projects (id, client_id, name, description) VALUES (" . (10200 + $i) . ", 1, 'Test Project $i', 'Test')");
    }
    
    // Run 100 iterations
    for ($i = 0; $i < 100; $i++) {
        // Use a non-existent service ID
        $nonExistentServiceId = 999999 + $i;
        
        // Verify service doesn't exist
        $service = $serviceRepository->findById($nonExistentServiceId);
        expect($service)->toBeNull(
            "Service ID $nonExistentServiceId should not exist"
        );
        
        // Attempt to register a user for non-existent service
        $userIdentifier = $faker->uuid();
        $userData = [
            'service_id' => $nonExistentServiceId,
            'user_identifier' => $userIdentifier,
            'status' => 'active'
        ];
        
        $exceptionThrown = false;
        try {
            $userService->registerUser($userData);
        } catch (Exception $e) {
            $exceptionThrown = true;
        }
        
        // Verify the operation failed
        expect($exceptionThrown)->toBeTrue(
            "User registration should fail for non-existent service (Service ID: $nonExistentServiceId)"
        );
        
        // Verify: no user record should be created
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE service_id = :service_id AND user_identifier = :user_identifier");
        $stmt->execute(['service_id' => $nonExistentServiceId, 'user_identifier' => $userIdentifier]);
        $userExists = (int)$stmt->fetchColumn();
        
        expect($userExists)->toBe(0,
            "No user record with identifier '$userIdentifier' should exist after failed registration for non-existent service"
        );
    }
    
    // Clean up
    $pdo->exec("DELETE FROM users WHERE service_id IN (SELECT id FROM services WHERE project_id >= 10200 AND project_id < 10300)");
    $pdo->exec("DELETE FROM services WHERE project_id >= 10200 AND project_id < 10300");
    $pdo->exec("DELETE FROM projects WHERE id >= 10200 AND id < 10300");
})->group('property', 'transaction-atomicity', 'data-integrity');

test('transaction rollback on validation failure maintains database consistency', function () {
    $faker = Faker\Factory::create();
    $pdo = getTestDatabase();
    $serviceRepository = new ServiceRepository($pdo);
    $userRepository = new UserRepository($pdo);
    $validationService = new ValidationService($serviceRepository);
    $userService = new UserService($userRepository, $serviceRepository, $validationService);
    
    // Clean up test data
    $pdo->exec("DELETE FROM users WHERE service_id IN (SELECT id FROM services WHERE project_id >= 10300 AND project_id < 10400)");
    $pdo->exec("DELETE FROM services WHERE project_id >= 10300 AND project_id < 10400");
    $pdo->exec("DELETE FROM projects WHERE id >= 10300 AND id < 10400");
    
    // Ensure test client exists
    $pdo->exec("INSERT IGNORE INTO clients (id, name, contact_info) VALUES (1, 'Test Client', 'test@example.com')");
    
    // Create test projects
    for ($i = 0; $i < 100; $i++) {
        $pdo->exec("INSERT INTO projects (id, client_id, name, description) VALUES (" . (10300 + $i) . ", 1, 'Test Project $i', 'Test')");
    }
    
    // Run 100 iterations
    for ($i = 0; $i < 100; $i++) {
        // Create a service with various failure scenarios (only valid scenarios)
        $failureScenario = $faker->randomElement(['at_capacity', 'expired']);
        
        $userLimit = $faker->numberBetween(5, 50);
        
        if ($failureScenario === 'at_capacity') {
            $activeUserCount = $userLimit;
            $startDate = $faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d');
            $endDate = $faker->dateTimeBetween('+1 day', '+1 year')->format('Y-m-d');
        } else { // expired
            $activeUserCount = $faker->numberBetween(0, $userLimit - 1);
            $startDate = $faker->dateTimeBetween('-2 years', '-1 year')->format('Y-m-d');
            $endDate = $faker->dateTimeBetween('-1 year', '-1 day')->format('Y-m-d');
        }
        
        $service = new Service(
            10300 + $i,
            $faker->randomElement(['web', 'mobile', 'other']),
            $userLimit,
            $startDate,
            $endDate,
            0 // Start with 0, we'll add users properly
        );
        
        $service = $serviceRepository->create($service);
        
        // Create actual user records to match the active_user_count
        for ($j = 0; $j < $activeUserCount; $j++) {
            $user = new User(
                $service->id,
                $faker->uuid(),
                'active'
            );
            $userRepository->create($user);
        }
        
        // Update service to reflect actual user count
        $stmt = $pdo->prepare("UPDATE services SET active_user_count = :count WHERE id = :id");
        $stmt->execute(['count' => $activeUserCount, 'id' => $service->id]);
        
        // Record complete state before failed registration
        $countBefore = $serviceRepository->findById($service->id)->activeUserCount;
        $userCountBefore = $userRepository->countActiveByServiceId($service->id);
        $totalUsersCountBefore = count($userRepository->findByServiceId($service->id));
        
        // Attempt to register a user (should fail)
        $userIdentifier = $faker->uuid();
        $userData = [
            'service_id' => $service->id,
            'user_identifier' => $userIdentifier,
            'status' => 'active'
        ];
        
        $exceptionThrown = false;
        try {
            $userService->registerUser($userData);
        } catch (Exception $e) {
            $exceptionThrown = true;
        }
        
        // Verify the operation failed
        expect($exceptionThrown)->toBeTrue(
            "User registration should fail for scenario '$failureScenario' (Service ID: {$service->id})"
        );
        
        // Verify: complete database consistency maintained
        $countAfter = $serviceRepository->findById($service->id)->activeUserCount;
        $userCountAfter = $userRepository->countActiveByServiceId($service->id);
        $totalUsersCountAfter = count($userRepository->findByServiceId($service->id));
        
        expect($countAfter)->toBe($countBefore,
            "Service active_user_count should remain unchanged (scenario: $failureScenario)"
        );
        
        expect($userCountAfter)->toBe($userCountBefore,
            "Active user count in database should remain unchanged (scenario: $failureScenario)"
        );
        
        expect($totalUsersCountAfter)->toBe($totalUsersCountBefore,
            "Total user count (active + deactivated) should remain unchanged (scenario: $failureScenario)"
        );
        
        // Verify: the attempted user does not exist in any state
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE service_id = :service_id AND user_identifier = :user_identifier");
        $stmt->execute(['service_id' => $service->id, 'user_identifier' => $userIdentifier]);
        $userExists = (int)$stmt->fetchColumn();
        
        expect($userExists)->toBe(0,
            "No user record should exist for failed registration (scenario: $failureScenario)"
        );
        
        // Verify: service active_user_count matches actual database count
        $actualActiveCount = $userRepository->countActiveByServiceId($service->id);
        $serviceActiveCount = $serviceRepository->findById($service->id)->activeUserCount;
        
        expect($serviceActiveCount)->toBe($actualActiveCount,
            "Service active_user_count should match actual database count after failed transaction"
        );
    }
    
    // Clean up
    $pdo->exec("DELETE FROM users WHERE service_id IN (SELECT id FROM services WHERE project_id >= 10300 AND project_id < 10400)");
    $pdo->exec("DELETE FROM services WHERE project_id >= 10300 AND project_id < 10400");
    $pdo->exec("DELETE FROM projects WHERE id >= 10300 AND id < 10400");
})->group('property', 'transaction-atomicity', 'data-integrity');

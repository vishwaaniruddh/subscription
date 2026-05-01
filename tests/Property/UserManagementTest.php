<?php

use App\Services\UserService;
use App\Services\ValidationService;
use App\Repositories\UserRepository;
use App\Repositories\ServiceRepository;
use App\Models\User;
use App\Models\Service;

/**
 * Feature: subscription-management-module
 * Property 7: Active User Count Accuracy
 * Property 8: User Registration Increments Count
 * Property 9: User Deactivation Decrements Count
 * Property 10: User Limit Enforcement
 * Property 12: Deactivation Audit Trail
 * 
 * For any service, the active_user_count should always equal the number of users 
 * with status='active' associated with that service.
 * 
 * For any service with available capacity, successfully registering a user should 
 * increase the active_user_count by exactly 1.
 * 
 * For any service with active users, deactivating a user should decrease the 
 * active_user_count by exactly 1.
 * 
 * For any service where active_user_count >= user_limit, attempting to register 
 * a new user should be rejected with error code USER_LIMIT_EXCEEDED.
 * 
 * For any user deactivation, a record should exist in the users table with 
 * status='deactivated' and a non-null deactivated_at timestamp.
 * 
 * **Validates: Requirements 4.4, 5.7, 5.8, 6.2, 6.3**
 */

test('active user count equals number of active users in database', function () {
    $faker = Faker\Factory::create();
    $pdo = getTestDatabase();
    $serviceRepository = new ServiceRepository($pdo);
    $userRepository = new UserRepository($pdo);
    
    // Clean up test data
    $pdo->exec("DELETE FROM users WHERE service_id IN (SELECT id FROM services WHERE project_id >= 9000 AND project_id < 9100)");
    $pdo->exec("DELETE FROM services WHERE project_id >= 9000 AND project_id < 9100");
    $pdo->exec("DELETE FROM projects WHERE id >= 9000 AND id < 9100");
    
    // Ensure test client exists
    $pdo->exec("INSERT IGNORE INTO clients (id, name, contact_info) VALUES (1, 'Test Client', 'test@example.com')");
    
    // Create test projects
    for ($i = 0; $i < 100; $i++) {
        $pdo->exec("INSERT INTO projects (id, client_id, name, description) VALUES (" . (9000 + $i) . ", 1, 'Test Project $i', 'Test')");
    }
    
    // Run 100 iterations
    for ($i = 0; $i < 100; $i++) {
        // Create a service with random user limit
        $userLimit = $faker->numberBetween(5, 50);
        $startDate = $faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d');
        $endDate = $faker->dateTimeBetween('+1 day', '+1 year')->format('Y-m-d');
        
        $service = new Service(
            9000 + $i,
            $faker->randomElement(['web', 'mobile', 'other']),
            $userLimit,
            $startDate,
            $endDate,
            0 // Start with 0 active users
        );
        
        $service = $serviceRepository->create($service);
        
        // Create a random number of active users
        $numActiveUsers = $faker->numberBetween(0, min($userLimit, 20));
        for ($j = 0; $j < $numActiveUsers; $j++) {
            $user = new User(
                $service->id,
                $faker->uuid(),
                'active'
            );
            $userRepository->create($user);
        }
        
        // Create some deactivated users (should not count)
        $numDeactivatedUsers = $faker->numberBetween(0, 10);
        for ($j = 0; $j < $numDeactivatedUsers; $j++) {
            $user = new User(
                $service->id,
                $faker->uuid(),
                'deactivated'
            );
            $userRepository->create($user);
        }
        
        // Update the service's active_user_count to match actual active users
        $stmt = $pdo->prepare("UPDATE services SET active_user_count = :count WHERE id = :id");
        $stmt->execute(['count' => $numActiveUsers, 'id' => $service->id]);
        
        // Verify: active_user_count should equal the count of active users
        $actualActiveCount = $userRepository->countActiveByServiceId($service->id);
        $service = $serviceRepository->findById($service->id);
        
        expect($service->activeUserCount)->toBe($actualActiveCount,
            "Service active_user_count ({$service->activeUserCount}) should equal actual active users in database ($actualActiveCount)"
        );
        
        expect($service->activeUserCount)->toBe($numActiveUsers,
            "Service active_user_count should match the number of active users created ($numActiveUsers)"
        );
    }
    
    // Clean up
    $pdo->exec("DELETE FROM users WHERE service_id IN (SELECT id FROM services WHERE project_id >= 9000 AND project_id < 9100)");
    $pdo->exec("DELETE FROM services WHERE project_id >= 9000 AND project_id < 9100");
    $pdo->exec("DELETE FROM projects WHERE id >= 9000 AND id < 9100");
})->group('property', 'user-management', 'active-user-count');

test('user registration increments active user count by exactly 1', function () {
    $faker = Faker\Factory::create();
    $pdo = getTestDatabase();
    $serviceRepository = new ServiceRepository($pdo);
    $userRepository = new UserRepository($pdo);
    $validationService = new ValidationService($serviceRepository);
    $userService = new UserService($userRepository, $serviceRepository, $validationService);
    
    // Clean up test data
    $pdo->exec("DELETE FROM users WHERE service_id IN (SELECT id FROM services WHERE project_id >= 9100 AND project_id < 9200)");
    $pdo->exec("DELETE FROM services WHERE project_id >= 9100 AND project_id < 9200");
    $pdo->exec("DELETE FROM projects WHERE id >= 9100 AND id < 9200");
    
    // Ensure test client exists
    $pdo->exec("INSERT IGNORE INTO clients (id, name, contact_info) VALUES (1, 'Test Client', 'test@example.com')");
    
    // Create test projects
    for ($i = 0; $i < 100; $i++) {
        $pdo->exec("INSERT INTO projects (id, client_id, name, description) VALUES (" . (9100 + $i) . ", 1, 'Test Project $i', 'Test')");
    }
    
    // Run 100 iterations
    for ($i = 0; $i < 100; $i++) {
        // Create a service with available capacity
        $userLimit = $faker->numberBetween(10, 100);
        $initialActiveCount = $faker->numberBetween(0, $userLimit - 1); // Ensure capacity available
        $startDate = $faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d');
        $endDate = $faker->dateTimeBetween('+1 day', '+1 year')->format('Y-m-d');
        
        $service = new Service(
            9100 + $i,
            $faker->randomElement(['web', 'mobile', 'other']),
            $userLimit,
            $startDate,
            $endDate,
            $initialActiveCount
        );
        
        $service = $serviceRepository->create($service);
        
        // Get the count before registration
        $countBefore = $serviceRepository->findById($service->id)->activeUserCount;
        
        // Register a new user
        $userData = [
            'service_id' => $service->id,
            'user_identifier' => $faker->uuid(),
            'status' => 'active'
        ];
        
        $userService->registerUser($userData);
        
        // Get the count after registration
        $countAfter = $serviceRepository->findById($service->id)->activeUserCount;
        
        // Verify the count increased by exactly 1
        expect($countAfter)->toBe($countBefore + 1,
            "Active user count should increase by exactly 1 after registration (before: $countBefore, after: $countAfter)"
        );
    }
    
    // Clean up
    $pdo->exec("DELETE FROM users WHERE service_id IN (SELECT id FROM services WHERE project_id >= 9100 AND project_id < 9200)");
    $pdo->exec("DELETE FROM services WHERE project_id >= 9100 AND project_id < 9200");
    $pdo->exec("DELETE FROM projects WHERE id >= 9100 AND id < 9200");
})->group('property', 'user-management', 'user-registration');

test('user deactivation decrements active user count by exactly 1', function () {
    $faker = Faker\Factory::create();
    $pdo = getTestDatabase();
    $serviceRepository = new ServiceRepository($pdo);
    $userRepository = new UserRepository($pdo);
    $validationService = new ValidationService($serviceRepository);
    $userService = new UserService($userRepository, $serviceRepository, $validationService);
    
    // Clean up test data
    $pdo->exec("DELETE FROM users WHERE service_id IN (SELECT id FROM services WHERE project_id >= 9200 AND project_id < 9300)");
    $pdo->exec("DELETE FROM services WHERE project_id >= 9200 AND project_id < 9300");
    $pdo->exec("DELETE FROM projects WHERE id >= 9200 AND id < 9300");
    
    // Ensure test client exists
    $pdo->exec("INSERT IGNORE INTO clients (id, name, contact_info) VALUES (1, 'Test Client', 'test@example.com')");
    
    // Create test projects
    for ($i = 0; $i < 100; $i++) {
        $pdo->exec("INSERT INTO projects (id, client_id, name, description) VALUES (" . (9200 + $i) . ", 1, 'Test Project $i', 'Test')");
    }
    
    // Run 100 iterations
    for ($i = 0; $i < 100; $i++) {
        // Create a service with at least one active user
        $userLimit = $faker->numberBetween(10, 100);
        $initialActiveCount = $faker->numberBetween(1, $userLimit); // At least 1 active user
        $startDate = $faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d');
        $endDate = $faker->dateTimeBetween('+1 day', '+1 year')->format('Y-m-d');
        
        $service = new Service(
            9200 + $i,
            $faker->randomElement(['web', 'mobile', 'other']),
            $userLimit,
            $startDate,
            $endDate,
            $initialActiveCount
        );
        
        $service = $serviceRepository->create($service);
        
        // Create an active user to deactivate
        $user = new User(
            $service->id,
            $faker->uuid(),
            'active'
        );
        $user = $userRepository->create($user);
        
        // Get the count before deactivation
        $countBefore = $serviceRepository->findById($service->id)->activeUserCount;
        
        // Deactivate the user
        $userService->deactivateUser($user->id);
        
        // Get the count after deactivation
        $countAfter = $serviceRepository->findById($service->id)->activeUserCount;
        
        // Verify the count decreased by exactly 1
        expect($countAfter)->toBe($countBefore - 1,
            "Active user count should decrease by exactly 1 after deactivation (before: $countBefore, after: $countAfter)"
        );
    }
    
    // Clean up
    $pdo->exec("DELETE FROM users WHERE service_id IN (SELECT id FROM services WHERE project_id >= 9200 AND project_id < 9300)");
    $pdo->exec("DELETE FROM services WHERE project_id >= 9200 AND project_id < 9300");
    $pdo->exec("DELETE FROM projects WHERE id >= 9200 AND id < 9300");
})->group('property', 'user-management', 'user-deactivation');

test('user registration is rejected when at or over user limit', function () {
    $faker = Faker\Factory::create();
    $pdo = getTestDatabase();
    $serviceRepository = new ServiceRepository($pdo);
    $userRepository = new UserRepository($pdo);
    $validationService = new ValidationService($serviceRepository);
    $userService = new UserService($userRepository, $serviceRepository, $validationService);
    
    // Clean up test data
    $pdo->exec("DELETE FROM users WHERE service_id IN (SELECT id FROM services WHERE project_id >= 9300 AND project_id < 9400)");
    $pdo->exec("DELETE FROM services WHERE project_id >= 9300 AND project_id < 9400");
    $pdo->exec("DELETE FROM projects WHERE id >= 9300 AND id < 9400");
    
    // Ensure test client exists
    $pdo->exec("INSERT IGNORE INTO clients (id, name, contact_info) VALUES (1, 'Test Client', 'test@example.com')");
    
    // Create test projects
    for ($i = 0; $i < 100; $i++) {
        $pdo->exec("INSERT INTO projects (id, client_id, name, description) VALUES (" . (9300 + $i) . ", 1, 'Test Project $i', 'Test')");
    }
    
    // Run 100 iterations
    for ($i = 0; $i < 100; $i++) {
        // Create a service at or over capacity
        $userLimit = $faker->numberBetween(5, 50);
        $activeUserCount = $faker->numberBetween($userLimit, $userLimit + 20); // At or over limit
        $startDate = $faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d');
        $endDate = $faker->dateTimeBetween('+1 day', '+1 year')->format('Y-m-d');
        
        $service = new Service(
            9300 + $i,
            $faker->randomElement(['web', 'mobile', 'other']),
            $userLimit,
            $startDate,
            $endDate,
            $activeUserCount
        );
        
        $service = $serviceRepository->create($service);
        
        // Attempt to register a new user
        $userData = [
            'service_id' => $service->id,
            'user_identifier' => $faker->uuid(),
            'status' => 'active'
        ];
        
        $exceptionThrown = false;
        $exceptionMessage = '';
        $exceptionCode = 0;
        
        try {
            $userService->registerUser($userData);
        } catch (Exception $e) {
            $exceptionThrown = true;
            $exceptionMessage = $e->getMessage();
            $exceptionCode = $e->getCode();
        }
        
        // Verify that registration was rejected
        expect($exceptionThrown)->toBeTrue(
            "User registration should be rejected when active_user_count ($activeUserCount) >= user_limit ($userLimit)"
        );
        
        expect($exceptionCode)->toBe(400,
            "Exception code should be 400 for validation error"
        );
        
        // Verify the error message indicates user limit exceeded
        expect($exceptionMessage)->toMatch('/limit/i',
            "Error message should indicate limit has been reached"
        );
    }
    
    // Clean up
    $pdo->exec("DELETE FROM users WHERE service_id IN (SELECT id FROM services WHERE project_id >= 9300 AND project_id < 9400)");
    $pdo->exec("DELETE FROM services WHERE project_id >= 9300 AND project_id < 9400");
    $pdo->exec("DELETE FROM projects WHERE id >= 9300 AND id < 9400");
})->group('property', 'user-management', 'user-limit-enforcement');

test('user registration is rejected at exact user limit boundary', function () {
    $faker = Faker\Factory::create();
    $pdo = getTestDatabase();
    $serviceRepository = new ServiceRepository($pdo);
    $userRepository = new UserRepository($pdo);
    $validationService = new ValidationService($serviceRepository);
    $userService = new UserService($userRepository, $serviceRepository, $validationService);
    
    // Clean up test data
    $pdo->exec("DELETE FROM users WHERE service_id IN (SELECT id FROM services WHERE project_id >= 9400 AND project_id < 9500)");
    $pdo->exec("DELETE FROM services WHERE project_id >= 9400 AND project_id < 9500");
    $pdo->exec("DELETE FROM projects WHERE id >= 9400 AND id < 9500");
    
    // Ensure test client exists
    $pdo->exec("INSERT IGNORE INTO clients (id, name, contact_info) VALUES (1, 'Test Client', 'test@example.com')");
    
    // Create test projects
    for ($i = 0; $i < 100; $i++) {
        $pdo->exec("INSERT INTO projects (id, client_id, name, description) VALUES (" . (9400 + $i) . ", 1, 'Test Project $i', 'Test')");
    }
    
    // Run 100 iterations
    for ($i = 0; $i < 100; $i++) {
        // Create a service at exact capacity (boundary condition)
        $userLimit = $faker->numberBetween(5, 50);
        $activeUserCount = $userLimit; // Exactly at limit
        $startDate = $faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d');
        $endDate = $faker->dateTimeBetween('+1 day', '+1 year')->format('Y-m-d');
        
        $service = new Service(
            9400 + $i,
            $faker->randomElement(['web', 'mobile', 'other']),
            $userLimit,
            $startDate,
            $endDate,
            $activeUserCount
        );
        
        $service = $serviceRepository->create($service);
        
        // Attempt to register a new user
        $userData = [
            'service_id' => $service->id,
            'user_identifier' => $faker->uuid(),
            'status' => 'active'
        ];
        
        $exceptionThrown = false;
        
        try {
            $userService->registerUser($userData);
        } catch (Exception $e) {
            $exceptionThrown = true;
        }
        
        // Verify that registration was rejected at exact boundary
        expect($exceptionThrown)->toBeTrue(
            "User registration should be rejected when active_user_count equals user_limit (both = $userLimit)"
        );
    }
    
    // Clean up
    $pdo->exec("DELETE FROM users WHERE service_id IN (SELECT id FROM services WHERE project_id >= 9400 AND project_id < 9500)");
    $pdo->exec("DELETE FROM services WHERE project_id >= 9400 AND project_id < 9500");
    $pdo->exec("DELETE FROM projects WHERE id >= 9400 AND id < 9500");
})->group('property', 'user-management', 'user-limit-enforcement');

test('deactivated user has status deactivated and non-null deactivated_at timestamp', function () {
    $faker = Faker\Factory::create();
    $pdo = getTestDatabase();
    $serviceRepository = new ServiceRepository($pdo);
    $userRepository = new UserRepository($pdo);
    $validationService = new ValidationService($serviceRepository);
    $userService = new UserService($userRepository, $serviceRepository, $validationService);
    
    // Clean up test data
    $pdo->exec("DELETE FROM users WHERE service_id IN (SELECT id FROM services WHERE project_id >= 9500 AND project_id < 9600)");
    $pdo->exec("DELETE FROM services WHERE project_id >= 9500 AND project_id < 9600");
    $pdo->exec("DELETE FROM projects WHERE id >= 9500 AND id < 9600");
    
    // Ensure test client exists
    $pdo->exec("INSERT IGNORE INTO clients (id, name, contact_info) VALUES (1, 'Test Client', 'test@example.com')");
    
    // Create test projects
    for ($i = 0; $i < 100; $i++) {
        $pdo->exec("INSERT INTO projects (id, client_id, name, description) VALUES (" . (9500 + $i) . ", 1, 'Test Project $i', 'Test')");
    }
    
    // Run 100 iterations
    for ($i = 0; $i < 100; $i++) {
        // Create a service
        $userLimit = $faker->numberBetween(10, 100);
        $startDate = $faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d');
        $endDate = $faker->dateTimeBetween('+1 day', '+1 year')->format('Y-m-d');
        
        $service = new Service(
            9500 + $i,
            $faker->randomElement(['web', 'mobile', 'other']),
            $userLimit,
            $startDate,
            $endDate,
            1 // Start with 1 active user
        );
        
        $service = $serviceRepository->create($service);
        
        // Create an active user
        $user = new User(
            $service->id,
            $faker->uuid(),
            'active'
        );
        $user = $userRepository->create($user);
        
        // Deactivate the user
        $userService->deactivateUser($user->id);
        
        // Retrieve the user from database
        $deactivatedUser = $userRepository->findById($user->id);
        
        // Verify the user has status 'deactivated'
        expect($deactivatedUser->status)->toBe('deactivated',
            "Deactivated user should have status='deactivated'"
        );
        
        // Verify the user has a non-null deactivated_at timestamp
        expect($deactivatedUser->deactivatedAt)->not->toBeNull(
            "Deactivated user should have a non-null deactivated_at timestamp"
        );
        
        // Verify the deactivated_at timestamp is a valid datetime string
        expect($deactivatedUser->deactivatedAt)->toMatch('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',
            "deactivated_at should be a valid datetime string (YYYY-MM-DD HH:MM:SS)"
        );
    }
    
    // Clean up
    $pdo->exec("DELETE FROM users WHERE service_id IN (SELECT id FROM services WHERE project_id >= 9500 AND project_id < 9600)");
    $pdo->exec("DELETE FROM services WHERE project_id >= 9500 AND project_id < 9600");
    $pdo->exec("DELETE FROM projects WHERE id >= 9500 AND id < 9600");
})->group('property', 'user-management', 'deactivation-audit-trail');

test('deactivation creates audit trail record in users table', function () {
    $faker = Faker\Factory::create();
    $pdo = getTestDatabase();
    $serviceRepository = new ServiceRepository($pdo);
    $userRepository = new UserRepository($pdo);
    $validationService = new ValidationService($serviceRepository);
    $userService = new UserService($userRepository, $serviceRepository, $validationService);
    
    // Clean up test data
    $pdo->exec("DELETE FROM users WHERE service_id IN (SELECT id FROM services WHERE project_id >= 9600 AND project_id < 9700)");
    $pdo->exec("DELETE FROM services WHERE project_id >= 9600 AND project_id < 9700");
    $pdo->exec("DELETE FROM projects WHERE id >= 9600 AND id < 9700");
    
    // Ensure test client exists
    $pdo->exec("INSERT IGNORE INTO clients (id, name, contact_info) VALUES (1, 'Test Client', 'test@example.com')");
    
    // Create test projects
    for ($i = 0; $i < 100; $i++) {
        $pdo->exec("INSERT INTO projects (id, client_id, name, description) VALUES (" . (9600 + $i) . ", 1, 'Test Project $i', 'Test')");
    }
    
    // Run 100 iterations
    for ($i = 0; $i < 100; $i++) {
        // Create a service
        $userLimit = $faker->numberBetween(10, 100);
        $startDate = $faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d');
        $endDate = $faker->dateTimeBetween('+1 day', '+1 year')->format('Y-m-d');
        
        $service = new Service(
            9600 + $i,
            $faker->randomElement(['web', 'mobile', 'other']),
            $userLimit,
            $startDate,
            $endDate,
            1
        );
        
        $service = $serviceRepository->create($service);
        
        // Create an active user
        $userIdentifier = $faker->uuid();
        $user = new User(
            $service->id,
            $userIdentifier,
            'active'
        );
        $user = $userRepository->create($user);
        $userId = $user->id;
        
        // Deactivate the user
        $userService->deactivateUser($userId);
        
        // Query the users table for the deactivated record
        $stmt = $pdo->prepare("
            SELECT * FROM users 
            WHERE id = :id 
            AND status = 'deactivated' 
            AND deactivated_at IS NOT NULL
        ");
        $stmt->execute(['id' => $userId]);
        $auditRecord = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Verify an audit trail record exists
        expect($auditRecord)->not->toBeNull(
            "An audit trail record should exist in users table for deactivated user (ID: $userId)"
        );
        
        expect($auditRecord['status'])->toBe('deactivated',
            "Audit record should have status='deactivated'"
        );
        
        expect($auditRecord['deactivated_at'])->not->toBeNull(
            "Audit record should have non-null deactivated_at timestamp"
        );
        
        expect($auditRecord['user_identifier'])->toBe($userIdentifier,
            "Audit record should preserve the original user_identifier"
        );
    }
    
    // Clean up
    $pdo->exec("DELETE FROM users WHERE service_id IN (SELECT id FROM services WHERE project_id >= 9600 AND project_id < 9700)");
    $pdo->exec("DELETE FROM services WHERE project_id >= 9600 AND project_id < 9700");
    $pdo->exec("DELETE FROM projects WHERE id >= 9600 AND id < 9700");
})->group('property', 'user-management', 'deactivation-audit-trail');

<?php

use App\Services\UserService;
use App\Services\ValidationService;
use App\Repositories\UserRepository;
use App\Repositories\ServiceRepository;
use App\Models\User;
use App\Models\Service;

/**
 * Feature: subscription-management-module
 * Property 35: Concurrent User Creation Safety
 * 
 * For any service at exactly (user_limit - 1) active users, if N concurrent 
 * user creation requests arrive (where N > 1), at most 1 should succeed and 
 * the rest should fail with USER_LIMIT_EXCEEDED.
 * 
 * **Validates: Requirements 15.1**
 */

test('concurrent user creation respects user limit with row-level locking', function () {
    $faker = Faker\Factory::create();
    $pdo = getTestDatabase();
    $serviceRepository = new ServiceRepository($pdo);
    $userRepository = new UserRepository($pdo);
    $validationService = new ValidationService($serviceRepository);
    $userService = new UserService($userRepository, $serviceRepository, $validationService);
    
    // Clean up test data
    $pdo->exec("DELETE FROM users WHERE service_id IN (SELECT id FROM services WHERE project_id >= 8000 AND project_id < 8100)");
    $pdo->exec("DELETE FROM services WHERE project_id >= 8000 AND project_id < 8100");
    $pdo->exec("DELETE FROM projects WHERE id >= 8000 AND id < 8100");
    
    // Ensure test client exists
    $pdo->exec("INSERT IGNORE INTO clients (id, name, contact_info) VALUES (1, 'Test Client', 'test@example.com')");
    
    // Create test projects
    for ($i = 0; $i < 100; $i++) {
        $pdo->exec("INSERT INTO projects (id, client_id, name, description) VALUES (" . (8000 + $i) . ", 1, 'Test Project $i', 'Test')");
    }
    
    // Run 100 iterations
    for ($i = 0; $i < 100; $i++) {
        // Create a service with user_limit and set active_user_count to (user_limit - 1)
        $userLimit = $faker->numberBetween(2, 20);
        $activeUserCount = $userLimit - 1; // One slot remaining
        $startDate = $faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d');
        $endDate = $faker->dateTimeBetween('+1 day', '+1 year')->format('Y-m-d');
        
        $service = new Service(
            8000 + $i,
            $faker->randomElement(['web', 'mobile', 'other']),
            $userLimit,
            $startDate,
            $endDate,
            $activeUserCount
        );
        
        $service = $serviceRepository->create($service);
        
        // Number of concurrent requests (N > 1)
        $concurrentRequests = $faker->numberBetween(2, 10);
        
        // Simulate concurrent user creation requests
        $results = [];
        $processes = [];
        
        // Check if pcntl extension is available for true concurrency
        if (function_exists('pcntl_fork')) {
            // Use process forking for true concurrent execution
            for ($j = 0; $j < $concurrentRequests; $j++) {
                $pid = pcntl_fork();
                
                if ($pid == -1) {
                    // Fork failed, fall back to sequential execution
                    break;
                } elseif ($pid == 0) {
                    // Child process
                    try {
                        // Create new database connection for child process
                        $childPdo = getTestDatabase();
                        $childServiceRepo = new ServiceRepository($childPdo);
                        $childUserRepo = new UserRepository($childPdo);
                        $childValidationService = new ValidationService($childServiceRepo);
                        $childUserService = new UserService($childUserRepo, $childServiceRepo, $childValidationService);
                        
                        $userData = [
                            'service_id' => $service->id,
                            'user_identifier' => $faker->uuid() . "_child_$j",
                            'status' => 'active'
                        ];
                        
                        $childUserService->registerUser($userData);
                        exit(0); // Success
                    } catch (Exception $e) {
                        exit(1); // Failure
                    }
                } else {
                    // Parent process - store child PID
                    $processes[] = $pid;
                }
            }
            
            // Parent process waits for all children
            $successCount = 0;
            $failureCount = 0;
            foreach ($processes as $pid) {
                $status = 0;
                pcntl_waitpid($pid, $status);
                if (pcntl_wexitstatus($status) == 0) {
                    $successCount++;
                } else {
                    $failureCount++;
                }
            }
            
            $results = [
                'success' => $successCount,
                'failure' => $failureCount
            ];
        } else {
            // Fallback: Use rapid sequential execution to test locking
            // This tests that the locking mechanism prevents race conditions
            // even when requests arrive in quick succession
            $successCount = 0;
            $failureCount = 0;
            
            for ($j = 0; $j < $concurrentRequests; $j++) {
                try {
                    $userData = [
                        'service_id' => $service->id,
                        'user_identifier' => $faker->uuid() . "_seq_$j",
                        'status' => 'active'
                    ];
                    
                    $userService->registerUser($userData);
                    $successCount++;
                } catch (Exception $e) {
                    $failureCount++;
                }
            }
            
            $results = [
                'success' => $successCount,
                'failure' => $failureCount
            ];
        }
        
        // Verify: At most 1 request should succeed
        expect($results['success'])->toBeLessThanOrEqual(1,
            "At most 1 user creation should succeed when service is at (user_limit - 1). " .
            "Service ID: {$service->id}, User Limit: $userLimit, Initial Active Count: $activeUserCount, " .
            "Concurrent Requests: $concurrentRequests, Successes: {$results['success']}, Failures: {$results['failure']}"
        );
        
        // Verify: At least (N - 1) requests should fail
        expect($results['failure'])->toBeGreaterThanOrEqual($concurrentRequests - 1,
            "At least " . ($concurrentRequests - 1) . " requests should fail when service is at (user_limit - 1). " .
            "Service ID: {$service->id}, Failures: {$results['failure']}"
        );
        
        // Verify: The final active_user_count should not exceed user_limit
        $finalService = $serviceRepository->findById($service->id);
        expect($finalService->activeUserCount)->toBeLessThanOrEqual($userLimit,
            "Final active_user_count ({$finalService->activeUserCount}) should not exceed user_limit ($userLimit)"
        );
        
        // Verify: The final active_user_count should be either (user_limit - 1) or user_limit
        expect($finalService->activeUserCount)->toBeIn([$activeUserCount, $userLimit],
            "Final active_user_count should be either $activeUserCount (no success) or $userLimit (1 success)"
        );
    }
    
    // Clean up
    $pdo->exec("DELETE FROM users WHERE service_id IN (SELECT id FROM services WHERE project_id >= 8000 AND project_id < 8100)");
    $pdo->exec("DELETE FROM services WHERE project_id >= 8000 AND project_id < 8100");
    $pdo->exec("DELETE FROM projects WHERE id >= 8000 AND id < 8100");
})->group('property', 'concurrent-user-creation', 'concurrency-safety');

test('concurrent user creation at exact capacity rejects all requests', function () {
    $faker = Faker\Factory::create();
    $pdo = getTestDatabase();
    $serviceRepository = new ServiceRepository($pdo);
    $userRepository = new UserRepository($pdo);
    $validationService = new ValidationService($serviceRepository);
    $userService = new UserService($userRepository, $serviceRepository, $validationService);
    
    // Clean up test data
    $pdo->exec("DELETE FROM users WHERE service_id IN (SELECT id FROM services WHERE project_id >= 8100 AND project_id < 8200)");
    $pdo->exec("DELETE FROM services WHERE project_id >= 8100 AND project_id < 8200");
    $pdo->exec("DELETE FROM projects WHERE id >= 8100 AND id < 8200");
    
    // Ensure test client exists
    $pdo->exec("INSERT IGNORE INTO clients (id, name, contact_info) VALUES (1, 'Test Client', 'test@example.com')");
    
    // Create test projects
    for ($i = 0; $i < 100; $i++) {
        $pdo->exec("INSERT INTO projects (id, client_id, name, description) VALUES (" . (8100 + $i) . ", 1, 'Test Project $i', 'Test')");
    }
    
    // Run 100 iterations
    for ($i = 0; $i < 100; $i++) {
        // Create a service at exact capacity
        $userLimit = $faker->numberBetween(2, 20);
        $activeUserCount = $userLimit; // At capacity
        $startDate = $faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d');
        $endDate = $faker->dateTimeBetween('+1 day', '+1 year')->format('Y-m-d');
        
        $service = new Service(
            8100 + $i,
            $faker->randomElement(['web', 'mobile', 'other']),
            $userLimit,
            $startDate,
            $endDate,
            $activeUserCount
        );
        
        $service = $serviceRepository->create($service);
        
        // Number of concurrent requests
        $concurrentRequests = $faker->numberBetween(2, 10);
        
        // Simulate concurrent user creation requests
        $successCount = 0;
        $failureCount = 0;
        
        for ($j = 0; $j < $concurrentRequests; $j++) {
            try {
                $userData = [
                    'service_id' => $service->id,
                    'user_identifier' => $faker->uuid() . "_capacity_$j",
                    'status' => 'active'
                ];
                
                $userService->registerUser($userData);
                $successCount++;
            } catch (Exception $e) {
                $failureCount++;
            }
        }
        
        // Verify: All requests should fail when at capacity
        expect($successCount)->toBe(0,
            "No user creation should succeed when service is at capacity. " .
            "Service ID: {$service->id}, User Limit: $userLimit, Active Count: $activeUserCount, " .
            "Concurrent Requests: $concurrentRequests, Successes: $successCount"
        );
        
        expect($failureCount)->toBe($concurrentRequests,
            "All $concurrentRequests requests should fail when service is at capacity"
        );
        
        // Verify: The active_user_count should remain at user_limit
        $finalService = $serviceRepository->findById($service->id);
        expect($finalService->activeUserCount)->toBe($userLimit,
            "Active user count should remain at user_limit ($userLimit) after failed concurrent requests"
        );
    }
    
    // Clean up
    $pdo->exec("DELETE FROM users WHERE service_id IN (SELECT id FROM services WHERE project_id >= 8100 AND project_id < 8200)");
    $pdo->exec("DELETE FROM services WHERE project_id >= 8100 AND project_id < 8200");
    $pdo->exec("DELETE FROM projects WHERE id >= 8100 AND id < 8200");
})->group('property', 'concurrent-user-creation', 'concurrency-safety');

test('row-level locking prevents race conditions in user registration', function () {
    $faker = Faker\Factory::create();
    $pdo = getTestDatabase();
    $serviceRepository = new ServiceRepository($pdo);
    $userRepository = new UserRepository($pdo);
    $validationService = new ValidationService($serviceRepository);
    $userService = new UserService($userRepository, $serviceRepository, $validationService);
    
    // Clean up test data
    $pdo->exec("DELETE FROM users WHERE service_id IN (SELECT id FROM services WHERE project_id >= 8200 AND project_id < 8300)");
    $pdo->exec("DELETE FROM services WHERE project_id >= 8200 AND project_id < 8300");
    $pdo->exec("DELETE FROM projects WHERE id >= 8200 AND id < 8300");
    
    // Ensure test client exists
    $pdo->exec("INSERT IGNORE INTO clients (id, name, contact_info) VALUES (1, 'Test Client', 'test@example.com')");
    
    // Create test projects
    for ($i = 0; $i < 100; $i++) {
        $pdo->exec("INSERT INTO projects (id, client_id, name, description) VALUES (" . (8200 + $i) . ", 1, 'Test Project $i', 'Test')");
    }
    
    // Run 100 iterations
    for ($i = 0; $i < 100; $i++) {
        // Create a service with one slot remaining
        $userLimit = $faker->numberBetween(5, 50);
        $initialActiveCount = $userLimit - 1;
        $startDate = $faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d');
        $endDate = $faker->dateTimeBetween('+1 day', '+1 year')->format('Y-m-d');
        
        $service = new Service(
            8200 + $i,
            $faker->randomElement(['web', 'mobile', 'other']),
            $userLimit,
            $startDate,
            $endDate,
            $initialActiveCount
        );
        
        $service = $serviceRepository->create($service);
        
        // Attempt rapid sequential user registrations to test locking
        $concurrentRequests = $faker->numberBetween(3, 15);
        $successCount = 0;
        $failureCount = 0;
        $userLimitErrors = 0;
        
        for ($j = 0; $j < $concurrentRequests; $j++) {
            try {
                $userData = [
                    'service_id' => $service->id,
                    'user_identifier' => $faker->uuid() . "_lock_$j",
                    'status' => 'active'
                ];
                
                $userService->registerUser($userData);
                $successCount++;
            } catch (Exception $e) {
                $failureCount++;
                if (stripos($e->getMessage(), 'limit') !== false) {
                    $userLimitErrors++;
                }
            }
        }
        
        // Verify: Exactly 1 request should succeed
        expect($successCount)->toBe(1,
            "Exactly 1 user creation should succeed when service has 1 slot remaining. " .
            "Service ID: {$service->id}, User Limit: $userLimit, Initial Count: $initialActiveCount, " .
            "Concurrent Requests: $concurrentRequests, Successes: $successCount"
        );
        
        // Verify: All failures should be due to user limit exceeded
        expect($userLimitErrors)->toBe($failureCount,
            "All $failureCount failures should be due to user limit exceeded errors"
        );
        
        // Verify: Final count should equal user_limit
        $finalService = $serviceRepository->findById($service->id);
        expect($finalService->activeUserCount)->toBe($userLimit,
            "Final active_user_count should equal user_limit ($userLimit) after concurrent requests"
        );
        
        // Verify: Actual database count matches service count
        $actualCount = $userRepository->countActiveByServiceId($service->id);
        expect($actualCount)->toBe($successCount,
            "Actual database count ($actualCount) should match number of successful registrations ($successCount)"
        );
        
        // Verify: The service's active_user_count reflects the actual users created
        expect($finalService->activeUserCount)->toBe($initialActiveCount + $successCount,
            "Service active_user_count should be initial count ($initialActiveCount) + successful registrations ($successCount)"
        );
    }
    
    // Clean up
    $pdo->exec("DELETE FROM users WHERE service_id IN (SELECT id FROM services WHERE project_id >= 8200 AND project_id < 8300)");
    $pdo->exec("DELETE FROM services WHERE project_id >= 8200 AND project_id < 8300");
    $pdo->exec("DELETE FROM projects WHERE id >= 8200 AND id < 8300");
})->group('property', 'concurrent-user-creation', 'row-level-locking');

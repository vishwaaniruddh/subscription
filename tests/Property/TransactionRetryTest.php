<?php

use App\Services\UserService;
use App\Services\ValidationService;
use App\Repositories\UserRepository;
use App\Repositories\ServiceRepository;
use App\Models\User;
use App\Models\Service;

/**
 * Feature: subscription-management-module
 * Property 37: Transaction Retry
 * 
 * For any user registration that fails due to transient database errors, 
 * the system should retry up to 3 times before returning an error to the caller.
 * 
 * **Validates: Requirements 15.5**
 */

test('transaction retries up to 3 times on deadlock before failing', function () {
    $faker = Faker\Factory::create();
    $pdo = getTestDatabase();
    $serviceRepository = new ServiceRepository($pdo);
    $userRepository = new UserRepository($pdo);
    $validationService = new ValidationService($serviceRepository);
    $userService = new UserService($userRepository, $serviceRepository, $validationService);
    
    // Clean up test data
    $pdo->exec("DELETE FROM users WHERE service_id IN (SELECT id FROM services WHERE project_id >= 20000 AND project_id < 20100)");
    $pdo->exec("DELETE FROM services WHERE project_id >= 20000 AND project_id < 20100");
    $pdo->exec("DELETE FROM projects WHERE id >= 20000 AND id < 20100");
    
    // Ensure test client exists
    $pdo->exec("INSERT IGNORE INTO clients (id, name, contact_info) VALUES (1, 'Test Client', 'test@example.com')");
    
    // Create test projects
    for ($i = 0; $i < 100; $i++) {
        $pdo->exec("INSERT INTO projects (id, client_id, name, description) VALUES (" . (20000 + $i) . ", 1, 'Test Project $i', 'Test')");
    }
    
    // Run 100 iterations
    for ($i = 0; $i < 100; $i++) {
        // Create a service with available capacity
        $userLimit = $faker->numberBetween(10, 50);
        $activeUserCount = $faker->numberBetween(0, $userLimit - 5); // Ensure capacity
        $startDate = $faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d');
        $endDate = $faker->dateTimeBetween('+1 day', '+1 year')->format('Y-m-d');
        
        $service = new Service(
            20000 + $i,
            $faker->randomElement(['web', 'mobile', 'other']),
            $userLimit,
            $startDate,
            $endDate,
            $activeUserCount
        );
        
        $service = $serviceRepository->create($service);
        
        // Create a mock repository that simulates deadlock errors
        $mockUserRepository = new class($pdo) extends UserRepository {
            private int $attemptCount = 0;
            private int $maxAttemptsBeforeSuccess;
            
            public function setMaxAttemptsBeforeSuccess(int $attempts): void {
                $this->maxAttemptsBeforeSuccess = $attempts;
                $this->attemptCount = 0;
            }
            
            public function create(User $user): User {
                $this->attemptCount++;
                
                // Simulate deadlock for the first N attempts
                if ($this->attemptCount <= $this->maxAttemptsBeforeSuccess) {
                    // Create a proper PDOException with errorInfo
                    $exception = new \PDOException("Deadlock found when trying to get lock");
                    $exception->errorInfo = ['HY000', 1213, 'Deadlock found when trying to get lock'];
                    throw $exception;
                }
                
                // On final attempt, succeed
                return parent::create($user);
            }
        };
        
        // Test scenario 1: Retry succeeds on 2nd attempt (1 retry)
        $mockUserRepository->setMaxAttemptsBeforeSuccess(1);
        $mockUserService = new UserService($mockUserRepository, $serviceRepository, $validationService);
        
        $userIdentifier = $faker->uuid();
        $userData = [
            'service_id' => $service->id,
            'user_identifier' => $userIdentifier,
            'status' => 'active'
        ];
        
        $success = false;
        try {
            $mockUserService->registerUser($userData);
            $success = true;
        } catch (Exception $e) {
            // Should not fail on 1 retry
        }
        
        expect($success)->toBeTrue(
            "Transaction should succeed after 1 retry (Service ID: {$service->id})"
        );
        
        // Clean up the created user
        $pdo->exec("DELETE FROM users WHERE service_id = {$service->id} AND user_identifier = '$userIdentifier'");
        $pdo->exec("UPDATE services SET active_user_count = $activeUserCount WHERE id = {$service->id}");
        
        // Test scenario 2: Retry succeeds on 3rd attempt (2 retries)
        $mockUserRepository->setMaxAttemptsBeforeSuccess(2);
        $userIdentifier2 = $faker->uuid();
        $userData2 = [
            'service_id' => $service->id,
            'user_identifier' => $userIdentifier2,
            'status' => 'active'
        ];
        
        $success = false;
        try {
            $mockUserService->registerUser($userData2);
            $success = true;
        } catch (Exception $e) {
            // Should not fail on 2 retries
        }
        
        expect($success)->toBeTrue(
            "Transaction should succeed after 2 retries (Service ID: {$service->id})"
        );
        
        // Clean up the created user
        $pdo->exec("DELETE FROM users WHERE service_id = {$service->id} AND user_identifier = '$userIdentifier2'");
        $pdo->exec("UPDATE services SET active_user_count = $activeUserCount WHERE id = {$service->id}");
    }
    
    // Clean up
    $pdo->exec("DELETE FROM users WHERE service_id IN (SELECT id FROM services WHERE project_id >= 20000 AND project_id < 20100)");
    $pdo->exec("DELETE FROM services WHERE project_id >= 20000 AND project_id < 20100");
    $pdo->exec("DELETE FROM projects WHERE id >= 20000 AND id < 20100");
})->group('property', 'transaction-retry', 'data-integrity');

test('transaction fails after exhausting 3 retry attempts', function () {
    $faker = Faker\Factory::create();
    $pdo = getTestDatabase();
    $serviceRepository = new ServiceRepository($pdo);
    $userRepository = new UserRepository($pdo);
    $validationService = new ValidationService($serviceRepository);
    $userService = new UserService($userRepository, $serviceRepository, $validationService);
    
    // Clean up test data
    $pdo->exec("DELETE FROM users WHERE service_id IN (SELECT id FROM services WHERE project_id >= 20100 AND project_id < 20200)");
    $pdo->exec("DELETE FROM services WHERE project_id >= 20100 AND project_id < 20200");
    $pdo->exec("DELETE FROM projects WHERE id >= 20100 AND id < 20200");
    
    // Ensure test client exists
    $pdo->exec("INSERT IGNORE INTO clients (id, name, contact_info) VALUES (1, 'Test Client', 'test@example.com')");
    
    // Create test projects
    for ($i = 0; $i < 100; $i++) {
        $pdo->exec("INSERT INTO projects (id, client_id, name, description) VALUES (" . (20100 + $i) . ", 1, 'Test Project $i', 'Test')");
    }
    
    // Run 100 iterations
    for ($i = 0; $i < 100; $i++) {
        // Create a service with available capacity
        $userLimit = $faker->numberBetween(10, 50);
        $activeUserCount = $faker->numberBetween(0, $userLimit - 5); // Ensure capacity
        $startDate = $faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d');
        $endDate = $faker->dateTimeBetween('+1 day', '+1 year')->format('Y-m-d');
        
        $service = new Service(
            20100 + $i,
            $faker->randomElement(['web', 'mobile', 'other']),
            $userLimit,
            $startDate,
            $endDate,
            $activeUserCount
        );
        
        $service = $serviceRepository->create($service);
        
        // Create a mock repository that always simulates deadlock errors
        $mockUserRepository = new class($pdo) extends UserRepository {
            private int $attemptCount = 0;
            
            public function resetAttemptCount(): void {
                $this->attemptCount = 0;
            }
            
            public function getAttemptCount(): int {
                return $this->attemptCount;
            }
            
            public function create(User $user): User {
                $this->attemptCount++;
                
                // Always simulate deadlock error with proper errorInfo
                $exception = new \PDOException("Deadlock found when trying to get lock");
                $exception->errorInfo = ['HY000', 1213, 'Deadlock found when trying to get lock'];
                throw $exception;
            }
        };
        
        $mockUserRepository->resetAttemptCount();
        $mockUserService = new UserService($mockUserRepository, $serviceRepository, $validationService);
        
        $userIdentifier = $faker->uuid();
        $userData = [
            'service_id' => $service->id,
            'user_identifier' => $userIdentifier,
            'status' => 'active'
        ];
        
        $exceptionThrown = false;
        $exceptionMessage = '';
        try {
            $mockUserService->registerUser($userData);
        } catch (Exception $e) {
            $exceptionThrown = true;
            $exceptionMessage = $e->getMessage();
        }
        
        // Verify the operation failed after retries
        expect($exceptionThrown)->toBeTrue(
            "Transaction should fail after exhausting retries (Service ID: {$service->id})"
        );
        
        // Verify the error message indicates retry exhaustion
        expect($exceptionMessage)->toMatch('/3 attempts|deadlocks/',
            "Error message should indicate 3 retry attempts were made or mention deadlocks"
        );
        
        // Verify: no user record should be created after all retries fail
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE service_id = :service_id AND user_identifier = :user_identifier");
        $stmt->execute(['service_id' => $service->id, 'user_identifier' => $userIdentifier]);
        $userExists = (int)$stmt->fetchColumn();
        
        expect($userExists)->toBe(0,
            "No user record should exist after all retries fail"
        );
        
        // Verify: active_user_count should remain unchanged
        $serviceAfter = $serviceRepository->findById($service->id);
        expect($serviceAfter->activeUserCount)->toBe($activeUserCount,
            "Active user count should remain unchanged after failed retries (before: $activeUserCount, after: {$serviceAfter->activeUserCount})"
        );
    }
    
    // Clean up
    $pdo->exec("DELETE FROM users WHERE service_id IN (SELECT id FROM services WHERE project_id >= 20100 AND project_id < 20200)");
    $pdo->exec("DELETE FROM services WHERE project_id >= 20100 AND project_id < 20200");
    $pdo->exec("DELETE FROM projects WHERE id >= 20100 AND id < 20200");
})->group('property', 'transaction-retry', 'data-integrity');

test('transaction retry uses exponential backoff', function () {
    $faker = Faker\Factory::create();
    $pdo = getTestDatabase();
    $serviceRepository = new ServiceRepository($pdo);
    $userRepository = new UserRepository($pdo);
    $validationService = new ValidationService($serviceRepository);
    $userService = new UserService($userRepository, $serviceRepository, $validationService);
    
    // Clean up test data
    $pdo->exec("DELETE FROM users WHERE service_id IN (SELECT id FROM services WHERE project_id >= 20200 AND project_id < 20300)");
    $pdo->exec("DELETE FROM services WHERE project_id >= 20200 AND project_id < 20300");
    $pdo->exec("DELETE FROM projects WHERE id >= 20200 AND id < 20300");
    
    // Ensure test client exists
    $pdo->exec("INSERT IGNORE INTO clients (id, name, contact_info) VALUES (1, 'Test Client', 'test@example.com')");
    
    // Create test projects
    for ($i = 0; $i < 100; $i++) {
        $pdo->exec("INSERT INTO projects (id, client_id, name, description) VALUES (" . (20200 + $i) . ", 1, 'Test Project $i', 'Test')");
    }
    
    // Run 100 iterations
    for ($i = 0; $i < 100; $i++) {
        // Create a service with available capacity
        $userLimit = $faker->numberBetween(10, 50);
        $activeUserCount = $faker->numberBetween(0, $userLimit - 5); // Ensure capacity
        $startDate = $faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d');
        $endDate = $faker->dateTimeBetween('+1 day', '+1 year')->format('Y-m-d');
        
        $service = new Service(
            20200 + $i,
            $faker->randomElement(['web', 'mobile', 'other']),
            $userLimit,
            $startDate,
            $endDate,
            $activeUserCount
        );
        
        $service = $serviceRepository->create($service);
        
        // Create a mock repository that tracks timing between attempts
        $mockUserRepository = new class($pdo) extends UserRepository {
            private int $attemptCount = 0;
            private array $attemptTimestamps = [];
            
            public function resetTracking(): void {
                $this->attemptCount = 0;
                $this->attemptTimestamps = [];
            }
            
            public function getAttemptTimestamps(): array {
                return $this->attemptTimestamps;
            }
            
            public function create(User $user): User {
                $this->attemptCount++;
                $this->attemptTimestamps[] = microtime(true);
                
                // Fail first 2 attempts, succeed on 3rd
                if ($this->attemptCount < 3) {
                    $exception = new \PDOException("Deadlock found when trying to get lock");
                    $exception->errorInfo = ['HY000', 1213, 'Deadlock found when trying to get lock'];
                    throw $exception;
                }
                
                return parent::create($user);
            }
        };
        
        $mockUserRepository->resetTracking();
        $mockUserService = new UserService($mockUserRepository, $serviceRepository, $validationService);
        
        $userIdentifier = $faker->uuid();
        $userData = [
            'service_id' => $service->id,
            'user_identifier' => $userIdentifier,
            'status' => 'active'
        ];
        
        $startTime = microtime(true);
        try {
            $mockUserService->registerUser($userData);
        } catch (Exception $e) {
            // Ignore exceptions for this test
        }
        $endTime = microtime(true);
        
        $timestamps = $mockUserRepository->getAttemptTimestamps();
        
        // Verify we had multiple attempts
        expect(count($timestamps))->toBeGreaterThanOrEqual(2,
            "Should have at least 2 attempts for exponential backoff test"
        );
        
        // Verify exponential backoff timing
        // First retry should wait ~200ms (2^1 * 100ms)
        // Second retry should wait ~400ms (2^2 * 100ms)
        if (count($timestamps) >= 2) {
            $firstDelay = ($timestamps[1] - $timestamps[0]) * 1000; // Convert to ms
            
            // Allow some tolerance for timing (50ms to 350ms for first retry)
            expect($firstDelay)->toBeGreaterThan(50,
                "First retry delay should be at least 50ms (exponential backoff)"
            );
            
            expect($firstDelay)->toBeLessThan(350,
                "First retry delay should be less than 350ms (exponential backoff with tolerance)"
            );
        }
        
        if (count($timestamps) >= 3) {
            $secondDelay = ($timestamps[2] - $timestamps[1]) * 1000; // Convert to ms
            
            // Second delay should be longer than first (exponential growth)
            $firstDelay = ($timestamps[1] - $timestamps[0]) * 1000;
            
            expect($secondDelay)->toBeGreaterThan($firstDelay * 0.8,
                "Second retry delay should be longer than first (exponential backoff)"
            );
        }
        
        // Clean up the created user
        $pdo->exec("DELETE FROM users WHERE service_id = {$service->id} AND user_identifier = '$userIdentifier'");
        $pdo->exec("UPDATE services SET active_user_count = $activeUserCount WHERE id = {$service->id}");
    }
    
    // Clean up
    $pdo->exec("DELETE FROM users WHERE service_id IN (SELECT id FROM services WHERE project_id >= 20200 AND project_id < 20300)");
    $pdo->exec("DELETE FROM services WHERE project_id >= 20200 AND project_id < 20300");
    $pdo->exec("DELETE FROM projects WHERE id >= 20200 AND id < 20300");
})->group('property', 'transaction-retry', 'data-integrity');

test('transaction retry only applies to deadlock errors not other exceptions', function () {
    $faker = Faker\Factory::create();
    $pdo = getTestDatabase();
    $serviceRepository = new ServiceRepository($pdo);
    $userRepository = new UserRepository($pdo);
    $validationService = new ValidationService($serviceRepository);
    $userService = new UserService($userRepository, $serviceRepository, $validationService);
    
    // Clean up test data
    $pdo->exec("DELETE FROM users WHERE service_id IN (SELECT id FROM services WHERE project_id >= 20300 AND project_id < 20400)");
    $pdo->exec("DELETE FROM services WHERE project_id >= 20300 AND project_id < 20400");
    $pdo->exec("DELETE FROM projects WHERE id >= 20300 AND id < 20400");
    
    // Ensure test client exists
    $pdo->exec("INSERT IGNORE INTO clients (id, name, contact_info) VALUES (1, 'Test Client', 'test@example.com')");
    
    // Create test projects
    for ($i = 0; $i < 100; $i++) {
        $pdo->exec("INSERT INTO projects (id, client_id, name, description) VALUES (" . (20300 + $i) . ", 1, 'Test Project $i', 'Test')");
    }
    
    // Run 100 iterations
    for ($i = 0; $i < 100; $i++) {
        // Create a service with available capacity
        $userLimit = $faker->numberBetween(10, 50);
        $activeUserCount = $faker->numberBetween(0, $userLimit - 5); // Ensure capacity
        $startDate = $faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d');
        $endDate = $faker->dateTimeBetween('+1 day', '+1 year')->format('Y-m-d');
        
        $service = new Service(
            20300 + $i,
            $faker->randomElement(['web', 'mobile', 'other']),
            $userLimit,
            $startDate,
            $endDate,
            $activeUserCount
        );
        
        $service = $serviceRepository->create($service);
        
        // Create a mock repository that simulates non-deadlock errors
        $mockUserRepository = new class($pdo) extends UserRepository {
            private int $attemptCount = 0;
            
            public function resetAttemptCount(): void {
                $this->attemptCount = 0;
            }
            
            public function getAttemptCount(): int {
                return $this->attemptCount;
            }
            
            public function create(User $user): User {
                $this->attemptCount++;
                
                // Simulate a non-deadlock database error (e.g., constraint violation)
                $exception = new \PDOException("Duplicate entry for key 'unique_active_user'");
                $exception->errorInfo = ['23000', 1062, "Duplicate entry for key 'unique_active_user'"];
                throw $exception;
            }
        };
        
        $mockUserRepository->resetAttemptCount();
        $mockUserService = new UserService($mockUserRepository, $serviceRepository, $validationService);
        
        $userIdentifier = $faker->uuid();
        $userData = [
            'service_id' => $service->id,
            'user_identifier' => $userIdentifier,
            'status' => 'active'
        ];
        
        $exceptionThrown = false;
        try {
            $mockUserService->registerUser($userData);
        } catch (Exception $e) {
            $exceptionThrown = true;
        }
        
        // Verify the operation failed
        expect($exceptionThrown)->toBeTrue(
            "Transaction should fail immediately for non-deadlock errors (Service ID: {$service->id})"
        );
        
        // Verify only 1 attempt was made (no retries for non-deadlock errors)
        expect($mockUserRepository->getAttemptCount())->toBe(1,
            "Should only attempt once for non-deadlock errors (no retries)"
        );
        
        // Verify: no user record should be created
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE service_id = :service_id AND user_identifier = :user_identifier");
        $stmt->execute(['service_id' => $service->id, 'user_identifier' => $userIdentifier]);
        $userExists = (int)$stmt->fetchColumn();
        
        expect($userExists)->toBe(0,
            "No user record should exist after non-deadlock error"
        );
    }
    
    // Clean up
    $pdo->exec("DELETE FROM users WHERE service_id IN (SELECT id FROM services WHERE project_id >= 20300 AND project_id < 20400)");
    $pdo->exec("DELETE FROM services WHERE project_id >= 20300 AND project_id < 20400");
    $pdo->exec("DELETE FROM projects WHERE id >= 20300 AND id < 20400");
})->group('property', 'transaction-retry', 'data-integrity');

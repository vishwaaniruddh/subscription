<?php

use App\Database;

/**
 * Feature: subscription-management-module
 * Property 6: User Limit Validation
 * 
 * For any service creation or update request, if user_limit is not provided or is <= 0,
 * the request should be rejected; if user_limit > 0, it should be accepted.
 * 
 * **Validates: Requirements 4.1, 4.2**
 * 
 * This test verifies that the database schema enforces user_limit constraints at the
 * database level through CHECK constraints.
 */

beforeEach(function () {
    $this->pdo = getTestDatabase();
    
    // Clean up test data before each test
    $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    $this->pdo->exec('TRUNCATE TABLE subscription_history');
    $this->pdo->exec('TRUNCATE TABLE users');
    $this->pdo->exec('TRUNCATE TABLE services');
    $this->pdo->exec('TRUNCATE TABLE projects');
    $this->pdo->exec('TRUNCATE TABLE clients');
    $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    
    // Create a test client and project for service creation
    $stmt = $this->pdo->prepare('INSERT INTO clients (name, contact_info) VALUES (?, ?)');
    $stmt->execute(['Test Client', 'test@example.com']);
    $this->clientId = $this->pdo->lastInsertId();
    
    $stmt = $this->pdo->prepare('INSERT INTO projects (client_id, name, description) VALUES (?, ?, ?)');
    $stmt->execute([$this->clientId, 'Test Project', 'Test Description']);
    $this->projectId = $this->pdo->lastInsertId();
});

afterEach(function () {
    // Clean up test data after each test
    $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    $this->pdo->exec('TRUNCATE TABLE subscription_history');
    $this->pdo->exec('TRUNCATE TABLE users');
    $this->pdo->exec('TRUNCATE TABLE services');
    $this->pdo->exec('TRUNCATE TABLE projects');
    $this->pdo->exec('TRUNCATE TABLE clients');
    $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
});

test('database rejects service creation with user_limit <= 0', function () {
    $faker = Faker\Factory::create();
    
    // Run 100 iterations with different invalid user_limit values
    for ($i = 0; $i < 100; $i++) {
        // Generate invalid user_limit values (0 or negative)
        $invalidUserLimit = $faker->numberBetween(-1000, 0);
        
        $serviceType = $faker->randomElement(['web', 'mobile', 'other']);
        $startDate = $faker->date('Y-m-d');
        $endDate = $faker->dateTimeBetween($startDate, '+1 year')->format('Y-m-d');
        
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO services (project_id, service_type, user_limit, start_date, end_date) 
                 VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $this->projectId,
                $serviceType,
                $invalidUserLimit,
                $startDate,
                $endDate
            ]);
            
            // If we reach here, the constraint didn't work
            throw new Exception("Database accepted invalid user_limit: $invalidUserLimit");
        } catch (PDOException $e) {
            // Expected: database should reject the insert
            expect($e->getCode())->toBeIn(['23000', '23513', 'HY000'])
                ->and($e->getMessage())->toContain('user_limit');
        }
    }
})->group('property', 'database', 'schema');

test('database accepts service creation with user_limit > 0', function () {
    $faker = Faker\Factory::create();
    
    // Run 100 iterations with different valid user_limit values
    for ($i = 0; $i < 100; $i++) {
        // Generate valid user_limit values (positive integers)
        $validUserLimit = $faker->numberBetween(1, 10000);
        
        $serviceType = $faker->randomElement(['web', 'mobile', 'other']);
        $startDate = $faker->date('Y-m-d');
        $endDate = $faker->dateTimeBetween($startDate, '+1 year')->format('Y-m-d');
        
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO services (project_id, service_type, user_limit, start_date, end_date) 
                 VALUES (?, ?, ?, ?, ?)'
            );
            $result = $stmt->execute([
                $this->projectId,
                $serviceType,
                $validUserLimit,
                $startDate,
                $endDate
            ]);
            
            // Verify the insert was successful
            expect($result)->toBeTrue();
            
            $serviceId = $this->pdo->lastInsertId();
            expect($serviceId)->toBeGreaterThan(0);
            
            // Verify the service was created with correct user_limit
            $stmt = $this->pdo->prepare('SELECT user_limit FROM services WHERE id = ?');
            $stmt->execute([$serviceId]);
            $service = $stmt->fetch();
            
            expect($service)->not->toBeNull()
                ->and($service['user_limit'])->toBe($validUserLimit);
            
            // Clean up for next iteration
            $this->pdo->exec("DELETE FROM services WHERE id = $serviceId");
        } catch (PDOException $e) {
            // Should not happen with valid user_limit
            throw new Exception("Database rejected valid user_limit $validUserLimit: " . $e->getMessage());
        }
    }
})->group('property', 'database', 'schema');

test('database rejects service update to user_limit <= 0', function () {
    $faker = Faker\Factory::create();
    
    // Run 100 iterations with different invalid user_limit values
    for ($i = 0; $i < 100; $i++) {
        // First create a valid service
        $validUserLimit = $faker->numberBetween(1, 1000);
        $serviceType = $faker->randomElement(['web', 'mobile', 'other']);
        $startDate = $faker->date('Y-m-d');
        $endDate = $faker->dateTimeBetween($startDate, '+1 year')->format('Y-m-d');
        
        $stmt = $this->pdo->prepare(
            'INSERT INTO services (project_id, service_type, user_limit, start_date, end_date) 
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $this->projectId,
            $serviceType,
            $validUserLimit,
            $startDate,
            $endDate
        ]);
        
        $serviceId = $this->pdo->lastInsertId();
        
        // Now try to update to an invalid user_limit
        $invalidUserLimit = $faker->numberBetween(-1000, 0);
        
        try {
            $stmt = $this->pdo->prepare('UPDATE services SET user_limit = ? WHERE id = ?');
            $stmt->execute([$invalidUserLimit, $serviceId]);
            
            // If we reach here, the constraint didn't work
            throw new Exception("Database accepted invalid user_limit update: $invalidUserLimit");
        } catch (PDOException $e) {
            // Expected: database should reject the update
            expect($e->getCode())->toBeIn(['23000', '23513', 'HY000'])
                ->and($e->getMessage())->toContain('user_limit');
        }
        
        // Clean up for next iteration
        $this->pdo->exec("DELETE FROM services WHERE id = $serviceId");
    }
})->group('property', 'database', 'schema');

test('database accepts service update to user_limit > 0', function () {
    $faker = Faker\Factory::create();
    
    // Run 100 iterations with different valid user_limit values
    for ($i = 0; $i < 100; $i++) {
        // First create a valid service
        $initialUserLimit = $faker->numberBetween(1, 500);
        $serviceType = $faker->randomElement(['web', 'mobile', 'other']);
        $startDate = $faker->date('Y-m-d');
        $endDate = $faker->dateTimeBetween($startDate, '+1 year')->format('Y-m-d');
        
        $stmt = $this->pdo->prepare(
            'INSERT INTO services (project_id, service_type, user_limit, start_date, end_date) 
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $this->projectId,
            $serviceType,
            $initialUserLimit,
            $startDate,
            $endDate
        ]);
        
        $serviceId = $this->pdo->lastInsertId();
        
        // Now update to a different valid user_limit
        $newValidUserLimit = $faker->numberBetween(1, 10000);
        
        try {
            $stmt = $this->pdo->prepare('UPDATE services SET user_limit = ? WHERE id = ?');
            $result = $stmt->execute([$newValidUserLimit, $serviceId]);
            
            // Verify the update was successful
            expect($result)->toBeTrue();
            
            // Verify the service was updated with correct user_limit
            $stmt = $this->pdo->prepare('SELECT user_limit FROM services WHERE id = ?');
            $stmt->execute([$serviceId]);
            $service = $stmt->fetch();
            
            expect($service)->not->toBeNull()
                ->and($service['user_limit'])->toBe($newValidUserLimit);
        } catch (PDOException $e) {
            // Should not happen with valid user_limit
            throw new Exception("Database rejected valid user_limit update $newValidUserLimit: " . $e->getMessage());
        }
        
        // Clean up for next iteration
        $this->pdo->exec("DELETE FROM services WHERE id = $serviceId");
    }
})->group('property', 'database', 'schema');

<?php

use App\Models\Client;
use App\Models\Project;
use App\Models\Service;
use App\Models\User;
use App\Repositories\ClientRepository;
use App\Repositories\ProjectRepository;
use App\Repositories\ServiceRepository;
use App\Repositories\UserRepository;
use App\Services\ProjectService;
use App\Services\ServiceManager;

/**
 * Feature: subscription-management-module
 * Property 31: Foreign Key Integrity
 * 
 * For any attempt to create a project with non-existent client_id, or service with 
 * non-existent project_id, or user with non-existent service_id, the operation should 
 * be rejected.
 * 
 * **Validates: Requirements 11.7**
 * 
 * This test verifies that the database enforces foreign key constraints at the database level,
 * preventing orphaned records and maintaining referential integrity across the entity hierarchy.
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
    
    // Initialize repositories
    $this->clientRepo = new ClientRepository($this->pdo);
    $this->projectRepo = new ProjectRepository($this->pdo);
    $this->serviceRepo = new ServiceRepository($this->pdo);
    $this->userRepo = new UserRepository($this->pdo);
    
    // Initialize services
    $this->projectService = new ProjectService($this->projectRepo);
    $this->serviceManager = new ServiceManager($this->serviceRepo);
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

test('project creation with non-existent client_id is rejected', function () {
    $faker = Faker\Factory::create();
    
    // Run 100 iterations with different non-existent client IDs
    for ($i = 0; $i < 100; $i++) {
        // Generate a random non-existent client ID (large random number)
        $nonExistentClientId = $faker->numberBetween(999999, 9999999);
        
        // Attempt to create project with non-existent client_id
        $projectData = [
            'client_id' => $nonExistentClientId,
            'name' => $faker->words(3, true),
            'description' => $faker->optional()->sentence()
        ];
        
        // Expect PDOException due to foreign key constraint violation
        try {
            $this->projectService->createProject($projectData);
            
            // If we reach here, the foreign key constraint was not enforced
            expect(false)->toBeTrue('Expected PDOException for foreign key violation but none was thrown');
        } catch (PDOException $e) {
            // Verify it's a foreign key constraint error
            // MySQL error code 1452 is "Cannot add or update a child row: a foreign key constraint fails"
            expect($e->getCode())->toBeIn(['23000', '1452'])
                ->and($e->getMessage())->toContain('foreign key constraint');
        } catch (Exception $e) {
            // Check if it's wrapped in a generic exception
            expect($e->getMessage())->toContain('foreign key constraint');
        }
    }
})->group('property', 'foreign-key', 'project');

test('service creation with non-existent project_id is rejected', function () {
    $faker = Faker\Factory::create();
    
    // Run 100 iterations with different non-existent project IDs
    for ($i = 0; $i < 100; $i++) {
        // Generate a random non-existent project ID (large random number)
        $nonExistentProjectId = $faker->numberBetween(999999, 9999999);
        
        // Generate valid service data
        $serviceType = $faker->randomElement(['web', 'mobile', 'other']);
        $userLimit = $faker->numberBetween(1, 10000);
        $startDate = $faker->dateTimeBetween('-1 year', '+1 year');
        $endDate = $faker->dateTimeBetween($startDate, '+2 years');
        
        // Attempt to create service with non-existent project_id
        $serviceData = [
            'project_id' => $nonExistentProjectId,
            'service_type' => $serviceType,
            'user_limit' => $userLimit,
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d')
        ];
        
        // Expect PDOException due to foreign key constraint violation
        try {
            $this->serviceManager->createService($serviceData);
            
            // If we reach here, the foreign key constraint was not enforced
            expect(false)->toBeTrue('Expected PDOException for foreign key violation but none was thrown');
        } catch (PDOException $e) {
            // Verify it's a foreign key constraint error
            expect($e->getCode())->toBeIn(['23000', '1452'])
                ->and($e->getMessage())->toContain('foreign key constraint');
        } catch (Exception $e) {
            // Check if it's wrapped in a generic exception
            expect($e->getMessage())->toContain('foreign key constraint');
        }
    }
})->group('property', 'foreign-key', 'service');

test('user creation with non-existent service_id is rejected', function () {
    $faker = Faker\Factory::create();
    
    // Run 100 iterations with different non-existent service IDs
    for ($i = 0; $i < 100; $i++) {
        // Generate a random non-existent service ID (large random number)
        $nonExistentServiceId = $faker->numberBetween(999999, 9999999);
        
        // Attempt to create user with non-existent service_id
        $user = new User(
            $nonExistentServiceId,
            $faker->userName(),
            'active'
        );
        
        // Expect PDOException due to foreign key constraint violation
        try {
            $this->userRepo->create($user);
            
            // If we reach here, the foreign key constraint was not enforced
            expect(false)->toBeTrue('Expected PDOException for foreign key violation but none was thrown');
        } catch (PDOException $e) {
            // Verify it's a foreign key constraint error
            expect($e->getCode())->toBeIn(['23000', '1452'])
                ->and($e->getMessage())->toContain('foreign key constraint');
        } catch (Exception $e) {
            // Check if it's wrapped in a generic exception
            expect($e->getMessage())->toContain('foreign key constraint');
        }
    }
})->group('property', 'foreign-key', 'user');

test('foreign key integrity across all entity relationships', function () {
    $faker = Faker\Factory::create();
    
    // Run 100 iterations testing all three foreign key relationships
    for ($i = 0; $i < 100; $i++) {
        // Test 1: Project with non-existent client_id
        $nonExistentClientId = $faker->numberBetween(999999, 9999999);
        $projectData = [
            'client_id' => $nonExistentClientId,
            'name' => $faker->words(3, true),
            'description' => $faker->sentence()
        ];
        
        $projectFailed = false;
        try {
            $this->projectService->createProject($projectData);
        } catch (Exception $e) {
            $projectFailed = true;
        }
        expect($projectFailed)->toBeTrue('Project creation with invalid client_id should fail');
        
        // Test 2: Service with non-existent project_id
        $nonExistentProjectId = $faker->numberBetween(999999, 9999999);
        $startDate = $faker->dateTimeBetween('-1 year', '+1 year');
        $endDate = $faker->dateTimeBetween($startDate, '+2 years');
        
        $serviceData = [
            'project_id' => $nonExistentProjectId,
            'service_type' => $faker->randomElement(['web', 'mobile', 'other']),
            'user_limit' => $faker->numberBetween(1, 10000),
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d')
        ];
        
        $serviceFailed = false;
        try {
            $this->serviceManager->createService($serviceData);
        } catch (Exception $e) {
            $serviceFailed = true;
        }
        expect($serviceFailed)->toBeTrue('Service creation with invalid project_id should fail');
        
        // Test 3: User with non-existent service_id
        $nonExistentServiceId = $faker->numberBetween(999999, 9999999);
        $user = new User($nonExistentServiceId, $faker->userName(), 'active');
        
        $userFailed = false;
        try {
            $this->userRepo->create($user);
        } catch (Exception $e) {
            $userFailed = true;
        }
        expect($userFailed)->toBeTrue('User creation with invalid service_id should fail');
    }
})->group('property', 'foreign-key', 'all-relationships');

test('valid foreign keys allow entity creation', function () {
    $faker = Faker\Factory::create();
    
    // Run 100 iterations to verify valid foreign keys work correctly
    for ($i = 0; $i < 100; $i++) {
        // Create valid client
        $client = new Client($faker->company(), $faker->email());
        $client = $this->clientRepo->create($client);
        expect($client->id)->toBeGreaterThan(0);
        
        // Create valid project with existing client_id
        $projectData = [
            'client_id' => $client->id,
            'name' => $faker->words(3, true),
            'description' => $faker->sentence()
        ];
        $project = $this->projectService->createProject($projectData);
        expect($project->id)->toBeGreaterThan(0)
            ->and($project->clientId)->toBe($client->id);
        
        // Create valid service with existing project_id
        $startDate = $faker->dateTimeBetween('-1 year', '+1 year');
        $endDate = $faker->dateTimeBetween($startDate, '+2 years');
        
        $serviceData = [
            'project_id' => $project->id,
            'service_type' => $faker->randomElement(['web', 'mobile', 'other']),
            'user_limit' => $faker->numberBetween(1, 10000),
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d')
        ];
        $service = $this->serviceManager->createService($serviceData);
        expect($service->id)->toBeGreaterThan(0)
            ->and($service->projectId)->toBe($project->id);
        
        // Create valid user with existing service_id
        $user = new User($service->id, $faker->userName(), 'active');
        $user = $this->userRepo->create($user);
        expect($user->id)->toBeGreaterThan(0)
            ->and($user->serviceId)->toBe($service->id);
        
        // Clean up
        $this->userRepo->deactivate($user->id);
        $this->serviceRepo->delete($service->id);
        $this->projectRepo->delete($project->id);
        $this->clientRepo->delete($client->id);
    }
})->group('property', 'foreign-key', 'valid-relationships');

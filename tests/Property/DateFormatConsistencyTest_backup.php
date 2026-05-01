<?php

use App\Models\Client;
use App\Models\Project;
use App\Models\Service;
use App\Repositories\ClientRepository;
use App\Repositories\ProjectRepository;
use App\Repositories\ServiceRepository;

/**
 * Feature: subscription-management-module
 * Property 2: Entity Persistence Round-Trip
 * 
 * For any client, project, or service entity, creating it and then retrieving it 
 * by ID should return an equivalent entity with all fields matching the original values.
 * 
 * **Validates: Requirements 1.6**
 * 
 * This test verifies that data written to the database can be read back with identical values,
 * ensuring data integrity through round-trip persistence for all three entity types.
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

test('client persistence round-trip', function () {
    $faker = Faker\Factory::create();
    
    // Run 100 iterations with different client data
    for ($i = 0; $i < 100; $i++) {
        // Generate random client data
        $name = $faker->company();
        $contactInfo = $faker->optional()->email();
        
        // Create client entity
        $originalClient = new Client($name, $contactInfo);
        
        // Persist to database
        $createdClient = $this->clientRepo->create($originalClient);
        
        // Verify ID was assigned
        expect($createdClient->id)->toBeGreaterThan(0);
        
        // Retrieve from database
        $retrievedClient = $this->clientRepo->findById($createdClient->id);
        
        // Verify entity was retrieved
        expect($retrievedClient)->not->toBeNull();
        
        // Verify all fields match
        expect($retrievedClient->id)->toBe($createdClient->id)
            ->and($retrievedClient->name)->toBe($name)
            ->and($retrievedClient->contactInfo)->toBe($contactInfo)
            ->and($retrievedClient->createdAt)->not->toBeNull()
            ->and($retrievedClient->updatedAt)->not->toBeNull();
        
        // Clean up for next iteration
        $this->clientRepo->delete($createdClient->id);
    }
})->group('property', 'persistence', 'client');

test('project persistence round-trip', function () {
    $faker = Faker\Factory::create();
    
    // Run 100 iterations with different project data
    for ($i = 0; $i < 100; $i++) {
        // Create a parent client first
        $client = new Client($faker->company(), $faker->email());
        $client = $this->clientRepo->create($client);
        
        // Generate random project data
        $name = $faker->words(3, true);
        $description = $faker->optional()->sentence();
        
        // Create project entity
        $originalProject = new Project($client->id, $name, $description);
        
        // Persist to database
        $createdProject = $this->projectRepo->create($originalProject);
        
        // Verify ID was assigned
        expect($createdProject->id)->toBeGreaterThan(0);
        
        // Retrieve from database
        $retrievedProject = $this->projectRepo->findById($createdProject->id);
        
        // Verify entity was retrieved
        expect($retrievedProject)->not->toBeNull();
        
        // Verify all fields match
        expect($retrievedProject->id)->toBe($createdProject->id)
            ->and($retrievedProject->clientId)->toBe($client->id)
            ->and($retrievedProject->name)->toBe($name)
            ->and($retrievedProject->description)->toBe($description)
            ->and($retrievedProject->createdAt)->not->toBeNull()
            ->and($retrievedProject->updatedAt)->not->toBeNull();
        
        // Clean up for next iteration
        $this->projectRepo->delete($createdProject->id);
        $this->clientRepo->delete($client->id);
    }
})->group('property', 'persistence', 'project');

test('service persistence round-trip', function () {
    $faker = Faker\Factory::create();
    
    // Run 100 iterations with different service data
    for ($i = 0; $i < 100; $i++) {
        // Create parent client and project first
        $client = new Client($faker->company(), $faker->email());
        $client = $this->clientRepo->create($client);
        
        $project = new Project($client->id, $faker->words(3, true), $faker->sentence());
        $project = $this->projectRepo->create($project);
        
        // Generate random service data
        $serviceType = $faker->randomElement(['web', 'mobile', 'other']);
        $userLimit = $faker->numberBetween(1, 10000);
        $activeUserCount = $faker->numberBetween(0, $userLimit);
        
        // Generate valid date range (start_date <= end_date)
        $startDate = $faker->dateTimeBetween('-1 year', '+1 year');
        $endDate = $faker->dateTimeBetween($startDate, '+2 years');
        $startDateStr = $startDate->format('Y-m-d');
        $endDateStr = $endDate->format('Y-m-d');
        
        // Create service entity
        $originalService = new Service(
            $project->id,
            $serviceType,
            $userLimit,
            $startDateStr,
            $endDateStr,
            $activeUserCount
        );
        
        // Persist to database
        $createdService = $this->serviceRepo->create($originalService);
        
        // Verify ID was assigned
        expect($createdService->id)->toBeGreaterThan(0);
        
        // Retrieve from database
        $retrievedService = $this->serviceRepo->findById($createdService->id);
        
        // Verify entity was retrieved
        expect($retrievedService)->not->toBeNull();
        
        // Verify all fields match
        expect($retrievedService->id)->toBe($createdService->id)
            ->and($retrievedService->projectId)->toBe($project->id)
            ->and($retrievedService->serviceType)->toBe($serviceType)
            ->and($retrievedService->userLimit)->toBe($userLimit)
            ->and($retrievedService->activeUserCount)->toBe($activeUserCount)
            ->and($retrievedService->startDate)->toBe($startDateStr)
            ->and($retrievedService->endDate)->toBe($endDateStr)
            ->and($retrievedService->createdAt)->not->toBeNull()
            ->and($retrievedService->updatedAt)->not->toBeNull();
        
        // Clean up for next iteration
        $this->serviceRepo->delete($createdService->id);
        $this->projectRepo->delete($project->id);
        $this->clientRepo->delete($client->id);
    }
})->group('property', 'persistence', 'service');


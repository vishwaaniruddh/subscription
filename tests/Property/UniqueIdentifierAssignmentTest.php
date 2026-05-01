<?php

use App\Models\Client;
use App\Models\Project;
use App\Models\Service;
use App\Repositories\ClientRepository;
use App\Repositories\ProjectRepository;
use App\Repositories\ServiceRepository;
use App\Services\ClientService;
use App\Services\ProjectService;
use App\Services\ServiceManager;

/**
 * Feature: subscription-management-module
 * Property 1: Entity Creation Assigns Unique Identifiers
 * 
 * For any valid client, project, or service data, creating the entity should result 
 * in a unique identifier being assigned that differs from all previously created 
 * entities of that type.
 * 
 * **Validates: Requirements 1.5**
 * 
 * This test verifies that the system assigns unique identifiers to each entity upon creation,
 * ensuring no ID collisions occur across multiple entity creations of the same type.
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
    
    // Initialize services
    $this->clientService = new ClientService($this->clientRepo);
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

test('client creation assigns unique identifiers', function () {
    $faker = Faker\Factory::create();
    
    $createdClientIds = [];
    
    // Run 100 iterations to create multiple clients
    for ($i = 0; $i < 100; $i++) {
        // Generate random client data
        $clientData = [
            'name' => $faker->company(),
            'contact_info' => $faker->optional()->email()
        ];
        
        // Create client using ClientService
        $client = $this->clientService->createClient($clientData);
        
        // Verify ID was assigned
        expect($client->id)->not->toBeNull()
            ->and($client->id)->toBeGreaterThan(0);
        
        // Verify ID is unique (not in the list of previously created IDs)
        expect($createdClientIds)->not->toContain($client->id);
        
        // Add to the list of created IDs
        $createdClientIds[] = $client->id;
    }
    
    // Verify we have 100 unique IDs
    expect(count($createdClientIds))->toBe(100)
        ->and(count(array_unique($createdClientIds)))->toBe(100);
})->group('property', 'unique-identifier', 'client');

test('project creation assigns unique identifiers', function () {
    $faker = Faker\Factory::create();
    
    // Create a parent client first
    $clientData = [
        'name' => $faker->company(),
        'contact_info' => $faker->email()
    ];
    $client = $this->clientService->createClient($clientData);
    
    $createdProjectIds = [];
    
    // Run 100 iterations to create multiple projects
    for ($i = 0; $i < 100; $i++) {
        // Generate random project data
        $projectData = [
            'client_id' => $client->id,
            'name' => $faker->words(3, true),
            'description' => $faker->optional()->sentence()
        ];
        
        // Create project using ProjectService
        $project = $this->projectService->createProject($projectData);
        
        // Verify ID was assigned
        expect($project->id)->not->toBeNull()
            ->and($project->id)->toBeGreaterThan(0);
        
        // Verify ID is unique (not in the list of previously created IDs)
        expect($createdProjectIds)->not->toContain($project->id);
        
        // Add to the list of created IDs
        $createdProjectIds[] = $project->id;
    }
    
    // Verify we have 100 unique IDs
    expect(count($createdProjectIds))->toBe(100)
        ->and(count(array_unique($createdProjectIds)))->toBe(100);
})->group('property', 'unique-identifier', 'project');

test('service creation assigns unique identifiers', function () {
    $faker = Faker\Factory::create();
    
    // Create parent client and project first
    $clientData = [
        'name' => $faker->company(),
        'contact_info' => $faker->email()
    ];
    $client = $this->clientService->createClient($clientData);
    
    $projectData = [
        'client_id' => $client->id,
        'name' => $faker->words(3, true),
        'description' => $faker->sentence()
    ];
    $project = $this->projectService->createProject($projectData);
    
    $createdServiceIds = [];
    
    // Run 100 iterations to create multiple services
    for ($i = 0; $i < 100; $i++) {
        // Generate random service data
        $serviceType = $faker->randomElement(['web', 'mobile', 'other']);
        $userLimit = $faker->numberBetween(1, 10000);
        
        // Generate valid date range (start_date <= end_date)
        $startDate = $faker->dateTimeBetween('-1 year', '+1 year');
        $endDate = $faker->dateTimeBetween($startDate, '+2 years');
        
        $serviceData = [
            'project_id' => $project->id,
            'service_type' => $serviceType,
            'user_limit' => $userLimit,
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d')
        ];
        
        // Create service using ServiceManager
        $service = $this->serviceManager->createService($serviceData);
        
        // Verify ID was assigned
        expect($service->id)->not->toBeNull()
            ->and($service->id)->toBeGreaterThan(0);
        
        // Verify ID is unique (not in the list of previously created IDs)
        expect($createdServiceIds)->not->toContain($service->id);
        
        // Add to the list of created IDs
        $createdServiceIds[] = $service->id;
    }
    
    // Verify we have 100 unique IDs
    expect(count($createdServiceIds))->toBe(100)
        ->and(count(array_unique($createdServiceIds)))->toBe(100);
})->group('property', 'unique-identifier', 'service');

test('unique identifiers across all entity types', function () {
    $faker = Faker\Factory::create();
    
    $allCreatedIds = [
        'clients' => [],
        'projects' => [],
        'services' => []
    ];
    
    // Create 100 entities of each type and track all IDs
    for ($i = 0; $i < 100; $i++) {
        // Create client
        $clientData = [
            'name' => $faker->company(),
            'contact_info' => $faker->optional()->email()
        ];
        $client = $this->clientService->createClient($clientData);
        
        expect($client->id)->not->toBeNull()
            ->and($client->id)->toBeGreaterThan(0)
            ->and($allCreatedIds['clients'])->not->toContain($client->id);
        
        $allCreatedIds['clients'][] = $client->id;
        
        // Create project for this client
        $projectData = [
            'client_id' => $client->id,
            'name' => $faker->words(3, true),
            'description' => $faker->optional()->sentence()
        ];
        $project = $this->projectService->createProject($projectData);
        
        expect($project->id)->not->toBeNull()
            ->and($project->id)->toBeGreaterThan(0)
            ->and($allCreatedIds['projects'])->not->toContain($project->id);
        
        $allCreatedIds['projects'][] = $project->id;
        
        // Create service for this project
        $serviceType = $faker->randomElement(['web', 'mobile', 'other']);
        $userLimit = $faker->numberBetween(1, 10000);
        $startDate = $faker->dateTimeBetween('-1 year', '+1 year');
        $endDate = $faker->dateTimeBetween($startDate, '+2 years');
        
        $serviceData = [
            'project_id' => $project->id,
            'service_type' => $serviceType,
            'user_limit' => $userLimit,
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d')
        ];
        $service = $this->serviceManager->createService($serviceData);
        
        expect($service->id)->not->toBeNull()
            ->and($service->id)->toBeGreaterThan(0)
            ->and($allCreatedIds['services'])->not->toContain($service->id);
        
        $allCreatedIds['services'][] = $service->id;
    }
    
    // Verify each entity type has 100 unique IDs
    expect(count($allCreatedIds['clients']))->toBe(100)
        ->and(count(array_unique($allCreatedIds['clients'])))->toBe(100)
        ->and(count($allCreatedIds['projects']))->toBe(100)
        ->and(count(array_unique($allCreatedIds['projects'])))->toBe(100)
        ->and(count($allCreatedIds['services']))->toBe(100)
        ->and(count(array_unique($allCreatedIds['services'])))->toBe(100);
})->group('property', 'unique-identifier', 'all-entities');

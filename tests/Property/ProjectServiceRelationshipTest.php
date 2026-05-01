<?php

use App\Models\Client;
use App\Models\Project;
use App\Models\Service;
use App\Repositories\ClientRepository;
use App\Repositories\ProjectRepository;
use App\Repositories\ServiceRepository;

/**
 * Feature: subscription-management-module
 * Property 4: Project-Service Relationship
 * 
 * For any project, creating multiple services associated with that project should result 
 * in all services being retrievable via the project's service list.
 * 
 * **Validates: Requirements 3.7**
 * 
 * This test verifies the one-to-many relationship between projects and services,
 * ensuring that all services created for a project can be retrieved via the
 * findByProjectId method.
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

test('project can have multiple services and all are retrievable', function () {
    $faker = Faker\Factory::create();
    
    // Run 100 iterations with different numbers of services
    for ($i = 0; $i < 100; $i++) {
        // Create a client and project
        $client = new Client($faker->company(), $faker->optional()->email());
        $client = $this->clientRepo->create($client);
        
        $project = new Project($client->id, $faker->words(3, true), $faker->optional()->sentence());
        $project = $this->projectRepo->create($project);
        
        // Generate a random number of services (1 to 10)
        $serviceCount = $faker->numberBetween(1, 10);
        $createdServices = [];
        
        // Create multiple services for this project
        for ($j = 0; $j < $serviceCount; $j++) {
            $serviceType = $faker->randomElement(['web', 'mobile', 'other']);
            $userLimit = $faker->numberBetween(1, 100);
            $startDate = $faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d');
            $endDate = $faker->dateTimeBetween('now', '+1 year')->format('Y-m-d');
            
            $service = new Service(
                $project->id,
                $serviceType,
                $userLimit,
                $startDate,
                $endDate
            );
            $createdService = $this->serviceRepo->create($service);
            
            // Verify service was created with correct project_id
            expect($createdService->id)->toBeGreaterThan(0)
                ->and($createdService->projectId)->toBe($project->id);
            
            $createdServices[] = $createdService;
        }
        
        // Retrieve all services for this project
        $retrievedServices = $this->serviceRepo->findByProjectId($project->id);
        
        // Verify the count matches
        expect(count($retrievedServices))->toBe($serviceCount)
            ->and(count($retrievedServices))->toBe(count($createdServices));
        
        // Verify all created services are in the retrieved list
        $retrievedServiceIds = array_map(fn($s) => $s->id, $retrievedServices);
        
        foreach ($createdServices as $createdService) {
            expect($retrievedServiceIds)->toContain($createdService->id);
            
            // Find the matching retrieved service
            $matchingService = null;
            foreach ($retrievedServices as $retrievedService) {
                if ($retrievedService->id === $createdService->id) {
                    $matchingService = $retrievedService;
                    break;
                }
            }
            
            // Verify the service details match
            expect($matchingService)->not->toBeNull()
                ->and($matchingService->projectId)->toBe($project->id)
                ->and($matchingService->serviceType)->toBe($createdService->serviceType)
                ->and($matchingService->userLimit)->toBe($createdService->userLimit)
                ->and($matchingService->startDate)->toBe($createdService->startDate)
                ->and($matchingService->endDate)->toBe($createdService->endDate);
        }
        
        // Clean up for next iteration
        foreach ($createdServices as $service) {
            $this->serviceRepo->delete($service->id);
        }
        $this->projectRepo->delete($project->id);
        $this->clientRepo->delete($client->id);
    }
})->group('property', 'relationship', 'project-service');

test('project with no services returns empty list', function () {
    $faker = Faker\Factory::create();
    
    // Run 100 iterations
    for ($i = 0; $i < 100; $i++) {
        // Create a client and project without any services
        $client = new Client($faker->company(), $faker->optional()->email());
        $client = $this->clientRepo->create($client);
        
        $project = new Project($client->id, $faker->words(3, true), $faker->optional()->sentence());
        $project = $this->projectRepo->create($project);
        
        // Retrieve services for this project
        $retrievedServices = $this->serviceRepo->findByProjectId($project->id);
        
        // Verify the list is empty
        expect($retrievedServices)->toBeArray()
            ->and(count($retrievedServices))->toBe(0);
        
        // Clean up for next iteration
        $this->projectRepo->delete($project->id);
        $this->clientRepo->delete($client->id);
    }
})->group('property', 'relationship', 'project-service');

test('services are isolated by project', function () {
    $faker = Faker\Factory::create();
    
    // Run 100 iterations
    for ($i = 0; $i < 100; $i++) {
        // Create a client and two different projects
        $client = new Client($faker->company(), $faker->email());
        $client = $this->clientRepo->create($client);
        
        $project1 = new Project($client->id, $faker->words(3, true), $faker->sentence());
        $project1 = $this->projectRepo->create($project1);
        
        $project2 = new Project($client->id, $faker->words(3, true), $faker->sentence());
        $project2 = $this->projectRepo->create($project2);
        
        // Create services for project1
        $project1ServiceCount = $faker->numberBetween(1, 5);
        $project1Services = [];
        for ($j = 0; $j < $project1ServiceCount; $j++) {
            $serviceType = $faker->randomElement(['web', 'mobile', 'other']);
            $userLimit = $faker->numberBetween(1, 100);
            $startDate = $faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d');
            $endDate = $faker->dateTimeBetween('now', '+1 year')->format('Y-m-d');
            
            $service = new Service($project1->id, $serviceType, $userLimit, $startDate, $endDate);
            $project1Services[] = $this->serviceRepo->create($service);
        }
        
        // Create services for project2
        $project2ServiceCount = $faker->numberBetween(1, 5);
        $project2Services = [];
        for ($j = 0; $j < $project2ServiceCount; $j++) {
            $serviceType = $faker->randomElement(['web', 'mobile', 'other']);
            $userLimit = $faker->numberBetween(1, 100);
            $startDate = $faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d');
            $endDate = $faker->dateTimeBetween('now', '+1 year')->format('Y-m-d');
            
            $service = new Service($project2->id, $serviceType, $userLimit, $startDate, $endDate);
            $project2Services[] = $this->serviceRepo->create($service);
        }
        
        // Retrieve services for each project
        $retrievedProject1Services = $this->serviceRepo->findByProjectId($project1->id);
        $retrievedProject2Services = $this->serviceRepo->findByProjectId($project2->id);
        
        // Verify counts match
        expect(count($retrievedProject1Services))->toBe($project1ServiceCount)
            ->and(count($retrievedProject2Services))->toBe($project2ServiceCount);
        
        // Verify project1's services don't contain project2's services
        $retrievedProject1ServiceIds = array_map(fn($s) => $s->id, $retrievedProject1Services);
        $retrievedProject2ServiceIds = array_map(fn($s) => $s->id, $retrievedProject2Services);
        
        foreach ($project1Services as $service) {
            expect($retrievedProject1ServiceIds)->toContain($service->id)
                ->and($retrievedProject2ServiceIds)->not->toContain($service->id);
        }
        
        foreach ($project2Services as $service) {
            expect($retrievedProject2ServiceIds)->toContain($service->id)
                ->and($retrievedProject1ServiceIds)->not->toContain($service->id);
        }
        
        // Clean up for next iteration
        foreach ($project1Services as $service) {
            $this->serviceRepo->delete($service->id);
        }
        foreach ($project2Services as $service) {
            $this->serviceRepo->delete($service->id);
        }
        $this->projectRepo->delete($project1->id);
        $this->projectRepo->delete($project2->id);
        $this->clientRepo->delete($client->id);
    }
})->group('property', 'relationship', 'project-service');

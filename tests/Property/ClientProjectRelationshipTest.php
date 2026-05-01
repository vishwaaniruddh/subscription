<?php

use App\Models\Client;
use App\Models\Project;
use App\Repositories\ClientRepository;
use App\Repositories\ProjectRepository;

/**
 * Feature: subscription-management-module
 * Property 3: Client-Project Relationship
 * 
 * For any client, creating multiple projects associated with that client should result 
 * in all projects being retrievable via the client's project list.
 * 
 * **Validates: Requirements 2.6**
 * 
 * This test verifies the one-to-many relationship between clients and projects,
 * ensuring that all projects created for a client can be retrieved via the
 * findByClientId method.
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

test('client can have multiple projects and all are retrievable', function () {
    $faker = Faker\Factory::create();
    
    // Run 100 iterations with different numbers of projects
    for ($i = 0; $i < 100; $i++) {
        // Create a client
        $clientName = $faker->company();
        $clientContactInfo = $faker->optional()->email();
        $client = new Client($clientName, $clientContactInfo);
        $client = $this->clientRepo->create($client);
        
        // Generate a random number of projects (1 to 10)
        $projectCount = $faker->numberBetween(1, 10);
        $createdProjects = [];
        
        // Create multiple projects for this client
        for ($j = 0; $j < $projectCount; $j++) {
            $projectName = $faker->words(3, true);
            $projectDescription = $faker->optional()->sentence();
            
            $project = new Project($client->id, $projectName, $projectDescription);
            $createdProject = $this->projectRepo->create($project);
            
            // Verify project was created with correct client_id
            expect($createdProject->id)->toBeGreaterThan(0)
                ->and($createdProject->clientId)->toBe($client->id);
            
            $createdProjects[] = $createdProject;
        }
        
        // Retrieve all projects for this client
        $retrievedProjects = $this->projectRepo->findByClientId($client->id);
        
        // Verify the count matches
        expect(count($retrievedProjects))->toBe($projectCount)
            ->and(count($retrievedProjects))->toBe(count($createdProjects));
        
        // Verify all created projects are in the retrieved list
        $retrievedProjectIds = array_map(fn($p) => $p->id, $retrievedProjects);
        
        foreach ($createdProjects as $createdProject) {
            expect($retrievedProjectIds)->toContain($createdProject->id);
            
            // Find the matching retrieved project
            $matchingProject = null;
            foreach ($retrievedProjects as $retrievedProject) {
                if ($retrievedProject->id === $createdProject->id) {
                    $matchingProject = $retrievedProject;
                    break;
                }
            }
            
            // Verify the project details match
            expect($matchingProject)->not->toBeNull()
                ->and($matchingProject->clientId)->toBe($client->id)
                ->and($matchingProject->name)->toBe($createdProject->name)
                ->and($matchingProject->description)->toBe($createdProject->description);
        }
        
        // Clean up for next iteration
        foreach ($createdProjects as $project) {
            $this->projectRepo->delete($project->id);
        }
        $this->clientRepo->delete($client->id);
    }
})->group('property', 'relationship', 'client-project');

test('client with no projects returns empty list', function () {
    $faker = Faker\Factory::create();
    
    // Run 100 iterations
    for ($i = 0; $i < 100; $i++) {
        // Create a client without any projects
        $clientName = $faker->company();
        $clientContactInfo = $faker->optional()->email();
        $client = new Client($clientName, $clientContactInfo);
        $client = $this->clientRepo->create($client);
        
        // Retrieve projects for this client
        $retrievedProjects = $this->projectRepo->findByClientId($client->id);
        
        // Verify the list is empty
        expect($retrievedProjects)->toBeArray()
            ->and(count($retrievedProjects))->toBe(0);
        
        // Clean up for next iteration
        $this->clientRepo->delete($client->id);
    }
})->group('property', 'relationship', 'client-project');

test('projects are isolated by client', function () {
    $faker = Faker\Factory::create();
    
    // Run 100 iterations
    for ($i = 0; $i < 100; $i++) {
        // Create two different clients
        $client1 = new Client($faker->company(), $faker->email());
        $client1 = $this->clientRepo->create($client1);
        
        $client2 = new Client($faker->company(), $faker->email());
        $client2 = $this->clientRepo->create($client2);
        
        // Create projects for client1
        $client1ProjectCount = $faker->numberBetween(1, 5);
        $client1Projects = [];
        for ($j = 0; $j < $client1ProjectCount; $j++) {
            $project = new Project($client1->id, $faker->words(3, true), $faker->sentence());
            $client1Projects[] = $this->projectRepo->create($project);
        }
        
        // Create projects for client2
        $client2ProjectCount = $faker->numberBetween(1, 5);
        $client2Projects = [];
        for ($j = 0; $j < $client2ProjectCount; $j++) {
            $project = new Project($client2->id, $faker->words(3, true), $faker->sentence());
            $client2Projects[] = $this->projectRepo->create($project);
        }
        
        // Retrieve projects for each client
        $retrievedClient1Projects = $this->projectRepo->findByClientId($client1->id);
        $retrievedClient2Projects = $this->projectRepo->findByClientId($client2->id);
        
        // Verify counts match
        expect(count($retrievedClient1Projects))->toBe($client1ProjectCount)
            ->and(count($retrievedClient2Projects))->toBe($client2ProjectCount);
        
        // Verify client1's projects don't contain client2's projects
        $retrievedClient1ProjectIds = array_map(fn($p) => $p->id, $retrievedClient1Projects);
        $retrievedClient2ProjectIds = array_map(fn($p) => $p->id, $retrievedClient2Projects);
        
        foreach ($client1Projects as $project) {
            expect($retrievedClient1ProjectIds)->toContain($project->id)
                ->and($retrievedClient2ProjectIds)->not->toContain($project->id);
        }
        
        foreach ($client2Projects as $project) {
            expect($retrievedClient2ProjectIds)->toContain($project->id)
                ->and($retrievedClient1ProjectIds)->not->toContain($project->id);
        }
        
        // Clean up for next iteration
        foreach ($client1Projects as $project) {
            $this->projectRepo->delete($project->id);
        }
        foreach ($client2Projects as $project) {
            $this->projectRepo->delete($project->id);
        }
        $this->clientRepo->delete($client1->id);
        $this->clientRepo->delete($client2->id);
    }
})->group('property', 'relationship', 'client-project');

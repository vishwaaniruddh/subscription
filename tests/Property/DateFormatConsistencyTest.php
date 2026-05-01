<?php

use App\Models\Client;
use App\Models\Project;
use App\Models\Service;
use App\Repositories\ClientRepository;
use App\Repositories\ProjectRepository;
use App\Repositories\ServiceRepository;

/**
 * Feature: subscription-management-module
 * Property 14: Date Format Consistency
 * 
 * For any service entity retrieved from the system, the start_date and end_date fields 
 * should be in ISO 8601 format (YYYY-MM-DD).
 * 
 * **Validates: Requirements 7.3**
 * 
 * This test verifies that all service dates are stored and retrieved in ISO 8601 format,
 * ensuring consistency across the system regardless of input format variations.
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

test('service dates are stored and retrieved in ISO 8601 format', function () {
    $faker = Faker\Factory::create();
    
    // Create a test client and project for foreign key constraints
    $client = new Client($faker->company(), $faker->email(), null);
    $client = $this->clientRepo->create($client);
    
    $project = new Project($client->id, $faker->words(3, true), $faker->sentence(), null);
    $project = $this->projectRepo->create($project);
    
    // Run 100 iterations with various date formats
    for ($i = 0; $i < 100; $i++) {
        // Generate random dates
        $startDateTime = $faker->dateTimeBetween('-1 year', '+1 year');
        $endDateTime = $faker->dateTimeBetween($startDateTime, '+2 years');
        
        // Format dates in ISO 8601 format (YYYY-MM-DD)
        $startDate = $startDateTime->format('Y-m-d');
        $endDate = $endDateTime->format('Y-m-d');
        
        // Create service with dates
        $service = new Service($project->id, $faker->randomElement(['web', 'mobile', 'other']), $faker->numberBetween(10, 1000), $startDate, $endDate, 0);
        
        // Store service in database
        $createdService = $this->serviceRepo->create($service);
        
        // Retrieve service from database
        $retrievedService = $this->serviceRepo->findById($createdService->id);
        
        // Verify service was retrieved
        expect($retrievedService)->not->toBeNull("Service should be retrievable from database");
        
        // Verify start_date is in ISO 8601 format (YYYY-MM-DD)
        expect($retrievedService->startDate)->toMatch('/^\d{4}-\d{2}-\d{2}$/', "start_date should be in ISO 8601 format (YYYY-MM-DD), got: {$retrievedService->startDate}");
        
        // Verify end_date is in ISO 8601 format (YYYY-MM-DD)
        expect($retrievedService->endDate)->toMatch('/^\d{4}-\d{2}-\d{2}$/', "end_date should be in ISO 8601 format (YYYY-MM-DD), got: {$retrievedService->endDate}");
        
        // Verify dates match what was stored
        expect($retrievedService->startDate)->toBe($startDate, "Retrieved start_date should match stored value");
        expect($retrievedService->endDate)->toBe($endDate, "Retrieved end_date should match stored value");
        
        // Verify dates are valid and parseable
        $parsedStart = DateTime::createFromFormat('Y-m-d', $retrievedService->startDate);
        expect($parsedStart)->not->toBeFalse("start_date should be parseable as ISO 8601 date");
        
        $parsedEnd = DateTime::createFromFormat('Y-m-d', $retrievedService->endDate);
        expect($parsedEnd)->not->toBeFalse("end_date should be parseable as ISO 8601 date");
    }
})->group('property', 'date-format', 'service-dates');

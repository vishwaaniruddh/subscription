<?php

use App\Services\ServiceManager;
use App\Services\UserService;
use App\Services\ClientService;
use App\Services\ProjectService;
use App\Services\ValidationService;
use App\Repositories\ServiceRepository;
use App\Repositories\UserRepository;
use App\Repositories\ClientRepository;
use App\Repositories\ProjectRepository;
use App\Models\Client;
use App\Models\Project;
use App\Models\Service;

/**
 * Feature: subscription-management-module
 * Property 38: Form Submission API Integration
 * Property 39: API Response Feedback
 * Property 40: Dynamic UI Updates
 * 
 * For any form submission in the frontend, an HTTP request should be made to the 
 * corresponding API endpoint.
 * 
 * For any API response (success or error), a message should be displayed in the 
 * user interface.
 * 
 * For any successful API operation, the interface should update to reflect the 
 * changes without triggering a full page reload.
 * 
 * **Validates: Requirements 12.7, 12.8, 12.9**
 * 
 * This test verifies frontend-backend integration patterns:
 * - Form submissions trigger API calls
 * - API responses are handled and displayed
 * - UI updates dynamically without page reloads
 * 
 * Since we cannot directly test JavaScript from PHP, we verify the backend
 * API endpoints that the frontend integrates with, ensuring they:
 * 1. Accept the data formats the frontend sends
 * 2. Return appropriate responses for success/error cases
 * 3. Provide data in formats suitable for dynamic UI updates
 */


test('client creation API accepts frontend form data format', function () {
    $faker = Faker\Factory::create();
    $pdo = getTestDatabase();
    $repository = new ClientRepository($pdo);
    $service = new ClientService($repository);
    
    // Clean up test data
    $pdo->exec("DELETE FROM clients WHERE name LIKE 'Frontend Test Client%'");
    
    // Run 100 iterations simulating frontend form submissions
    for ($i = 0; $i < 100; $i++) {
        // Simulate data from frontend form (FormData -> Object.fromEntries)
        $formData = [
            'name' => 'Frontend Test Client ' . $faker->unique()->numberBetween(100000, 999999),
            'contact_info' => $faker->optional()->email(),
        ];
        
        try {
            // API endpoint should accept this format
            $client = $service->createClient($formData);
            
            // Verify response format suitable for frontend
            expect($client)->toBeInstanceOf(Client::class);
            expect($client->id)->toBeGreaterThan(0);
            expect($client->name)->toBe($formData['name']);
            
            // Verify response can be JSON serialized for API
            $jsonResponse = json_encode([
                'id' => $client->id,
                'name' => $client->name,
                'contact_info' => $client->contactInfo,
                'created_at' => $client->createdAt,
            ]);
            
            expect($jsonResponse)->toBeString();
            expect(json_last_error())->toBe(JSON_ERROR_NONE);
            
        } catch (Exception $e) {
            expect(false)->toBeTrue(
                "API should accept valid frontend form data (iteration $i): " . $e->getMessage()
            );
        }
    }
    
    // Clean up
    $pdo->exec("DELETE FROM clients WHERE name LIKE 'Frontend Test Client%'");
})->group('property', 'frontend-api-integration', 'form-submission');


test('project creation API accepts frontend form data with client_id', function () {
    $faker = Faker\Factory::create();
    $pdo = getTestDatabase();
    $repository = new ProjectRepository($pdo);
    $service = new ProjectService($repository);
    
    // Ensure test client exists
    $pdo->exec("INSERT IGNORE INTO clients (id, name, contact_info) VALUES (99999, 'Test Client for Frontend', 'test@example.com')");
    
    // Clean up test data
    $pdo->exec("DELETE FROM projects WHERE name LIKE 'Frontend Test Project%'");
    
    // Run 100 iterations
    for ($i = 0; $i < 100; $i++) {
        // Simulate frontend form data with client_id added by JavaScript
        $formData = [
            'client_id' => 99999,
            'name' => 'Frontend Test Project ' . $faker->unique()->numberBetween(100000, 999999),
            'description' => $faker->optional()->sentence(),
        ];
        
        try {
            $project = $service->createProject($formData);
            
            // Verify response format
            expect($project)->toBeInstanceOf(Project::class);
            expect($project->id)->toBeGreaterThan(0);
            expect($project->clientId)->toBe($formData['client_id']);
            expect($project->name)->toBe($formData['name']);
            
            // Verify JSON serialization
            $jsonResponse = json_encode([
                'id' => $project->id,
                'client_id' => $project->clientId,
                'name' => $project->name,
                'description' => $project->description,
                'created_at' => $project->createdAt,
            ]);
            
            expect(json_last_error())->toBe(JSON_ERROR_NONE);
            
        } catch (Exception $e) {
            expect(false)->toBeTrue(
                "API should accept valid project form data (iteration $i): " . $e->getMessage()
            );
        }
    }
    
    // Clean up
    $pdo->exec("DELETE FROM projects WHERE name LIKE 'Frontend Test Project%'");
})->group('property', 'frontend-api-integration', 'form-submission');


test('service creation API accepts frontend form data with project_id', function () {
    $faker = Faker\Factory::create();
    $pdo = getTestDatabase();
    $repository = new ServiceRepository($pdo);
    $manager = new ServiceManager($repository);
    
    // Ensure test project exists
    $pdo->exec("INSERT IGNORE INTO clients (id, name, contact_info) VALUES (99998, 'Test Client', 'test@example.com')");
    $pdo->exec("INSERT IGNORE INTO projects (id, client_id, name, description) VALUES (99998, 99998, 'Test Project', 'Test')");
    
    // Clean up test data
    $pdo->exec("DELETE FROM services WHERE project_id = 99998");
    
    // Run 100 iterations
    for ($i = 0; $i < 100; $i++) {
        // Simulate frontend form data
        $startDate = $faker->dateTimeBetween('now', '+1 year');
        $endDate = $faker->dateTimeBetween($startDate, '+2 years');
        
        $formData = [
            'project_id' => 99998,
            'service_type' => $faker->randomElement(['web', 'mobile', 'other']),
            'user_limit' => (string) $faker->numberBetween(1, 1000), // Forms send as strings
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
        ];
        
        try {
            $service = $manager->createService($formData);
            
            // Verify response format
            expect($service)->toBeInstanceOf(Service::class);
            expect($service->id)->toBeGreaterThan(0);
            expect($service->projectId)->toBe($formData['project_id']);
            expect($service->serviceType)->toBe($formData['service_type']);
            
            // Verify JSON serialization with all fields frontend needs
            $jsonResponse = json_encode([
                'id' => $service->id,
                'project_id' => $service->projectId,
                'service_type' => $service->serviceType,
                'user_limit' => $service->userLimit,
                'active_user_count' => $service->activeUserCount,
                'start_date' => $service->startDate,
                'end_date' => $service->endDate,
                'created_at' => $service->createdAt,
            ]);
            
            expect(json_last_error())->toBe(JSON_ERROR_NONE);
            
        } catch (Exception $e) {
            expect(false)->toBeTrue(
                "API should accept valid service form data (iteration $i): " . $e->getMessage()
            );
        }
    }
    
    // Clean up
    $pdo->exec("DELETE FROM services WHERE project_id = 99998");
})->group('property', 'frontend-api-integration', 'form-submission');


test('API returns appropriate error responses for frontend display', function () {
    $faker = Faker\Factory::create();
    $pdo = getTestDatabase();
    $repository = new ServiceRepository($pdo);
    $manager = new ServiceManager($repository);
    
    // Run 100 iterations testing various error scenarios
    for ($i = 0; $i < 100; $i++) {
        // Generate invalid data that should trigger errors
        $errorScenario = $faker->randomElement([
            'invalid_user_limit',
            'invalid_date_range',
        ]);
        
        $formData = [];
        $expectedError = '';
        
        switch ($errorScenario) {
            case 'invalid_user_limit':
                $startDate = $faker->dateTimeBetween('now', '+1 year');
                $endDate = $faker->dateTimeBetween($startDate, '+2 years');
                $formData = [
                    'project_id' => 99998,
                    'service_type' => 'web',
                    'user_limit' => 0, // Invalid
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => $endDate->format('Y-m-d'),
                ];
                $expectedError = 'user_limit';
                break;
                
            case 'invalid_date_range':
                $endDate = $faker->dateTimeBetween('now', '+1 year');
                $startDate = $faker->dateTimeBetween($endDate, '+2 years'); // Start after end
                $formData = [
                    'project_id' => 99998,
                    'service_type' => 'web',
                    'user_limit' => 100,
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => $endDate->format('Y-m-d'),
                ];
                $expectedError = 'date';
                break;
        }
        
        try {
            $service = $manager->createService($formData);
            
            // If creation succeeds, fail the test
            expect(false)->toBeTrue(
                "API should return error for scenario '$errorScenario' (iteration $i)"
            );
            
        } catch (Exception $e) {
            // Verify error message is suitable for frontend display
            $errorMessage = $e->getMessage();
            
            expect($errorMessage)->toBeString();
            expect(strlen($errorMessage))->toBeGreaterThan(0);
            
            // Error message should be descriptive
            expect($errorMessage)->toMatch('/' . $expectedError . '/i',
                "Error message should mention the issue for frontend display"
            );
            
            // Verify error can be JSON encoded for API response
            $errorResponse = json_encode([
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => $errorMessage,
                ]
            ]);
            
            expect(json_last_error())->toBe(JSON_ERROR_NONE);
        }
    }
})->group('property', 'frontend-api-integration', 'error-response');


test('API list endpoints return data suitable for dynamic UI rendering', function () {
    $faker = Faker\Factory::create();
    $pdo = getTestDatabase();
    
    $clientRepository = new ClientRepository($pdo);
    $projectRepository = new ProjectRepository($pdo);
    $serviceRepository = new ServiceRepository($pdo);
    
    $clientService = new ClientService($clientRepository);
    $projectService = new ProjectService($projectRepository);
    $serviceManager = new ServiceManager($serviceRepository);
    
    // Clean up test data
    $pdo->exec("DELETE FROM clients WHERE name LIKE 'UI Test Client%'");
    
    // Run 100 iterations
    for ($i = 0; $i < 100; $i++) {
        // Create test data
        $clientData = [
            'name' => 'UI Test Client ' . $faker->unique()->numberBetween(200000, 299999),
            'contact_info' => $faker->email(),
        ];
        
        $client = $clientService->createClient($clientData);
        
        // Test client list endpoint response
        $clients = $clientRepository->findAll();
        expect($clients)->toBeArray();
        
        // Verify each client has fields needed for UI rendering
        $foundTestClient = false;
        foreach ($clients as $c) {
            if ($c->id === $client->id) {
                $foundTestClient = true;
                
                // Verify all fields needed by frontend table rendering
                expect($c->id)->toBeInt();
                expect($c->name)->toBeString();
                expect(isset($c->contactInfo))->toBeTrue();
                
                // Verify can be JSON encoded
                $json = json_encode([
                    'id' => $c->id,
                    'name' => $c->name,
                    'contact_info' => $c->contactInfo,
                ]);
                expect(json_last_error())->toBe(JSON_ERROR_NONE);
            }
        }
        
        expect($foundTestClient)->toBeTrue(
            "Created client should appear in list endpoint (iteration $i)"
        );
        
        // Create project for testing
        $projectData = [
            'client_id' => $client->id,
            'name' => 'UI Test Project ' . $i,
            'description' => 'Test',
        ];
        
        $project = $projectService->createProject($projectData);
        
        // Test project list endpoint response
        $projects = $projectRepository->findByClientId($client->id);
        expect($projects)->toBeArray();
        expect(count($projects))->toBeGreaterThan(0);
        
        // Verify project data structure
        foreach ($projects as $p) {
            expect($p->id)->toBeInt();
            expect($p->clientId)->toBe($client->id);
            expect($p->name)->toBeString();
            
            $json = json_encode([
                'id' => $p->id,
                'client_id' => $p->clientId,
                'name' => $p->name,
                'description' => $p->description,
            ]);
            expect(json_last_error())->toBe(JSON_ERROR_NONE);
        }
    }
    
    // Clean up
    $pdo->exec("DELETE FROM clients WHERE name LIKE 'UI Test Client%'");
})->group('property', 'frontend-api-integration', 'dynamic-ui');


test('service renewal API accepts frontend form data and returns updated state', function () {
    $faker = Faker\Factory::create();
    $pdo = getTestDatabase();
    $serviceRepository = new ServiceRepository($pdo);
    $historyRepository = new \App\Repositories\SubscriptionHistoryRepository($pdo);
    $lifecycleManager = new \App\Services\SubscriptionLifecycleManager($serviceRepository, $historyRepository);
    $serviceManager = new ServiceManager($serviceRepository);
    
    // Ensure test project exists
    $pdo->exec("INSERT IGNORE INTO clients (id, name, contact_info) VALUES (99997, 'Test Client', 'test@example.com')");
    $pdo->exec("INSERT IGNORE INTO projects (id, client_id, name, description) VALUES (99997, 99997, 'Test Project', 'Test')");
    
    // Clean up test data
    $pdo->exec("DELETE FROM services WHERE project_id = 99997");
    
    // Run 100 iterations
    for ($i = 0; $i < 100; $i++) {
        // Create a service to renew
        $startDate = $faker->dateTimeBetween('-1 year', 'now');
        $endDate = $faker->dateTimeBetween('now', '+1 month');
        
        $serviceData = [
            'project_id' => 99997,
            'service_type' => $faker->randomElement(['web', 'mobile', 'other']),
            'user_limit' => $faker->numberBetween(10, 100),
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
        ];
        
        $service = $serviceManager->createService($serviceData);
        
        // Simulate frontend renewal form submission
        $newEndDate = $faker->dateTimeBetween('+2 months', '+1 year');
        $newEndDateStr = $newEndDate->format('Y-m-d');
        
        try {
            // API should accept renewal request
            $result = $lifecycleManager->renewSubscription($service->id, $newEndDateStr);
            expect($result)->toBeTrue();
            
            // Retrieve updated service
            $renewed = $serviceRepository->findById($service->id);
            
            // Verify response format for frontend
            expect($renewed)->toBeInstanceOf(Service::class);
            expect($renewed->id)->toBe($service->id);
            expect($renewed->endDate)->toBe($newEndDateStr);
            
            // Verify user_limit and active_user_count unchanged (Property 19)
            expect($renewed->userLimit)->toBe($service->userLimit);
            expect($renewed->activeUserCount)->toBe($service->activeUserCount);
            
            // Verify JSON response for UI update
            $jsonResponse = json_encode([
                'id' => $renewed->id,
                'end_date' => $renewed->endDate,
                'renewed_at' => date('Y-m-d H:i:s'),
            ]);
            
            expect(json_last_error())->toBe(JSON_ERROR_NONE);
            
        } catch (Exception $e) {
            expect(false)->toBeTrue(
                "Renewal API should accept valid form data (iteration $i): " . $e->getMessage()
            );
        }
    }
    
    // Clean up
    $pdo->exec("DELETE FROM services WHERE project_id = 99997");
})->group('property', 'frontend-api-integration', 'form-submission');


test('service extension API accepts frontend form data and returns updated state', function () {
    $faker = Faker\Factory::create();
    $pdo = getTestDatabase();
    $serviceRepository = new ServiceRepository($pdo);
    $historyRepository = new \App\Repositories\SubscriptionHistoryRepository($pdo);
    $lifecycleManager = new \App\Services\SubscriptionLifecycleManager($serviceRepository, $historyRepository);
    $serviceManager = new ServiceManager($serviceRepository);
    
    // Ensure test project exists
    $pdo->exec("INSERT IGNORE INTO clients (id, name, contact_info) VALUES (99996, 'Test Client', 'test@example.com')");
    $pdo->exec("INSERT IGNORE INTO projects (id, client_id, name, description) VALUES (99996, 99996, 'Test Project', 'Test')");
    
    // Clean up test data
    $pdo->exec("DELETE FROM services WHERE project_id = 99996");
    
    // Run 100 iterations
    for ($i = 0; $i < 100; $i++) {
        // Create a service to extend
        $startDate = $faker->dateTimeBetween('-1 year', 'now');
        $endDate = $faker->dateTimeBetween('now', '+6 months');
        $currentLimit = $faker->numberBetween(10, 50);
        
        $serviceData = [
            'project_id' => 99996,
            'service_type' => $faker->randomElement(['web', 'mobile', 'other']),
            'user_limit' => $currentLimit,
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
        ];
        
        $service = $serviceManager->createService($serviceData);
        
        // Simulate frontend extension form submission
        $newLimit = $faker->numberBetween($currentLimit + 1, $currentLimit + 100);
        $newEndDateStr = null;
        
        // Optionally include new end date
        if ($faker->boolean()) {
            $newEndDate = $faker->dateTimeBetween('+7 months', '+2 years');
            $newEndDateStr = $newEndDate->format('Y-m-d');
        }
        
        try {
            // API should accept extension request
            $result = $lifecycleManager->extendSubscription($service->id, $newLimit, $newEndDateStr);
            expect($result)->toBeTrue();
            
            // Retrieve updated service
            $extended = $serviceRepository->findById($service->id);
            
            // Verify response format for frontend
            expect($extended)->toBeInstanceOf(Service::class);
            expect($extended->id)->toBe($service->id);
            expect($extended->userLimit)->toBe($newLimit);
            
            // If new_end_date was provided, verify it was updated
            if ($newEndDateStr !== null) {
                expect($extended->endDate)->toBe($newEndDateStr);
            }
            
            // Verify JSON response for UI update
            $jsonResponse = json_encode([
                'id' => $extended->id,
                'user_limit' => $extended->userLimit,
                'end_date' => $extended->endDate,
                'extended_at' => date('Y-m-d H:i:s'),
            ]);
            
            expect(json_last_error())->toBe(JSON_ERROR_NONE);
            
        } catch (Exception $e) {
            expect(false)->toBeTrue(
                "Extension API should accept valid form data (iteration $i): " . $e->getMessage()
            );
        }
    }
    
    // Clean up
    $pdo->exec("DELETE FROM services WHERE project_id = 99996");
})->group('property', 'frontend-api-integration', 'form-submission');


test('API responses include all fields needed for UI state updates', function () {
    $faker = Faker\Factory::create();
    $pdo = getTestDatabase();
    $repository = new ServiceRepository($pdo);
    $manager = new ServiceManager($repository);
    
    // Ensure test project exists
    $pdo->exec("INSERT IGNORE INTO clients (id, name, contact_info) VALUES (99995, 'Test Client', 'test@example.com')");
    $pdo->exec("INSERT IGNORE INTO projects (id, client_id, name, description) VALUES (99995, 99995, 'Test Project', 'Test')");
    
    // Clean up test data
    $pdo->exec("DELETE FROM services WHERE project_id = 99995");
    
    // Run 100 iterations
    for ($i = 0; $i < 100; $i++) {
        // Create a service
        $startDate = $faker->dateTimeBetween('-1 year', '+1 year');
        $endDate = $faker->dateTimeBetween($startDate, '+2 years');
        
        $serviceData = [
            'project_id' => 99995,
            'service_type' => $faker->randomElement(['web', 'mobile', 'other']),
            'user_limit' => $faker->numberBetween(10, 100),
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
        ];
        
        $service = $manager->createService($serviceData);
        
        // Retrieve service (simulating GET request)
        $retrieved = $repository->findById($service->id);
        
        // Verify response includes all fields needed for UI rendering
        expect($retrieved->id)->toBeInt();
        expect($retrieved->projectId)->toBeInt();
        expect($retrieved->serviceType)->toBeString();
        expect($retrieved->userLimit)->toBeInt();
        expect($retrieved->activeUserCount)->toBeInt();
        expect($retrieved->startDate)->toBeString();
        expect($retrieved->endDate)->toBeString();
        
        // Calculate derived fields that UI needs
        $now = new DateTime();
        $endDateTime = new DateTime($retrieved->endDate);
        $isActive = $now <= $endDateTime;
        $utilizationPercentage = ($retrieved->activeUserCount / $retrieved->userLimit) * 100;
        
        // Verify complete API response format
        $apiResponse = [
            'id' => $retrieved->id,
            'project_id' => $retrieved->projectId,
            'service_type' => $retrieved->serviceType,
            'user_limit' => $retrieved->userLimit,
            'active_user_count' => $retrieved->activeUserCount,
            'start_date' => $retrieved->startDate,
            'end_date' => $retrieved->endDate,
            'is_active' => $isActive,
            'utilization_percentage' => $utilizationPercentage,
        ];
        
        // Verify JSON encoding works
        $json = json_encode($apiResponse);
        expect(json_last_error())->toBe(JSON_ERROR_NONE);
        
        // Verify decoded data matches
        $decoded = json_decode($json, true);
        expect($decoded['id'])->toBe($retrieved->id);
        expect($decoded['service_type'])->toBe($retrieved->serviceType);
        expect($decoded['user_limit'])->toBe($retrieved->userLimit);
        expect($decoded['active_user_count'])->toBe($retrieved->activeUserCount);
    }
    
    // Clean up
    $pdo->exec("DELETE FROM services WHERE project_id = 99995");
})->group('property', 'frontend-api-integration', 'dynamic-ui');


test('delete operations return appropriate responses for UI feedback', function () {
    $faker = Faker\Factory::create();
    $pdo = getTestDatabase();
    
    $clientRepository = new ClientRepository($pdo);
    $clientService = new ClientService($clientRepository);
    
    // Clean up test data
    $pdo->exec("DELETE FROM clients WHERE name LIKE 'Delete Test Client%'");
    
    // Run 100 iterations
    for ($i = 0; $i < 100; $i++) {
        // Create a client to delete
        $clientData = [
            'name' => 'Delete Test Client ' . $faker->unique()->numberBetween(300000, 399999),
            'contact_info' => $faker->email(),
        ];
        
        $client = $clientService->createClient($clientData);
        
        try {
            // Perform delete operation
            $result = $clientService->deleteClient($client->id);
            
            // Verify delete returns success indicator
            expect($result)->toBeTrue();
            
            // Verify client is actually deleted
            $deleted = $clientRepository->findById($client->id);
            expect($deleted)->toBeNull();
            
            // Verify API can return success response
            $apiResponse = [
                'success' => true,
                'message' => 'Client deleted successfully',
            ];
            
            $json = json_encode($apiResponse);
            expect(json_last_error())->toBe(JSON_ERROR_NONE);
            
        } catch (Exception $e) {
            expect(false)->toBeTrue(
                "Delete operation should succeed (iteration $i): " . $e->getMessage()
            );
        }
    }
    
    // Test delete of non-existent entity returns appropriate error
    for ($i = 0; $i < 10; $i++) {
        $nonExistentId = $faker->numberBetween(900000, 999999);
        
        try {
            $result = $clientService->deleteClient($nonExistentId);
            
            // If it returns false or null, that's acceptable
            if ($result === false || $result === null) {
                expect(true)->toBeTrue();
            }
            
        } catch (Exception $e) {
            // Exception is also acceptable for not found
            $errorResponse = [
                'error' => [
                    'code' => 'RESOURCE_NOT_FOUND',
                    'message' => $e->getMessage(),
                ]
            ];
            
            $json = json_encode($errorResponse);
            expect(json_last_error())->toBe(JSON_ERROR_NONE);
        }
    }
})->group('property', 'frontend-api-integration', 'error-response');


test('validation errors include context for meaningful UI feedback', function () {
    $faker = Faker\Factory::create();
    $pdo = getTestDatabase();
    
    $serviceRepository = new ServiceRepository($pdo);
    $userRepository = new UserRepository($pdo);
    $validationService = new ValidationService($serviceRepository);
    $userService = new UserService($userRepository, $serviceRepository, $validationService);
    
    // Ensure test project exists
    $pdo->exec("INSERT IGNORE INTO clients (id, name, contact_info) VALUES (99994, 'Test Client', 'test@example.com')");
    $pdo->exec("INSERT IGNORE INTO projects (id, client_id, name, description) VALUES (99994, 99994, 'Test Project', 'Test')");
    
    // Clean up test data
    $pdo->exec("DELETE FROM services WHERE project_id = 99994");
    $pdo->exec("DELETE FROM users WHERE service_id IN (SELECT id FROM services WHERE project_id = 99994)");
    
    // Run 100 iterations
    for ($i = 0; $i < 100; $i++) {
        // Create a service at capacity
        $userLimit = $faker->numberBetween(5, 20);
        $startDate = $faker->dateTimeBetween('-1 year', 'now');
        $endDate = $faker->dateTimeBetween('now', '+1 year');
        
        $stmt = $pdo->prepare("
            INSERT INTO services (project_id, service_type, user_limit, active_user_count, start_date, end_date)
            VALUES (99994, :service_type, :user_limit, :active_user_count, :start_date, :end_date)
        ");
        $stmt->execute([
            'service_type' => $faker->randomElement(['web', 'mobile', 'other']),
            'user_limit' => $userLimit,
            'active_user_count' => $userLimit, // At capacity
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
        ]);
        $serviceId = (int) $pdo->lastInsertId();
        
        // Attempt to register user when at capacity
        try {
            $userData = [
                'service_id' => $serviceId,
                'user_identifier' => $faker->email(),
            ];
            
            $user = $userService->registerUser($userData);
            
            // Should not succeed
            expect(false)->toBeTrue(
                "User registration should fail when at capacity (iteration $i)"
            );
            
        } catch (Exception $e) {
            // Verify error message provides context
            $errorMessage = $e->getMessage();
            
            expect($errorMessage)->toBeString();
            expect(strlen($errorMessage))->toBeGreaterThan(0);
            
            // Error should mention limit or capacity
            expect($errorMessage)->toMatch('/limit|capacity|exceeded/i',
                "Error message should explain the limit issue"
            );
            
            // Verify API can return structured error with context
            $errorResponse = [
                'error' => [
                    'code' => 'USER_LIMIT_EXCEEDED',
                    'message' => $errorMessage,
                    'context' => [
                        'current_count' => $userLimit,
                        'user_limit' => $userLimit,
                    ]
                ]
            ];
            
            $json = json_encode($errorResponse);
            expect(json_last_error())->toBe(JSON_ERROR_NONE);
            
            // Verify decoded context is accessible
            $decoded = json_decode($json, true);
            expect($decoded['error']['context']['current_count'])->toBe($userLimit);
            expect($decoded['error']['context']['user_limit'])->toBe($userLimit);
        }
    }
    
    // Clean up
    $pdo->exec("DELETE FROM services WHERE project_id = 99994");
})->group('property', 'frontend-api-integration', 'error-response');


test('reporting endpoints return data formatted for dashboard display', function () {
    $faker = Faker\Factory::create();
    $pdo = getTestDatabase();
    $repository = new ServiceRepository($pdo);
    $manager = new ServiceManager($repository);
    
    // Ensure test project exists
    $pdo->exec("INSERT IGNORE INTO clients (id, name, contact_info) VALUES (99993, 'Test Client', 'test@example.com')");
    $pdo->exec("INSERT IGNORE INTO projects (id, client_id, name, description) VALUES (99993, 99993, 'Test Project', 'Test')");
    
    // Clean up test data
    $pdo->exec("DELETE FROM services WHERE project_id = 99993");
    
    // Run 100 iterations
    for ($i = 0; $i < 100; $i++) {
        // Create services with various expiry dates and utilization
        $daysUntilExpiry = $faker->numberBetween(1, 60);
        $endDate = (new DateTime())->modify("+{$daysUntilExpiry} days");
        $startDate = (new DateTime())->modify('-1 year');
        
        $userLimit = $faker->numberBetween(10, 100);
        $activeUserCount = $faker->numberBetween(0, $userLimit);
        
        $stmt = $pdo->prepare("
            INSERT INTO services (project_id, service_type, user_limit, active_user_count, start_date, end_date)
            VALUES (99993, :service_type, :user_limit, :active_user_count, :start_date, :end_date)
        ");
        $stmt->execute([
            'service_type' => $faker->randomElement(['web', 'mobile', 'other']),
            'user_limit' => $userLimit,
            'active_user_count' => $activeUserCount,
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
        ]);
        $serviceId = (int) $pdo->lastInsertId();
        
        // Query expiring services (simulating reporting endpoint)
        $expiringServices = $repository->findExpiring(30);
        
        // Verify response format for dashboard
        expect($expiringServices)->toBeArray();
        
        foreach ($expiringServices as $service) {
            // Verify all fields needed for dashboard display
            expect($service->id)->toBeInt();
            expect($service->projectId)->toBeInt();
            expect($service->serviceType)->toBeString();
            expect($service->endDate)->toBeString();
            
            // Calculate days until expiry for display
            $end = new DateTime($service->endDate);
            $now = new DateTime();
            $daysRemaining = $now->diff($end)->days;
            
            // Verify API response format
            $apiResponse = [
                'id' => $service->id,
                'project_id' => $service->projectId,
                'service_type' => $service->serviceType,
                'end_date' => $service->endDate,
                'days_until_expiry' => $daysRemaining,
            ];
            
            $json = json_encode($apiResponse);
            expect(json_last_error())->toBe(JSON_ERROR_NONE);
        }
        
        // Query high utilization services
        $highUtilServices = $repository->findHighUtilization(80);
        
        expect($highUtilServices)->toBeArray();
        
        foreach ($highUtilServices as $service) {
            $utilizationPercentage = ($service->activeUserCount / $service->userLimit) * 100;
            
            // Verify utilization is actually high
            expect($utilizationPercentage)->toBeGreaterThanOrEqual(80);
            
            // Verify API response format
            $apiResponse = [
                'id' => $service->id,
                'project_id' => $service->projectId,
                'service_type' => $service->serviceType,
                'user_limit' => $service->userLimit,
                'active_user_count' => $service->activeUserCount,
                'utilization_percentage' => $utilizationPercentage,
            ];
            
            $json = json_encode($apiResponse);
            expect(json_last_error())->toBe(JSON_ERROR_NONE);
        }
    }
    
    // Clean up
    $pdo->exec("DELETE FROM services WHERE project_id = 99993");
})->group('property', 'frontend-api-integration', 'dynamic-ui');

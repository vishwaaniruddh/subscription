<?php

use App\Services\ClientService;
use App\Services\ProjectService;
use App\Services\ServiceManager;
use App\Services\UserService;
use App\Services\ValidationService;
use App\Services\SubscriptionLifecycleManager;
use App\Services\Logger;
use App\Repositories\ClientRepository;
use App\Repositories\ProjectRepository;
use App\Repositories\ServiceRepository;
use App\Repositories\UserRepository;
use App\Repositories\SubscriptionHistoryRepository;
use App\Database;

beforeEach(function () {
    $this->db = Database::getInstance();
    
    // Use a temporary directory for test logs
    $this->testLogDir = sys_get_temp_dir() . '/subscription_workflow_logs_' . uniqid();
    $this->logger = new Logger($this->testLogDir);
    
    // Create repositories
    $this->clientRepo = new ClientRepository($this->db, $this->logger);
    $this->projectRepo = new ProjectRepository($this->db, $this->logger);
    $this->serviceRepo = new ServiceRepository($this->db, $this->logger);
    $this->userRepo = new UserRepository($this->db, $this->logger);
    $this->historyRepo = new SubscriptionHistoryRepository($this->db, $this->logger);
    
    // Create services
    $this->clientService = new ClientService($this->clientRepo);
    $this->projectService = new ProjectService($this->projectRepo);
    $this->serviceManager = new ServiceManager($this->serviceRepo);
    $this->validationService = new ValidationService($this->serviceRepo, $this->logger);
    $this->userService = new UserService($this->userRepo, $this->serviceRepo, $this->validationService);
    $this->lifecycleManager = new SubscriptionLifecycleManager($this->serviceRepo, $this->historyRepo);
    
    // Clean up any existing test data
    $this->db->exec("DELETE FROM users WHERE service_id IN (SELECT id FROM services WHERE project_id IN (SELECT id FROM projects WHERE client_id >= 8000))");
    $this->db->exec("DELETE FROM subscription_history WHERE service_id IN (SELECT id FROM services WHERE project_id IN (SELECT id FROM projects WHERE client_id >= 8000))");
    $this->db->exec("DELETE FROM services WHERE project_id IN (SELECT id FROM projects WHERE client_id >= 8000)");
    $this->db->exec("DELETE FROM projects WHERE client_id >= 8000");
    $this->db->exec("DELETE FROM clients WHERE id >= 8000");
});

afterEach(function () {
    // Clean up test data
    $this->db->exec("DELETE FROM users WHERE service_id IN (SELECT id FROM services WHERE project_id IN (SELECT id FROM projects WHERE client_id >= 8000))");
    $this->db->exec("DELETE FROM subscription_history WHERE service_id IN (SELECT id FROM services WHERE project_id IN (SELECT id FROM projects WHERE client_id >= 8000))");
    $this->db->exec("DELETE FROM services WHERE project_id IN (SELECT id FROM projects WHERE client_id >= 8000)");
    $this->db->exec("DELETE FROM projects WHERE client_id >= 8000");
    $this->db->exec("DELETE FROM clients WHERE id >= 8000");
    
    // Clean up test logs
    if ($this->logger) {
        $this->logger->clearLog();
    }
    if (is_dir($this->testLogDir)) {
        rmdir($this->testLogDir);
    }
});

test('complete end-to-end client → project → service → user creation workflow', function () {
    // Step 1: Create a client
    $client = $this->clientService->createClient([
        'name' => 'Acme Corporation',
        'contact_info' => 'contact@acme.com'
    ]);
    
    expect($client)->not->toBeNull();
    expect($client->id)->toBeGreaterThan(0);
    expect($client->name)->toBe('Acme Corporation');
    expect($client->contactInfo)->toBe('contact@acme.com');
    
    // Step 2: Create a project under the client
    $project = $this->projectService->createProject([
        'client_id' => $client->id,
        'name' => 'E-Commerce Platform',
        'description' => 'Online shopping platform'
    ]);
    
    expect($project)->not->toBeNull();
    expect($project->id)->toBeGreaterThan(0);
    expect($project->clientId)->toBe($client->id);
    expect($project->name)->toBe('E-Commerce Platform');
    
    // Step 3: Create a service under the project
    $service = $this->serviceManager->createService([
        'project_id' => $project->id,
        'service_type' => 'web',
        'user_limit' => 5,
        'active_user_count' => 0,
        'start_date' => date('Y-m-d', strtotime('-1 day')),
        'end_date' => date('Y-m-d', strtotime('+30 days'))
    ]);
    
    expect($service)->not->toBeNull();
    expect($service->id)->toBeGreaterThan(0);
    expect($service->projectId)->toBe($project->id);
    expect($service->serviceType)->toBe('web');
    expect($service->userLimit)->toBe(5);
    expect($service->activeUserCount)->toBe(0);
    
    // Step 4: Validate user creation (should succeed)
    $validation = $this->validationService->validateUserCreation($service->id);
    expect($validation['success'])->toBeTrue();
    
    // Step 5: Register users
    $user1 = $this->userService->registerUser([
        'service_id' => $service->id,
        'user_identifier' => 'user1@acme.com',
        'status' => 'active'
    ]);
    
    expect($user1)->not->toBeNull();
    expect($user1->id)->toBeGreaterThan(0);
    expect($user1->serviceId)->toBe($service->id);
    expect($user1->userIdentifier)->toBe('user1@acme.com');
    expect($user1->status)->toBe('active');
    
    // Verify active user count increased
    $updatedService = $this->serviceManager->getService($service->id);
    expect($updatedService->activeUserCount)->toBe(1);
    
    // Register second user
    $user2 = $this->userService->registerUser([
        'service_id' => $service->id,
        'user_identifier' => 'user2@acme.com',
        'status' => 'active'
    ]);
    
    expect($user2)->not->toBeNull();
    $updatedService = $this->serviceManager->getService($service->id);
    expect($updatedService->activeUserCount)->toBe(2);
    
    // Step 6: Verify relationships
    $retrievedClient = $this->clientService->getClient($client->id);
    expect($retrievedClient)->not->toBeNull();
    
    $clientProjects = $this->projectService->listProjectsByClient($client->id);
    expect($clientProjects)->toHaveCount(1);
    expect($clientProjects[0]->id)->toBe($project->id);
    
    $projectServices = $this->serviceManager->listServicesByProject($project->id);
    expect($projectServices)->toHaveCount(1);
    expect($projectServices[0]->id)->toBe($service->id);
    
    $serviceUsers = $this->userService->listActiveUsers($service->id);
    expect($serviceUsers)->toHaveCount(2);
});

test('subscription renewal workflow preserves user limits and counts', function () {
    // Create client → project → service
    $client = $this->clientService->createClient([
        'name' => 'Tech Startup',
        'contact_info' => 'info@techstartup.com'
    ]);
    
    $project = $this->projectService->createProject([
        'client_id' => $client->id,
        'name' => 'Mobile App',
        'description' => 'iOS and Android app'
    ]);
    
    $originalEndDate = date('Y-m-d', strtotime('+30 days'));
    $service = $this->serviceManager->createService([
        'project_id' => $project->id,
        'service_type' => 'mobile',
        'user_limit' => 10,
        'active_user_count' => 0,
        'start_date' => date('Y-m-d', strtotime('-1 day')),
        'end_date' => $originalEndDate
    ]);
    
    // Register some users
    $this->userService->registerUser([
        'service_id' => $service->id,
        'user_identifier' => 'user1@techstartup.com',
        'status' => 'active'
    ]);
    
    $this->userService->registerUser([
        'service_id' => $service->id,
        'user_identifier' => 'user2@techstartup.com',
        'status' => 'active'
    ]);
    
    $this->userService->registerUser([
        'service_id' => $service->id,
        'user_identifier' => 'user3@techstartup.com',
        'status' => 'active'
    ]);
    
    // Verify initial state
    $serviceBeforeRenewal = $this->serviceManager->getService($service->id);
    expect($serviceBeforeRenewal->userLimit)->toBe(10);
    expect($serviceBeforeRenewal->activeUserCount)->toBe(3);
    
    // Renew subscription
    $newEndDate = date('Y-m-d', strtotime('+60 days'));
    $result = $this->lifecycleManager->renewSubscription($service->id, $newEndDate);
    expect($result)->toBeTrue();
    
    // Verify renewal preserved user limit and count
    $serviceAfterRenewal = $this->serviceManager->getService($service->id);
    expect($serviceAfterRenewal->userLimit)->toBe(10);
    expect($serviceAfterRenewal->activeUserCount)->toBe(3);
    expect($serviceAfterRenewal->endDate)->toBe($newEndDate);
    
    // Verify history record was created
    $history = $this->historyRepo->findByServiceId($service->id);
    expect($history)->toHaveCount(1);
    expect($history[0]->actionType)->toBe('RENEWAL');
    expect($history[0]->oldValue)->toBe($originalEndDate);
    expect($history[0]->newValue)->toBe($newEndDate);
});

test('subscription extension workflow increases user limit', function () {
    // Create client → project → service
    $client = $this->clientService->createClient([
        'name' => 'Growing Business',
        'contact_info' => 'contact@growingbiz.com'
    ]);
    
    $project = $this->projectService->createProject([
        'client_id' => $client->id,
        'name' => 'Web Portal',
        'description' => 'Customer portal'
    ]);
    
    $service = $this->serviceManager->createService([
        'project_id' => $project->id,
        'service_type' => 'web',
        'user_limit' => 3,
        'active_user_count' => 0,
        'start_date' => date('Y-m-d', strtotime('-1 day')),
        'end_date' => date('Y-m-d', strtotime('+30 days'))
    ]);
    
    // Register users up to limit
    $this->userService->registerUser([
        'service_id' => $service->id,
        'user_identifier' => 'user1@growingbiz.com',
        'status' => 'active'
    ]);
    
    $this->userService->registerUser([
        'service_id' => $service->id,
        'user_identifier' => 'user2@growingbiz.com',
        'status' => 'active'
    ]);
    
    $this->userService->registerUser([
        'service_id' => $service->id,
        'user_identifier' => 'user3@growingbiz.com',
        'status' => 'active'
    ]);
    
    // Verify at capacity
    $serviceAtCapacity = $this->serviceManager->getService($service->id);
    expect($serviceAtCapacity->activeUserCount)->toBe(3);
    expect($serviceAtCapacity->userLimit)->toBe(3);
    
    // Validation should fail
    $validation = $this->validationService->validateUserCreation($service->id);
    expect($validation['success'])->toBeFalse();
    expect($validation['error_code'])->toBe('USER_LIMIT_EXCEEDED');
    
    // Extend subscription to increase limit
    $result = $this->lifecycleManager->extendSubscription($service->id, 10);
    expect($result)->toBeTrue();
    
    // Verify limit increased
    $serviceAfterExtension = $this->serviceManager->getService($service->id);
    expect($serviceAfterExtension->userLimit)->toBe(10);
    expect($serviceAfterExtension->activeUserCount)->toBe(3);
    
    // Validation should now succeed
    $validationAfter = $this->validationService->validateUserCreation($service->id);
    expect($validationAfter['success'])->toBeTrue();
    
    // Register additional user
    $user4 = $this->userService->registerUser([
        'service_id' => $service->id,
        'user_identifier' => 'user4@growingbiz.com',
        'status' => 'active'
    ]);
    
    expect($user4)->not->toBeNull();
    
    $finalService = $this->serviceManager->getService($service->id);
    expect($finalService->activeUserCount)->toBe(4);
    
    // Verify history record
    $history = $this->historyRepo->findByServiceId($service->id);
    expect($history)->toHaveCount(1);
    expect($history[0]->actionType)->toBe('EXTENSION');
});

test('subscription extension with date change updates both limit and end date', function () {
    // Create client → project → service
    $client = $this->clientService->createClient([
        'name' => 'Enterprise Client',
        'contact_info' => 'admin@enterprise.com'
    ]);
    
    $project = $this->projectService->createProject([
        'client_id' => $client->id,
        'name' => 'Enterprise System',
        'description' => 'Large scale system'
    ]);
    
    $originalEndDate = date('Y-m-d', strtotime('+30 days'));
    $service = $this->serviceManager->createService([
        'project_id' => $project->id,
        'service_type' => 'other',
        'user_limit' => 5,
        'active_user_count' => 0,
        'start_date' => date('Y-m-d', strtotime('-1 day')),
        'end_date' => $originalEndDate
    ]);
    
    // Register a user
    $this->userService->registerUser([
        'service_id' => $service->id,
        'user_identifier' => 'admin@enterprise.com',
        'status' => 'active'
    ]);
    
    // Extend with both new limit and new end date
    $newEndDate = date('Y-m-d', strtotime('+90 days'));
    $result = $this->lifecycleManager->extendSubscription($service->id, 20, $newEndDate);
    expect($result)->toBeTrue();
    
    // Verify both changed
    $updatedService = $this->serviceManager->getService($service->id);
    expect($updatedService->userLimit)->toBe(20);
    expect($updatedService->endDate)->toBe($newEndDate);
    expect($updatedService->activeUserCount)->toBe(1);
});

test('validation error workflow for user limit exceeded', function () {
    // Create client → project → service with limit of 2
    $client = $this->clientService->createClient([
        'name' => 'Small Business',
        'contact_info' => 'owner@smallbiz.com'
    ]);
    
    $project = $this->projectService->createProject([
        'client_id' => $client->id,
        'name' => 'Website',
        'description' => 'Company website'
    ]);
    
    $service = $this->serviceManager->createService([
        'project_id' => $project->id,
        'service_type' => 'web',
        'user_limit' => 2,
        'active_user_count' => 0,
        'start_date' => date('Y-m-d', strtotime('-1 day')),
        'end_date' => date('Y-m-d', strtotime('+30 days'))
    ]);
    
    // Register users up to limit
    $this->userService->registerUser([
        'service_id' => $service->id,
        'user_identifier' => 'user1@smallbiz.com',
        'status' => 'active'
    ]);
    
    $this->userService->registerUser([
        'service_id' => $service->id,
        'user_identifier' => 'user2@smallbiz.com',
        'status' => 'active'
    ]);
    
    // Attempt to register third user (should fail)
    try {
        $this->userService->registerUser([
            'service_id' => $service->id,
            'user_identifier' => 'user3@smallbiz.com',
            'status' => 'active'
        ]);
        expect(false)->toBeTrue('Should have thrown an exception');
    } catch (Exception $e) {
        expect($e->getMessage())->toContain('user limit');
    }
    
    // Verify count didn't change
    $finalService = $this->serviceManager->getService($service->id);
    expect($finalService->activeUserCount)->toBe(2);
    
    // Verify only 2 users exist
    $users = $this->userService->listActiveUsers($service->id);
    expect($users)->toHaveCount(2);
});

test('validation error workflow for expired subscription', function () {
    // Create client → project → expired service
    $client = $this->clientService->createClient([
        'name' => 'Expired Client',
        'contact_info' => 'contact@expired.com'
    ]);
    
    $project = $this->projectService->createProject([
        'client_id' => $client->id,
        'name' => 'Old Project',
        'description' => 'Project with expired subscription'
    ]);
    
    $service = $this->serviceManager->createService([
        'project_id' => $project->id,
        'service_type' => 'mobile',
        'user_limit' => 10,
        'active_user_count' => 0,
        'start_date' => date('Y-m-d', strtotime('-60 days')),
        'end_date' => date('Y-m-d', strtotime('-1 day'))
    ]);
    
    // Attempt to validate user creation (should fail)
    $validation = $this->validationService->validateUserCreation($service->id);
    expect($validation['success'])->toBeFalse();
    expect($validation['error_code'])->toBe('SUBSCRIPTION_EXPIRED');
    
    // Attempt to register user (should fail)
    try {
        $this->userService->registerUser([
            'service_id' => $service->id,
            'user_identifier' => 'user@expired.com',
            'status' => 'active'
        ]);
        expect(false)->toBeTrue('Should have thrown an exception');
    } catch (Exception $e) {
        expect($e->getMessage())->toContain('expired');
    }
    
    // Verify no users were created
    $users = $this->userService->listActiveUsers($service->id);
    expect($users)->toHaveCount(0);
    
    // Renew subscription
    $newEndDate = date('Y-m-d', strtotime('+30 days'));
    $this->lifecycleManager->renewSubscription($service->id, $newEndDate);
    
    // Now validation should succeed
    $validationAfterRenewal = $this->validationService->validateUserCreation($service->id);
    expect($validationAfterRenewal['success'])->toBeTrue();
    
    // User registration should now work
    $user = $this->userService->registerUser([
        'service_id' => $service->id,
        'user_identifier' => 'user@expired.com',
        'status' => 'active'
    ]);
    
    expect($user)->not->toBeNull();
    expect($user->status)->toBe('active');
});

test('user deactivation workflow frees up capacity', function () {
    // Create client → project → service
    $client = $this->clientService->createClient([
        'name' => 'Capacity Test Client',
        'contact_info' => 'test@capacity.com'
    ]);
    
    $project = $this->projectService->createProject([
        'client_id' => $client->id,
        'name' => 'Capacity Project',
        'description' => 'Testing capacity management'
    ]);
    
    $service = $this->serviceManager->createService([
        'project_id' => $project->id,
        'service_type' => 'web',
        'user_limit' => 2,
        'active_user_count' => 0,
        'start_date' => date('Y-m-d', strtotime('-1 day')),
        'end_date' => date('Y-m-d', strtotime('+30 days'))
    ]);
    
    // Register users to capacity
    $user1 = $this->userService->registerUser([
        'service_id' => $service->id,
        'user_identifier' => 'user1@capacity.com',
        'status' => 'active'
    ]);
    
    $user2 = $this->userService->registerUser([
        'service_id' => $service->id,
        'user_identifier' => 'user2@capacity.com',
        'status' => 'active'
    ]);
    
    // Verify at capacity
    $serviceAtCapacity = $this->serviceManager->getService($service->id);
    expect($serviceAtCapacity->activeUserCount)->toBe(2);
    
    // Validation should fail
    $validation = $this->validationService->validateUserCreation($service->id);
    expect($validation['success'])->toBeFalse();
    
    // Deactivate one user
    $result = $this->userService->deactivateUser($user1->id);
    expect($result)->toBeTrue();
    
    // Verify capacity freed
    $serviceAfterDeactivation = $this->serviceManager->getService($service->id);
    expect($serviceAfterDeactivation->activeUserCount)->toBe(1);
    
    // Validation should now succeed
    $validationAfter = $this->validationService->validateUserCreation($service->id);
    expect($validationAfter['success'])->toBeTrue();
    
    // Register new user
    $user3 = $this->userService->registerUser([
        'service_id' => $service->id,
        'user_identifier' => 'user3@capacity.com',
        'status' => 'active'
    ]);
    
    expect($user3)->not->toBeNull();
    
    // Verify final state
    $finalService = $this->serviceManager->getService($service->id);
    expect($finalService->activeUserCount)->toBe(2);
    
    $activeUsers = $this->userService->listActiveUsers($service->id);
    expect($activeUsers)->toHaveCount(2);
});

test('extension validation error workflow when new limit below active count', function () {
    // Create client → project → service
    $client = $this->clientService->createClient([
        'name' => 'Extension Test Client',
        'contact_info' => 'test@extension.com'
    ]);
    
    $project = $this->projectService->createProject([
        'client_id' => $client->id,
        'name' => 'Extension Project',
        'description' => 'Testing extension validation'
    ]);
    
    $service = $this->serviceManager->createService([
        'project_id' => $project->id,
        'service_type' => 'web',
        'user_limit' => 10,
        'active_user_count' => 0,
        'start_date' => date('Y-m-d', strtotime('-1 day')),
        'end_date' => date('Y-m-d', strtotime('+30 days'))
    ]);
    
    // Register 5 users
    for ($i = 1; $i <= 5; $i++) {
        $this->userService->registerUser([
            'service_id' => $service->id,
            'user_identifier' => "user{$i}@extension.com",
            'status' => 'active'
        ]);
    }
    
    // Verify 5 active users
    $serviceWithUsers = $this->serviceManager->getService($service->id);
    expect($serviceWithUsers->activeUserCount)->toBe(5);
    
    // Attempt to extend with new limit below active count (should fail)
    try {
        $this->lifecycleManager->extendSubscription($service->id, 3);
        expect(false)->toBeTrue('Should have thrown an exception');
    } catch (Exception $e) {
        expect($e->getMessage())->toContain('cannot be less than current active user count');
    }
    
    // Verify limit unchanged
    $serviceAfterFailedExtension = $this->serviceManager->getService($service->id);
    expect($serviceAfterFailedExtension->userLimit)->toBe(10);
    expect($serviceAfterFailedExtension->activeUserCount)->toBe(5);
    
    // Extension with valid limit should succeed
    $result = $this->lifecycleManager->extendSubscription($service->id, 5);
    expect($result)->toBeTrue();
    
    $serviceAfterValidExtension = $this->serviceManager->getService($service->id);
    expect($serviceAfterValidExtension->userLimit)->toBe(5);
    expect($serviceAfterValidExtension->activeUserCount)->toBe(5);
});

test('complex workflow with multiple projects and services', function () {
    // Create client with multiple projects
    $client = $this->clientService->createClient([
        'name' => 'Multi-Project Client',
        'contact_info' => 'admin@multiproject.com'
    ]);
    
    // Create first project with web service
    $project1 = $this->projectService->createProject([
        'client_id' => $client->id,
        'name' => 'Web Application',
        'description' => 'Main web app'
    ]);
    
    $webService = $this->serviceManager->createService([
        'project_id' => $project1->id,
        'service_type' => 'web',
        'user_limit' => 5,
        'active_user_count' => 0,
        'start_date' => date('Y-m-d', strtotime('-1 day')),
        'end_date' => date('Y-m-d', strtotime('+30 days'))
    ]);
    
    // Create second project with mobile service
    $project2 = $this->projectService->createProject([
        'client_id' => $client->id,
        'name' => 'Mobile Application',
        'description' => 'iOS and Android apps'
    ]);
    
    $mobileService = $this->serviceManager->createService([
        'project_id' => $project2->id,
        'service_type' => 'mobile',
        'user_limit' => 3,
        'active_user_count' => 0,
        'start_date' => date('Y-m-d', strtotime('-1 day')),
        'end_date' => date('Y-m-d', strtotime('+30 days'))
    ]);
    
    // Register users to web service
    $this->userService->registerUser([
        'service_id' => $webService->id,
        'user_identifier' => 'webuser1@multiproject.com',
        'status' => 'active'
    ]);
    
    $this->userService->registerUser([
        'service_id' => $webService->id,
        'user_identifier' => 'webuser2@multiproject.com',
        'status' => 'active'
    ]);
    
    // Register users to mobile service
    $this->userService->registerUser([
        'service_id' => $mobileService->id,
        'user_identifier' => 'mobileuser1@multiproject.com',
        'status' => 'active'
    ]);
    
    // Verify client has 2 projects
    $clientProjects = $this->projectService->listProjectsByClient($client->id);
    expect($clientProjects)->toHaveCount(2);
    
    // Verify each project has 1 service
    $project1Services = $this->serviceManager->listServicesByProject($project1->id);
    expect($project1Services)->toHaveCount(1);
    expect($project1Services[0]->serviceType)->toBe('web');
    
    $project2Services = $this->serviceManager->listServicesByProject($project2->id);
    expect($project2Services)->toHaveCount(1);
    expect($project2Services[0]->serviceType)->toBe('mobile');
    
    // Verify user counts
    $updatedWebService = $this->serviceManager->getService($webService->id);
    expect($updatedWebService->activeUserCount)->toBe(2);
    
    $updatedMobileService = $this->serviceManager->getService($mobileService->id);
    expect($updatedMobileService->activeUserCount)->toBe(1);
    
    // Verify users are isolated per service
    $webUsers = $this->userService->listActiveUsers($webService->id);
    expect($webUsers)->toHaveCount(2);
    
    $mobileUsers = $this->userService->listActiveUsers($mobileService->id);
    expect($mobileUsers)->toHaveCount(1);
});

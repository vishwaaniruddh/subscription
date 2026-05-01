<?php

use App\Services\SubscriptionLifecycleManager;
use App\Repositories\ServiceRepository;
use App\Repositories\SubscriptionHistoryRepository;
use App\Models\Service;

/**
 * Feature: subscription-management-module
 * Property 17: Renewal Requires End Date
 * Property 18: Renewal Updates End Date
 * Property 19: Renewal Preserves User Limit and Count
 * Property 20: Renewal Creates History Record
 * Property 21: Extension Limit Validation
 * Property 22: Extension Updates User Limit
 * Property 23: Extension Creates History Record
 * Property 24: Extension Date Update
 * 
 * For any renewal request without a new_end_date parameter, the request should be rejected.
 * 
 * For any service and valid new_end_date, renewing the subscription should update the 
 * end_date field to the new value.
 * 
 * For any service, renewing the subscription should not change the user_limit or 
 * active_user_count values.
 * 
 * For any successful renewal, a record should be created in subscription_history with 
 * action_type='renewal' and a timestamp.
 * 
 * For any extension request where new_user_limit < active_user_count, the request should 
 * be rejected with error code INVALID_USER_LIMIT.
 * 
 * For any service and valid new_user_limit (>= active_user_count), extending the 
 * subscription should update the user_limit field to the new value.
 * 
 * For any successful extension, a record should be created in subscription_history with 
 * action_type='extension' and a timestamp.
 * 
 * For any extension request that includes a new_end_date parameter, the service's 
 * end_date should be updated to the new value.
 * 
 * **Validates: Requirements 8.2, 8.3, 8.4, 8.5, 8.6, 9.3, 9.4, 9.5, 9.6, 9.7**
 */


test('renewal requires end date parameter', function () {
    $faker = Faker\Factory::create();
    $pdo = getTestDatabase();
    $serviceRepository = new ServiceRepository($pdo);
    $historyRepository = new SubscriptionHistoryRepository($pdo);
    $lifecycleManager = new SubscriptionLifecycleManager($serviceRepository, $historyRepository);
    
    // Clean up test data
    $pdo->exec("DELETE FROM subscription_history WHERE service_id IN (SELECT id FROM services WHERE project_id >= 10000 AND project_id < 10100)");
    $pdo->exec("DELETE FROM services WHERE project_id >= 10000 AND project_id < 10100");
    $pdo->exec("DELETE FROM projects WHERE id >= 10000 AND id < 10100");
    
    // Ensure test client exists
    $pdo->exec("INSERT IGNORE INTO clients (id, name, contact_info) VALUES (1, 'Test Client', 'test@example.com')");
    
    // Create test projects
    for ($i = 0; $i < 100; $i++) {
        $pdo->exec("INSERT INTO projects (id, client_id, name, description) VALUES (" . (10000 + $i) . ", 1, 'Test Project $i', 'Test')");
    }
    
    // Run 100 iterations
    for ($i = 0; $i < 100; $i++) {
        // Create a service
        $userLimit = $faker->numberBetween(10, 100);
        $activeUserCount = $faker->numberBetween(0, $userLimit);
        $startDate = $faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d');
        $endDate = $faker->dateTimeBetween('+1 day', '+1 year')->format('Y-m-d');
        
        $service = new Service(
            10000 + $i,
            $faker->randomElement(['web', 'mobile', 'other']),
            $userLimit,
            $startDate,
            $endDate,
            $activeUserCount
        );
        
        $service = $serviceRepository->create($service);
        
        // The renewSubscription method requires a newEndDate parameter
        // Since PHP doesn't allow calling without required parameters, we verify
        // that the method signature requires it by checking if calling with empty/null fails
        
        $exceptionThrown = false;
        try {
            // Attempt to call with empty string (invalid date)
            $lifecycleManager->renewSubscription($service->id, '');
        } catch (Exception $e) {
            $exceptionThrown = true;
        }
        
        // Verify that an exception was thrown for invalid/empty date
        expect($exceptionThrown)->toBeTrue(
            "Renewal should reject empty or invalid end date parameter"
        );
    }
    
    // Clean up
    $pdo->exec("DELETE FROM subscription_history WHERE service_id IN (SELECT id FROM services WHERE project_id >= 10000 AND project_id < 10100)");
    $pdo->exec("DELETE FROM services WHERE project_id >= 10000 AND project_id < 10100");
    $pdo->exec("DELETE FROM projects WHERE id >= 10000 AND id < 10100");
})->group('property', 'subscription-lifecycle', 'renewal');


test('renewal updates end date to new value', function () {
    $faker = Faker\Factory::create();
    $pdo = getTestDatabase();
    $serviceRepository = new ServiceRepository($pdo);
    $historyRepository = new SubscriptionHistoryRepository($pdo);
    $lifecycleManager = new SubscriptionLifecycleManager($serviceRepository, $historyRepository);
    
    // Clean up test data
    $pdo->exec("DELETE FROM subscription_history WHERE service_id IN (SELECT id FROM services WHERE project_id >= 10100 AND project_id < 10200)");
    $pdo->exec("DELETE FROM services WHERE project_id >= 10100 AND project_id < 10200");
    $pdo->exec("DELETE FROM projects WHERE id >= 10100 AND id < 10200");
    
    // Ensure test client exists
    $pdo->exec("INSERT IGNORE INTO clients (id, name, contact_info) VALUES (1, 'Test Client', 'test@example.com')");
    
    // Create test projects
    for ($i = 0; $i < 100; $i++) {
        $pdo->exec("INSERT INTO projects (id, client_id, name, description) VALUES (" . (10100 + $i) . ", 1, 'Test Project $i', 'Test')");
    }
    
    // Run 100 iterations
    for ($i = 0; $i < 100; $i++) {
        // Create a service
        $userLimit = $faker->numberBetween(10, 100);
        $activeUserCount = $faker->numberBetween(0, $userLimit);
        $startDate = $faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d');
        $endDate = $faker->dateTimeBetween('+1 day', '+6 months')->format('Y-m-d');
        
        $service = new Service(
            10100 + $i,
            $faker->randomElement(['web', 'mobile', 'other']),
            $userLimit,
            $startDate,
            $endDate,
            $activeUserCount
        );
        
        $service = $serviceRepository->create($service);
        $originalEndDate = $service->endDate;
        
        // Generate a new end date (after the original)
        $newEndDate = $faker->dateTimeBetween('+7 months', '+2 years')->format('Y-m-d');
        
        // Renew the subscription
        $lifecycleManager->renewSubscription($service->id, $newEndDate);
        
        // Retrieve the updated service
        $updatedService = $serviceRepository->findById($service->id);
        
        // Verify the end date was updated to the new value
        expect($updatedService->endDate)->toBe($newEndDate,
            "Service end_date should be updated to new value after renewal (original: $originalEndDate, new: $newEndDate)"
        );
    }
    
    // Clean up
    $pdo->exec("DELETE FROM subscription_history WHERE service_id IN (SELECT id FROM services WHERE project_id >= 10100 AND project_id < 10200)");
    $pdo->exec("DELETE FROM services WHERE project_id >= 10100 AND project_id < 10200");
    $pdo->exec("DELETE FROM projects WHERE id >= 10100 AND id < 10200");
})->group('property', 'subscription-lifecycle', 'renewal');


test('renewal preserves user limit and active user count', function () {
    $faker = Faker\Factory::create();
    $pdo = getTestDatabase();
    $serviceRepository = new ServiceRepository($pdo);
    $historyRepository = new SubscriptionHistoryRepository($pdo);
    $lifecycleManager = new SubscriptionLifecycleManager($serviceRepository, $historyRepository);
    
    // Clean up test data
    $pdo->exec("DELETE FROM subscription_history WHERE service_id IN (SELECT id FROM services WHERE project_id >= 10200 AND project_id < 10300)");
    $pdo->exec("DELETE FROM services WHERE project_id >= 10200 AND project_id < 10300");
    $pdo->exec("DELETE FROM projects WHERE id >= 10200 AND id < 10300");
    
    // Ensure test client exists
    $pdo->exec("INSERT IGNORE INTO clients (id, name, contact_info) VALUES (1, 'Test Client', 'test@example.com')");
    
    // Create test projects
    for ($i = 0; $i < 100; $i++) {
        $pdo->exec("INSERT INTO projects (id, client_id, name, description) VALUES (" . (10200 + $i) . ", 1, 'Test Project $i', 'Test')");
    }
    
    // Run 100 iterations
    for ($i = 0; $i < 100; $i++) {
        // Create a service with random values
        $userLimit = $faker->numberBetween(10, 100);
        $activeUserCount = $faker->numberBetween(0, $userLimit);
        $startDate = $faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d');
        $endDate = $faker->dateTimeBetween('+1 day', '+6 months')->format('Y-m-d');
        
        $service = new Service(
            10200 + $i,
            $faker->randomElement(['web', 'mobile', 'other']),
            $userLimit,
            $startDate,
            $endDate,
            $activeUserCount
        );
        
        $service = $serviceRepository->create($service);
        $originalUserLimit = $service->userLimit;
        $originalActiveUserCount = $service->activeUserCount;
        
        // Generate a new end date
        $newEndDate = $faker->dateTimeBetween('+7 months', '+2 years')->format('Y-m-d');
        
        // Renew the subscription
        $lifecycleManager->renewSubscription($service->id, $newEndDate);
        
        // Retrieve the updated service
        $updatedService = $serviceRepository->findById($service->id);
        
        // Verify user_limit was not changed
        expect($updatedService->userLimit)->toBe($originalUserLimit,
            "Service user_limit should remain unchanged after renewal (original: $originalUserLimit)"
        );
        
        // Verify active_user_count was not changed
        expect($updatedService->activeUserCount)->toBe($originalActiveUserCount,
            "Service active_user_count should remain unchanged after renewal (original: $originalActiveUserCount)"
        );
    }
    
    // Clean up
    $pdo->exec("DELETE FROM subscription_history WHERE service_id IN (SELECT id FROM services WHERE project_id >= 10200 AND project_id < 10300)");
    $pdo->exec("DELETE FROM services WHERE project_id >= 10200 AND project_id < 10300");
    $pdo->exec("DELETE FROM projects WHERE id >= 10200 AND id < 10300");
})->group('property', 'subscription-lifecycle', 'renewal');


test('renewal creates history record with action type renewal', function () {
    $faker = Faker\Factory::create();
    $pdo = getTestDatabase();
    $serviceRepository = new ServiceRepository($pdo);
    $historyRepository = new SubscriptionHistoryRepository($pdo);
    $lifecycleManager = new SubscriptionLifecycleManager($serviceRepository, $historyRepository);
    
    // Clean up test data
    $pdo->exec("DELETE FROM subscription_history WHERE service_id IN (SELECT id FROM services WHERE project_id >= 10300 AND project_id < 10400)");
    $pdo->exec("DELETE FROM services WHERE project_id >= 10300 AND project_id < 10400");
    $pdo->exec("DELETE FROM projects WHERE id >= 10300 AND id < 10400");
    
    // Ensure test client exists
    $pdo->exec("INSERT IGNORE INTO clients (id, name, contact_info) VALUES (1, 'Test Client', 'test@example.com')");
    
    // Create test projects
    for ($i = 0; $i < 100; $i++) {
        $pdo->exec("INSERT INTO projects (id, client_id, name, description) VALUES (" . (10300 + $i) . ", 1, 'Test Project $i', 'Test')");
    }
    
    // Run 100 iterations
    for ($i = 0; $i < 100; $i++) {
        // Create a service
        $userLimit = $faker->numberBetween(10, 100);
        $activeUserCount = $faker->numberBetween(0, $userLimit);
        $startDate = $faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d');
        $endDate = $faker->dateTimeBetween('+1 day', '+6 months')->format('Y-m-d');
        
        $service = new Service(
            10300 + $i,
            $faker->randomElement(['web', 'mobile', 'other']),
            $userLimit,
            $startDate,
            $endDate,
            $activeUserCount
        );
        
        $service = $serviceRepository->create($service);
        
        // Count history records before renewal
        $historyBefore = $historyRepository->findByServiceId($service->id);
        $countBefore = count($historyBefore);
        
        // Generate a new end date
        $newEndDate = $faker->dateTimeBetween('+7 months', '+2 years')->format('Y-m-d');
        
        // Renew the subscription
        $lifecycleManager->renewSubscription($service->id, $newEndDate);
        
        // Retrieve history records after renewal
        $historyAfter = $historyRepository->findByServiceId($service->id);
        $countAfter = count($historyAfter);
        
        // Verify a new history record was created
        expect($countAfter)->toBe($countBefore + 1,
            "A new history record should be created after renewal (before: $countBefore, after: $countAfter)"
        );
        
        // Get the most recent history record
        $latestHistory = $historyAfter[0]; // findByServiceId returns DESC order
        
        // Verify the action type is 'RENEWAL'
        expect($latestHistory->actionType)->toBe('RENEWAL',
            "History record action_type should be 'RENEWAL'"
        );
        
        // Verify the history record has a timestamp
        expect($latestHistory->timestamp)->not->toBeNull(
            "History record should have a non-null timestamp"
        );
    }
    
    // Clean up
    $pdo->exec("DELETE FROM subscription_history WHERE service_id IN (SELECT id FROM services WHERE project_id >= 10300 AND project_id < 10400)");
    $pdo->exec("DELETE FROM services WHERE project_id >= 10300 AND project_id < 10400");
    $pdo->exec("DELETE FROM projects WHERE id >= 10300 AND id < 10400");
})->group('property', 'subscription-lifecycle', 'renewal');


test('extension rejects new user limit less than active user count', function () {
    $faker = Faker\Factory::create();
    $pdo = getTestDatabase();
    $serviceRepository = new ServiceRepository($pdo);
    $historyRepository = new SubscriptionHistoryRepository($pdo);
    $lifecycleManager = new SubscriptionLifecycleManager($serviceRepository, $historyRepository);
    
    // Clean up test data
    $pdo->exec("DELETE FROM subscription_history WHERE service_id IN (SELECT id FROM services WHERE project_id >= 10400 AND project_id < 10500)");
    $pdo->exec("DELETE FROM services WHERE project_id >= 10400 AND project_id < 10500");
    $pdo->exec("DELETE FROM projects WHERE id >= 10400 AND id < 10500");
    
    // Ensure test client exists
    $pdo->exec("INSERT IGNORE INTO clients (id, name, contact_info) VALUES (1, 'Test Client', 'test@example.com')");
    
    // Create test projects
    for ($i = 0; $i < 100; $i++) {
        $pdo->exec("INSERT INTO projects (id, client_id, name, description) VALUES (" . (10400 + $i) . ", 1, 'Test Project $i', 'Test')");
    }
    
    // Run 100 iterations
    for ($i = 0; $i < 100; $i++) {
        // Create a service with active users
        $activeUserCount = $faker->numberBetween(10, 50);
        $userLimit = $faker->numberBetween($activeUserCount, 100);
        $startDate = $faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d');
        $endDate = $faker->dateTimeBetween('+1 day', '+1 year')->format('Y-m-d');
        
        $service = new Service(
            10400 + $i,
            $faker->randomElement(['web', 'mobile', 'other']),
            $userLimit,
            $startDate,
            $endDate,
            $activeUserCount
        );
        
        $service = $serviceRepository->create($service);
        
        // Try to set new user limit less than active user count
        $newUserLimit = $faker->numberBetween(1, $activeUserCount - 1);
        
        $exceptionThrown = false;
        $exceptionMessage = '';
        $exceptionCode = 0;
        
        try {
            $lifecycleManager->extendSubscription($service->id, $newUserLimit);
        } catch (Exception $e) {
            $exceptionThrown = true;
            $exceptionMessage = $e->getMessage();
            $exceptionCode = $e->getCode();
        }
        
        // Verify that extension was rejected
        expect($exceptionThrown)->toBeTrue(
            "Extension should be rejected when new_user_limit ($newUserLimit) < active_user_count ($activeUserCount)"
        );
        
        // Verify the error code is 400 (validation error)
        expect($exceptionCode)->toBe(400,
            "Exception code should be 400 for INVALID_USER_LIMIT error"
        );
        
        // Verify the error message mentions user limit or active users
        expect($exceptionMessage)->toMatch('/(user|limit|active)/i',
            "Error message should indicate user limit validation failure"
        );
    }
    
    // Clean up
    $pdo->exec("DELETE FROM subscription_history WHERE service_id IN (SELECT id FROM services WHERE project_id >= 10400 AND project_id < 10500)");
    $pdo->exec("DELETE FROM services WHERE project_id >= 10400 AND project_id < 10500");
    $pdo->exec("DELETE FROM projects WHERE id >= 10400 AND id < 10500");
})->group('property', 'subscription-lifecycle', 'extension');


test('extension updates user limit to new value when valid', function () {
    $faker = Faker\Factory::create();
    $pdo = getTestDatabase();
    $serviceRepository = new ServiceRepository($pdo);
    $historyRepository = new SubscriptionHistoryRepository($pdo);
    $lifecycleManager = new SubscriptionLifecycleManager($serviceRepository, $historyRepository);
    
    // Clean up test data
    $pdo->exec("DELETE FROM subscription_history WHERE service_id IN (SELECT id FROM services WHERE project_id >= 10500 AND project_id < 10600)");
    $pdo->exec("DELETE FROM services WHERE project_id >= 10500 AND project_id < 10600");
    $pdo->exec("DELETE FROM projects WHERE id >= 10500 AND id < 10600");
    
    // Ensure test client exists
    $pdo->exec("INSERT IGNORE INTO clients (id, name, contact_info) VALUES (1, 'Test Client', 'test@example.com')");
    
    // Create test projects
    for ($i = 0; $i < 100; $i++) {
        $pdo->exec("INSERT INTO projects (id, client_id, name, description) VALUES (" . (10500 + $i) . ", 1, 'Test Project $i', 'Test')");
    }
    
    // Run 100 iterations
    for ($i = 0; $i < 100; $i++) {
        // Create a service
        $activeUserCount = $faker->numberBetween(5, 50);
        $userLimit = $faker->numberBetween($activeUserCount, 100);
        $startDate = $faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d');
        $endDate = $faker->dateTimeBetween('+1 day', '+1 year')->format('Y-m-d');
        
        $service = new Service(
            10500 + $i,
            $faker->randomElement(['web', 'mobile', 'other']),
            $userLimit,
            $startDate,
            $endDate,
            $activeUserCount
        );
        
        $service = $serviceRepository->create($service);
        $originalUserLimit = $service->userLimit;
        
        // Set new user limit >= active user count
        $newUserLimit = $faker->numberBetween($activeUserCount, $activeUserCount + 100);
        
        // Extend the subscription
        $lifecycleManager->extendSubscription($service->id, $newUserLimit);
        
        // Retrieve the updated service
        $updatedService = $serviceRepository->findById($service->id);
        
        // Verify the user limit was updated to the new value
        expect($updatedService->userLimit)->toBe($newUserLimit,
            "Service user_limit should be updated to new value after extension (original: $originalUserLimit, new: $newUserLimit)"
        );
    }
    
    // Clean up
    $pdo->exec("DELETE FROM subscription_history WHERE service_id IN (SELECT id FROM services WHERE project_id >= 10500 AND project_id < 10600)");
    $pdo->exec("DELETE FROM services WHERE project_id >= 10500 AND project_id < 10600");
    $pdo->exec("DELETE FROM projects WHERE id >= 10500 AND id < 10600");
})->group('property', 'subscription-lifecycle', 'extension');


test('extension creates history record with action type extension', function () {
    $faker = Faker\Factory::create();
    $pdo = getTestDatabase();
    $serviceRepository = new ServiceRepository($pdo);
    $historyRepository = new SubscriptionHistoryRepository($pdo);
    $lifecycleManager = new SubscriptionLifecycleManager($serviceRepository, $historyRepository);
    
    // Clean up test data
    $pdo->exec("DELETE FROM subscription_history WHERE service_id IN (SELECT id FROM services WHERE project_id >= 10600 AND project_id < 10700)");
    $pdo->exec("DELETE FROM services WHERE project_id >= 10600 AND project_id < 10700");
    $pdo->exec("DELETE FROM projects WHERE id >= 10600 AND id < 10700");
    
    // Ensure test client exists
    $pdo->exec("INSERT IGNORE INTO clients (id, name, contact_info) VALUES (1, 'Test Client', 'test@example.com')");
    
    // Create test projects
    for ($i = 0; $i < 100; $i++) {
        $pdo->exec("INSERT INTO projects (id, client_id, name, description) VALUES (" . (10600 + $i) . ", 1, 'Test Project $i', 'Test')");
    }
    
    // Run 100 iterations
    for ($i = 0; $i < 100; $i++) {
        // Create a service
        $activeUserCount = $faker->numberBetween(5, 50);
        $userLimit = $faker->numberBetween($activeUserCount, 100);
        $startDate = $faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d');
        $endDate = $faker->dateTimeBetween('+1 day', '+1 year')->format('Y-m-d');
        
        $service = new Service(
            10600 + $i,
            $faker->randomElement(['web', 'mobile', 'other']),
            $userLimit,
            $startDate,
            $endDate,
            $activeUserCount
        );
        
        $service = $serviceRepository->create($service);
        
        // Count history records before extension
        $historyBefore = $historyRepository->findByServiceId($service->id);
        $countBefore = count($historyBefore);
        
        // Set new user limit >= active user count
        $newUserLimit = $faker->numberBetween($activeUserCount, $activeUserCount + 100);
        
        // Extend the subscription
        $lifecycleManager->extendSubscription($service->id, $newUserLimit);
        
        // Retrieve history records after extension
        $historyAfter = $historyRepository->findByServiceId($service->id);
        $countAfter = count($historyAfter);
        
        // Verify a new history record was created
        expect($countAfter)->toBe($countBefore + 1,
            "A new history record should be created after extension (before: $countBefore, after: $countAfter)"
        );
        
        // Get the most recent history record
        $latestHistory = $historyAfter[0]; // findByServiceId returns DESC order
        
        // Verify the action type is 'EXTENSION'
        expect($latestHistory->actionType)->toBe('EXTENSION',
            "History record action_type should be 'EXTENSION'"
        );
        
        // Verify the history record has a timestamp
        expect($latestHistory->timestamp)->not->toBeNull(
            "History record should have a non-null timestamp"
        );
    }
    
    // Clean up
    $pdo->exec("DELETE FROM subscription_history WHERE service_id IN (SELECT id FROM services WHERE project_id >= 10600 AND project_id < 10700)");
    $pdo->exec("DELETE FROM services WHERE project_id >= 10600 AND project_id < 10700");
    $pdo->exec("DELETE FROM projects WHERE id >= 10600 AND id < 10700");
})->group('property', 'subscription-lifecycle', 'extension');


test('extension updates end date when new end date parameter is provided', function () {
    $faker = Faker\Factory::create();
    $pdo = getTestDatabase();
    $serviceRepository = new ServiceRepository($pdo);
    $historyRepository = new SubscriptionHistoryRepository($pdo);
    $lifecycleManager = new SubscriptionLifecycleManager($serviceRepository, $historyRepository);
    
    // Clean up test data
    $pdo->exec("DELETE FROM subscription_history WHERE service_id IN (SELECT id FROM services WHERE project_id >= 10700 AND project_id < 10800)");
    $pdo->exec("DELETE FROM services WHERE project_id >= 10700 AND project_id < 10800");
    $pdo->exec("DELETE FROM projects WHERE id >= 10700 AND id < 10800");
    
    // Ensure test client exists
    $pdo->exec("INSERT IGNORE INTO clients (id, name, contact_info) VALUES (1, 'Test Client', 'test@example.com')");
    
    // Create test projects
    for ($i = 0; $i < 100; $i++) {
        $pdo->exec("INSERT INTO projects (id, client_id, name, description) VALUES (" . (10700 + $i) . ", 1, 'Test Project $i', 'Test')");
    }
    
    // Run 100 iterations
    for ($i = 0; $i < 100; $i++) {
        // Create a service
        $activeUserCount = $faker->numberBetween(5, 50);
        $userLimit = $faker->numberBetween($activeUserCount, 100);
        $startDate = $faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d');
        $endDate = $faker->dateTimeBetween('+1 day', '+6 months')->format('Y-m-d');
        
        $service = new Service(
            10700 + $i,
            $faker->randomElement(['web', 'mobile', 'other']),
            $userLimit,
            $startDate,
            $endDate,
            $activeUserCount
        );
        
        $service = $serviceRepository->create($service);
        $originalEndDate = $service->endDate;
        
        // Set new user limit and new end date
        $newUserLimit = $faker->numberBetween($activeUserCount, $activeUserCount + 100);
        $newEndDate = $faker->dateTimeBetween('+7 months', '+2 years')->format('Y-m-d');
        
        // Extend the subscription with new end date
        $lifecycleManager->extendSubscription($service->id, $newUserLimit, $newEndDate);
        
        // Retrieve the updated service
        $updatedService = $serviceRepository->findById($service->id);
        
        // Verify the end date was updated to the new value
        expect($updatedService->endDate)->toBe($newEndDate,
            "Service end_date should be updated when new_end_date parameter is provided (original: $originalEndDate, new: $newEndDate)"
        );
    }
    
    // Clean up
    $pdo->exec("DELETE FROM subscription_history WHERE service_id IN (SELECT id FROM services WHERE project_id >= 10700 AND project_id < 10800)");
    $pdo->exec("DELETE FROM services WHERE project_id >= 10700 AND project_id < 10800");
    $pdo->exec("DELETE FROM projects WHERE id >= 10700 AND id < 10800");
})->group('property', 'subscription-lifecycle', 'extension');

test('extension preserves end date when new end date parameter is not provided', function () {
    $faker = Faker\Factory::create();
    $pdo = getTestDatabase();
    $serviceRepository = new ServiceRepository($pdo);
    $historyRepository = new SubscriptionHistoryRepository($pdo);
    $lifecycleManager = new SubscriptionLifecycleManager($serviceRepository, $historyRepository);
    
    // Clean up test data
    $pdo->exec("DELETE FROM subscription_history WHERE service_id IN (SELECT id FROM services WHERE project_id >= 10800 AND project_id < 10900)");
    $pdo->exec("DELETE FROM services WHERE project_id >= 10800 AND project_id < 10900");
    $pdo->exec("DELETE FROM projects WHERE id >= 10800 AND id < 10900");
    
    // Ensure test client exists
    $pdo->exec("INSERT IGNORE INTO clients (id, name, contact_info) VALUES (1, 'Test Client', 'test@example.com')");
    
    // Create test projects
    for ($i = 0; $i < 100; $i++) {
        $pdo->exec("INSERT INTO projects (id, client_id, name, description) VALUES (" . (10800 + $i) . ", 1, 'Test Project $i', 'Test')");
    }
    
    // Run 100 iterations
    for ($i = 0; $i < 100; $i++) {
        // Create a service
        $activeUserCount = $faker->numberBetween(5, 50);
        $userLimit = $faker->numberBetween($activeUserCount, 100);
        $startDate = $faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d');
        $endDate = $faker->dateTimeBetween('+1 day', '+1 year')->format('Y-m-d');
        
        $service = new Service(
            10800 + $i,
            $faker->randomElement(['web', 'mobile', 'other']),
            $userLimit,
            $startDate,
            $endDate,
            $activeUserCount
        );
        
        $service = $serviceRepository->create($service);
        $originalEndDate = $service->endDate;
        
        // Set new user limit without new end date (null)
        $newUserLimit = $faker->numberBetween($activeUserCount, $activeUserCount + 100);
        
        // Extend the subscription without new end date
        $lifecycleManager->extendSubscription($service->id, $newUserLimit, null);
        
        // Retrieve the updated service
        $updatedService = $serviceRepository->findById($service->id);
        
        // Verify the end date was NOT changed
        expect($updatedService->endDate)->toBe($originalEndDate,
            "Service end_date should remain unchanged when new_end_date parameter is not provided (original: $originalEndDate)"
        );
    }
    
    // Clean up
    $pdo->exec("DELETE FROM subscription_history WHERE service_id IN (SELECT id FROM services WHERE project_id >= 10800 AND project_id < 10900)");
    $pdo->exec("DELETE FROM services WHERE project_id >= 10800 AND project_id < 10900");
    $pdo->exec("DELETE FROM projects WHERE id >= 10800 AND id < 10900");
})->group('property', 'subscription-lifecycle', 'extension');

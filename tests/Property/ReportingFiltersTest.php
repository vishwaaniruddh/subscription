<?php

use App\Repositories\ServiceRepository;
use App\Services\ServiceManager;

/**
 * Feature: subscription-management-module
 * Property 33: Expiring Services Filter
 * Property 34: High Utilization Filter
 * 
 * Property 33: For any query for services expiring within N days, all returned 
 * services should have end_date within N days from the current date, and no 
 * services meeting this criteria should be omitted.
 * 
 * Property 34: For any query for services with utilization above threshold T, 
 * all returned services should have utilization_percentage >= T, and no services 
 * meeting this criteria should be omitted.
 * 
 * **Validates: Requirements 14.5, 14.6**
 * 
 * These tests verify the completeness (no false negatives) and correctness 
 * (no false positives) of reporting filter queries.
 */

test('expiring services filter returns all services expiring within N days', function () {
    $faker = Faker\Factory::create();
    $pdo = getTestDatabase();
    $repository = new ServiceRepository($pdo);
    $serviceManager = new ServiceManager($repository);
    
    // Get database's current date to avoid timezone issues
    $stmt = $pdo->query("SELECT CURDATE() as today, DATE_ADD(CURDATE(), INTERVAL 90 DAY) as max_date");
    $dates = $stmt->fetch(PDO::FETCH_ASSOC);
    $dbToday = $dates['today'];
    
    // Clean up test data
    $pdo->exec("DELETE FROM services WHERE project_id >= 9000 AND project_id < 9100");
    $pdo->exec("DELETE FROM projects WHERE id >= 9000 AND id < 9100");
    
    // Ensure test client exists
    $pdo->exec("INSERT IGNORE INTO clients (id, name, contact_info) VALUES (1, 'Test Client', 'test@example.com')");
    
    // Create test projects for foreign key constraint
    for ($i = 0; $i < 100; $i++) {
        $pdo->exec("INSERT INTO projects (id, client_id, name, description) VALUES (" . (9000 + $i) . ", 1, 'Test Project $i', 'Test')");
    }
    
    // Run 100 iterations
    for ($i = 0; $i < 100; $i++) {
        // Generate random number of days to look ahead
        $daysAhead = $faker->numberBetween(1, 90);
        
        // Create a mix of services: some expiring within N days, some not
        $servicesWithinRange = [];
        $servicesOutsideRange = [];
        
        // Create 3-5 services that should be in the result (expiring within N days)
        $numWithin = $faker->numberBetween(3, 5);
        for ($j = 0; $j < $numWithin; $j++) {
            $startDate = date('Y-m-d', strtotime($dbToday . ' -1 year'));
            // End date is between today and N days from now (using database's today)
            $daysOffset = $faker->numberBetween(0, $daysAhead);
            $endDate = date('Y-m-d', strtotime($dbToday . " +{$daysOffset} days"));
            
            $serviceData = [
                'project_id' => 9000 + $i,
                'service_type' => $faker->randomElement(['web', 'mobile', 'other']),
                'user_limit' => $faker->numberBetween(10, 1000),
                'active_user_count' => $faker->numberBetween(0, 100),
                'start_date' => $startDate,
                'end_date' => $endDate,
            ];
            
            $stmt = $pdo->prepare("
                INSERT INTO services (project_id, service_type, user_limit, active_user_count, start_date, end_date)
                VALUES (:project_id, :service_type, :user_limit, :active_user_count, :start_date, :end_date)
            ");
            $stmt->execute($serviceData);
            $servicesWithinRange[] = (int) $pdo->lastInsertId();
        }
        
        // Create 2-3 services that should NOT be in the result (expiring after N days)
        $numOutside = $faker->numberBetween(2, 3);
        for ($j = 0; $j < $numOutside; $j++) {
            $startDate = date('Y-m-d', strtotime($dbToday . ' -1 year'));
            // End date is more than N days from now
            $daysOffset = $faker->numberBetween($daysAhead + 1, 365);
            $endDate = date('Y-m-d', strtotime($dbToday . " +{$daysOffset} days"));
            
            $serviceData = [
                'project_id' => 9000 + $i,
                'service_type' => $faker->randomElement(['web', 'mobile', 'other']),
                'user_limit' => $faker->numberBetween(10, 1000),
                'active_user_count' => $faker->numberBetween(0, 100),
                'start_date' => $startDate,
                'end_date' => $endDate,
            ];
            
            $stmt = $pdo->prepare("
                INSERT INTO services (project_id, service_type, user_limit, active_user_count, start_date, end_date)
                VALUES (:project_id, :service_type, :user_limit, :active_user_count, :start_date, :end_date)
            ");
            $stmt->execute($serviceData);
            $servicesOutsideRange[] = (int) $pdo->lastInsertId();
        }
        
        // Query for expiring services
        $results = $serviceManager->getExpiringServices($daysAhead);
        $resultIds = array_map(fn($s) => $s->id, $results);
        
        // Verify completeness: all services within range are included (no false negatives)
        foreach ($servicesWithinRange as $expectedId) {
            expect($resultIds)->toContain($expectedId,
                "Service $expectedId expiring within $daysAhead days should be included in results"
            );
        }
        
        // Verify correctness: no services outside range are included (no false positives)
        foreach ($servicesOutsideRange as $unexpectedId) {
            expect($resultIds)->not->toContain($unexpectedId,
                "Service $unexpectedId expiring after $daysAhead days should NOT be included in results"
            );
        }
        
        // Verify all returned services have end_date within N days (using database date)
        $today = new DateTime($dbToday);
        $maxDate = (clone $today)->modify("+{$daysAhead} days");
        
        foreach ($results as $service) {
            $endDate = new DateTime($service->endDate);
            expect($endDate >= $today && $endDate <= $maxDate)->toBeTrue(
                "Service {$service->id} end_date {$service->endDate} should be between $dbToday and $daysAhead days from now"
            );
        }
        
        // Clean up this iteration's services
        $pdo->exec("DELETE FROM services WHERE project_id = " . (9000 + $i));
    }
    
    // Clean up
    $pdo->exec("DELETE FROM services WHERE project_id >= 9000 AND project_id < 9100");
    $pdo->exec("DELETE FROM projects WHERE id >= 9000 AND id < 9100");
})->group('property', 'reporting', 'expiring-services-filter');

test('expiring services filter handles edge case of services expiring today', function () {
    $faker = Faker\Factory::create();
    $pdo = getTestDatabase();
    $repository = new ServiceRepository($pdo);
    $serviceManager = new ServiceManager($repository);
    
    // Get database's current date
    $stmt = $pdo->query("SELECT CURDATE() as today");
    $dbToday = $stmt->fetch(PDO::FETCH_ASSOC)['today'];
    
    // Clean up test data
    $pdo->exec("DELETE FROM services WHERE project_id >= 9100 AND project_id < 9200");
    $pdo->exec("DELETE FROM projects WHERE id >= 9100 AND id < 9200");
    
    // Ensure test client exists
    $pdo->exec("INSERT IGNORE INTO clients (id, name, contact_info) VALUES (1, 'Test Client', 'test@example.com')");
    
    // Create test projects for foreign key constraint
    for ($i = 0; $i < 100; $i++) {
        $pdo->exec("INSERT INTO projects (id, client_id, name, description) VALUES (" . (9100 + $i) . ", 1, 'Test Project $i', 'Test')");
    }
    
    // Run 100 iterations
    for ($i = 0; $i < 100; $i++) {
        $daysAhead = $faker->numberBetween(1, 30);
        
        // Create a service expiring today (using database's today)
        $serviceData = [
            'project_id' => 9100 + $i,
            'service_type' => $faker->randomElement(['web', 'mobile', 'other']),
            'user_limit' => $faker->numberBetween(10, 1000),
            'active_user_count' => $faker->numberBetween(0, 100),
            'start_date' => date('Y-m-d', strtotime($dbToday . ' -1 year')),
            'end_date' => $dbToday,
        ];
        
        $stmt = $pdo->prepare("
            INSERT INTO services (project_id, service_type, user_limit, active_user_count, start_date, end_date)
            VALUES (:project_id, :service_type, :user_limit, :active_user_count, :start_date, :end_date)
        ");
        $stmt->execute($serviceData);
        $serviceId = (int) $pdo->lastInsertId();
        
        // Query for expiring services
        $results = $serviceManager->getExpiringServices($daysAhead);
        $resultIds = array_map(fn($s) => $s->id, $results);
        
        // Verify service expiring today is included
        expect($resultIds)->toContain($serviceId,
            "Service expiring today should be included when querying for services expiring within $daysAhead days"
        );
        
        // Clean up this iteration's services
        $pdo->exec("DELETE FROM services WHERE project_id = " . (9100 + $i));
    }
    
    // Clean up
    $pdo->exec("DELETE FROM services WHERE project_id >= 9100 AND project_id < 9200");
    $pdo->exec("DELETE FROM projects WHERE id >= 9100 AND id < 9200");
})->group('property', 'reporting', 'expiring-services-filter');

test('expiring services filter handles edge case of services expiring exactly N days from now', function () {
    $faker = Faker\Factory::create();
    $pdo = getTestDatabase();
    $repository = new ServiceRepository($pdo);
    $serviceManager = new ServiceManager($repository);
    
    // Get database's current date
    $stmt = $pdo->query("SELECT CURDATE() as today");
    $dbToday = $stmt->fetch(PDO::FETCH_ASSOC)['today'];
    
    // Clean up test data
    $pdo->exec("DELETE FROM services WHERE project_id >= 9200 AND project_id < 9300");
    $pdo->exec("DELETE FROM projects WHERE id >= 9200 AND id < 9300");
    
    // Ensure test client exists
    $pdo->exec("INSERT IGNORE INTO clients (id, name, contact_info) VALUES (1, 'Test Client', 'test@example.com')");
    
    // Create test projects for foreign key constraint
    for ($i = 0; $i < 100; $i++) {
        $pdo->exec("INSERT INTO projects (id, client_id, name, description) VALUES (" . (9200 + $i) . ", 1, 'Test Project $i', 'Test')");
    }
    
    // Run 100 iterations
    for ($i = 0; $i < 100; $i++) {
        $daysAhead = $faker->numberBetween(1, 90);
        
        // Create a service expiring exactly N days from now (using database's today)
        $exactDate = date('Y-m-d', strtotime($dbToday . " +{$daysAhead} days"));
        $serviceData = [
            'project_id' => 9200 + $i,
            'service_type' => $faker->randomElement(['web', 'mobile', 'other']),
            'user_limit' => $faker->numberBetween(10, 1000),
            'active_user_count' => $faker->numberBetween(0, 100),
            'start_date' => date('Y-m-d', strtotime($dbToday . ' -1 year')),
            'end_date' => $exactDate,
        ];
        
        $stmt = $pdo->prepare("
            INSERT INTO services (project_id, service_type, user_limit, active_user_count, start_date, end_date)
            VALUES (:project_id, :service_type, :user_limit, :active_user_count, :start_date, :end_date)
        ");
        $stmt->execute($serviceData);
        $serviceId = (int) $pdo->lastInsertId();
        
        // Query for expiring services
        $results = $serviceManager->getExpiringServices($daysAhead);
        $resultIds = array_map(fn($s) => $s->id, $results);
        
        // Verify service expiring exactly N days from now is included (boundary condition)
        expect($resultIds)->toContain($serviceId,
            "Service expiring exactly $daysAhead days from now should be included in results"
        );
        
        // Clean up this iteration's services
        $pdo->exec("DELETE FROM services WHERE project_id = " . (9200 + $i));
    }
    
    // Clean up
    $pdo->exec("DELETE FROM services WHERE project_id >= 9200 AND project_id < 9300");
    $pdo->exec("DELETE FROM projects WHERE id >= 9200 AND id < 9300");
})->group('property', 'reporting', 'expiring-services-filter');

test('high utilization filter returns all services with utilization above threshold', function () {
    $faker = Faker\Factory::create();
    $pdo = getTestDatabase();
    $repository = new ServiceRepository($pdo);
    $serviceManager = new ServiceManager($repository);
    
    // Get database's current date
    $stmt = $pdo->query("SELECT CURDATE() as today");
    $dbToday = $stmt->fetch(PDO::FETCH_ASSOC)['today'];
    
    // Clean up test data
    $pdo->exec("DELETE FROM services WHERE project_id >= 9300 AND project_id < 9400");
    $pdo->exec("DELETE FROM projects WHERE id >= 9300 AND id < 9400");
    
    // Ensure test client exists
    $pdo->exec("INSERT IGNORE INTO clients (id, name, contact_info) VALUES (1, 'Test Client', 'test@example.com')");
    
    // Create test projects for foreign key constraint
    for ($i = 0; $i < 100; $i++) {
        $pdo->exec("INSERT INTO projects (id, client_id, name, description) VALUES (" . (9300 + $i) . ", 1, 'Test Project $i', 'Test')");
    }
    
    // Run 100 iterations
    for ($i = 0; $i < 100; $i++) {
        // Generate random threshold
        $threshold = $faker->randomFloat(2, 0, 100);
        
        // Create a mix of services: some above threshold, some below
        $servicesAboveThreshold = [];
        $servicesBelowThreshold = [];
        
        // Create 3-5 services that should be in the result (utilization >= threshold)
        $numAbove = $faker->numberBetween(3, 5);
        for ($j = 0; $j < $numAbove; $j++) {
            $userLimit = $faker->numberBetween(100, 1000); // Use larger limits for precision
            // Calculate active_user_count to be at or above threshold
            // Add a small buffer to ensure we're definitely above threshold
            $minActiveUsers = (int) ceil(($threshold / 100) * $userLimit);
            $activeUserCount = $faker->numberBetween($minActiveUsers, $userLimit);
            
            // Verify the utilization is actually >= threshold before inserting
            $actualUtilization = ($activeUserCount / $userLimit) * 100;
            if ($actualUtilization < $threshold) {
                // Adjust to ensure we're at or above threshold
                $activeUserCount = (int) ceil(($threshold / 100) * $userLimit);
            }
            
            $serviceData = [
                'project_id' => 9300 + $i,
                'service_type' => $faker->randomElement(['web', 'mobile', 'other']),
                'user_limit' => $userLimit,
                'active_user_count' => $activeUserCount,
                'start_date' => date('Y-m-d', strtotime($dbToday . ' -1 year')),
                'end_date' => date('Y-m-d', strtotime($dbToday . ' +1 year')),
            ];
            
            $stmt = $pdo->prepare("
                INSERT INTO services (project_id, service_type, user_limit, active_user_count, start_date, end_date)
                VALUES (:project_id, :service_type, :user_limit, :active_user_count, :start_date, :end_date)
            ");
            $stmt->execute($serviceData);
            $servicesAboveThreshold[] = (int) $pdo->lastInsertId();
        }
        
        // Create 2-3 services that should NOT be in the result (utilization < threshold)
        $numBelow = $faker->numberBetween(2, 3);
        for ($j = 0; $j < $numBelow; $j++) {
            $userLimit = $faker->numberBetween(100, 1000); // Use larger limit for more precision
            // Calculate active_user_count to be below threshold
            $maxActiveUsers = (int) floor(($threshold / 100) * $userLimit);
            // Ensure we're strictly below threshold
            if ($maxActiveUsers > 0 && ($maxActiveUsers / $userLimit) * 100 >= $threshold) {
                $maxActiveUsers--;
            }
            $activeUserCount = $faker->numberBetween(0, max(0, $maxActiveUsers));
            
            $serviceData = [
                'project_id' => 9300 + $i,
                'service_type' => $faker->randomElement(['web', 'mobile', 'other']),
                'user_limit' => $userLimit,
                'active_user_count' => $activeUserCount,
                'start_date' => date('Y-m-d', strtotime($dbToday . ' -1 year')),
                'end_date' => date('Y-m-d', strtotime($dbToday . ' +1 year')),
            ];
            
            $stmt = $pdo->prepare("
                INSERT INTO services (project_id, service_type, user_limit, active_user_count, start_date, end_date)
                VALUES (:project_id, :service_type, :user_limit, :active_user_count, :start_date, :end_date)
            ");
            $stmt->execute($serviceData);
            $servicesBelowThreshold[] = (int) $pdo->lastInsertId();
        }
        
        // Query for high utilization services
        $results = $serviceManager->getHighUtilizationServices($threshold);
        $resultIds = array_map(fn($s) => $s->id, $results);
        
        // Verify completeness: all services above threshold are included (no false negatives)
        foreach ($servicesAboveThreshold as $expectedId) {
            expect($resultIds)->toContain($expectedId,
                "Service $expectedId with utilization >= $threshold% should be included in results"
            );
        }
        
        // Verify correctness: no services below threshold are included (no false positives)
        foreach ($servicesBelowThreshold as $unexpectedId) {
            expect($resultIds)->not->toContain($unexpectedId,
                "Service $unexpectedId with utilization < $threshold% should NOT be included in results"
            );
        }
        
        // Verify all returned services have utilization >= threshold
        foreach ($results as $service) {
            $utilization = ($service->activeUserCount / $service->userLimit) * 100;
            expect($utilization >= $threshold - 0.01)->toBeTrue(
                "Service {$service->id} utilization ($utilization%) should be >= threshold ($threshold%)"
            );
        }
        
        // Clean up this iteration's services
        $pdo->exec("DELETE FROM services WHERE project_id = " . (9300 + $i));
    }
    
    // Clean up
    $pdo->exec("DELETE FROM services WHERE project_id >= 9300 AND project_id < 9400");
    $pdo->exec("DELETE FROM projects WHERE id >= 9300 AND id < 9400");
})->group('property', 'reporting', 'high-utilization-filter');

test('high utilization filter handles edge case of services at exactly threshold', function () {
    $faker = Faker\Factory::create();
    $pdo = getTestDatabase();
    $repository = new ServiceRepository($pdo);
    $serviceManager = new ServiceManager($repository);
    
    // Get database's current date
    $stmt = $pdo->query("SELECT CURDATE() as today");
    $dbToday = $stmt->fetch(PDO::FETCH_ASSOC)['today'];
    
    // Clean up test data
    $pdo->exec("DELETE FROM services WHERE project_id >= 9400 AND project_id < 9500");
    $pdo->exec("DELETE FROM projects WHERE id >= 9400 AND id < 9500");
    
    // Ensure test client exists
    $pdo->exec("INSERT IGNORE INTO clients (id, name, contact_info) VALUES (1, 'Test Client', 'test@example.com')");
    
    // Create test projects for foreign key constraint
    for ($i = 0; $i < 100; $i++) {
        $pdo->exec("INSERT INTO projects (id, client_id, name, description) VALUES (" . (9400 + $i) . ", 1, 'Test Project $i', 'Test')");
    }
    
    // Run 100 iterations
    for ($i = 0; $i < 100; $i++) {
        // Generate random threshold (avoid very low thresholds for precision)
        $threshold = $faker->randomFloat(2, 10, 90);
        
        // Create a service with utilization at or very close to threshold
        $userLimit = 1000; // Use large number for precision
        $activeUserCount = (int) round(($threshold / 100) * $userLimit);
        
        // Ensure we're at or above threshold
        $actualUtilization = ($activeUserCount / $userLimit) * 100;
        if ($actualUtilization < $threshold) {
            $activeUserCount++;
        }
        
        $serviceData = [
            'project_id' => 9400 + $i,
            'service_type' => $faker->randomElement(['web', 'mobile', 'other']),
            'user_limit' => $userLimit,
            'active_user_count' => $activeUserCount,
            'start_date' => date('Y-m-d', strtotime($dbToday . ' -1 year')),
            'end_date' => date('Y-m-d', strtotime($dbToday . ' +1 year')),
        ];
        
        $stmt = $pdo->prepare("
            INSERT INTO services (project_id, service_type, user_limit, active_user_count, start_date, end_date)
            VALUES (:project_id, :service_type, :user_limit, :active_user_count, :start_date, :end_date)
        ");
        $stmt->execute($serviceData);
        $serviceId = (int) $pdo->lastInsertId();
        
        // Query for high utilization services
        $results = $serviceManager->getHighUtilizationServices($threshold);
        $resultIds = array_map(fn($s) => $s->id, $results);
        
        // Verify service at or above threshold is included (boundary condition)
        $actualUtilization = ($activeUserCount / $userLimit) * 100;
        expect($resultIds)->toContain($serviceId,
            "Service with utilization at threshold ($actualUtilization% >= $threshold%) should be included"
        );
        
        // Clean up this iteration's services
        $pdo->exec("DELETE FROM services WHERE project_id = " . (9400 + $i));
    }
    
    // Clean up
    $pdo->exec("DELETE FROM services WHERE project_id >= 9400 AND project_id < 9500");
    $pdo->exec("DELETE FROM projects WHERE id >= 9400 AND id < 9500");
})->group('property', 'reporting', 'high-utilization-filter');

test('high utilization filter handles edge case of 0% and 100% utilization', function () {
    $faker = Faker\Factory::create();
    $pdo = getTestDatabase();
    $repository = new ServiceRepository($pdo);
    $serviceManager = new ServiceManager($repository);
    
    // Get database's current date
    $stmt = $pdo->query("SELECT CURDATE() as today");
    $dbToday = $stmt->fetch(PDO::FETCH_ASSOC)['today'];
    
    // Clean up test data
    $pdo->exec("DELETE FROM services WHERE project_id >= 9500 AND project_id < 9600");
    $pdo->exec("DELETE FROM projects WHERE id >= 9500 AND id < 9600");
    
    // Ensure test client exists
    $pdo->exec("INSERT IGNORE INTO clients (id, name, contact_info) VALUES (1, 'Test Client', 'test@example.com')");
    
    // Create test projects for foreign key constraint
    for ($i = 0; $i < 100; $i++) {
        $pdo->exec("INSERT INTO projects (id, client_id, name, description) VALUES (" . (9500 + $i) . ", 1, 'Test Project $i', 'Test')");
    }
    
    // Run 100 iterations
    for ($i = 0; $i < 100; $i++) {
        $userLimit = $faker->numberBetween(10, 1000);
        
        // Create a service with 0% utilization
        $serviceData0 = [
            'project_id' => 9500 + $i,
            'service_type' => $faker->randomElement(['web', 'mobile', 'other']),
            'user_limit' => $userLimit,
            'active_user_count' => 0,
            'start_date' => date('Y-m-d', strtotime($dbToday . ' -1 year')),
            'end_date' => date('Y-m-d', strtotime($dbToday . ' +1 year')),
        ];
        
        $stmt = $pdo->prepare("
            INSERT INTO services (project_id, service_type, user_limit, active_user_count, start_date, end_date)
            VALUES (:project_id, :service_type, :user_limit, :active_user_count, :start_date, :end_date)
        ");
        $stmt->execute($serviceData0);
        $serviceId0 = (int) $pdo->lastInsertId();
        
        // Create a service with 100% utilization
        $serviceData100 = [
            'project_id' => 9500 + $i,
            'service_type' => $faker->randomElement(['web', 'mobile', 'other']),
            'user_limit' => $userLimit,
            'active_user_count' => $userLimit,
            'start_date' => date('Y-m-d', strtotime($dbToday . ' -1 year')),
            'end_date' => date('Y-m-d', strtotime($dbToday . ' +1 year')),
        ];
        
        $stmt->execute($serviceData100);
        $serviceId100 = (int) $pdo->lastInsertId();
        
        // Test with threshold 0: both should be included
        $results0 = $serviceManager->getHighUtilizationServices(0);
        $resultIds0 = array_map(fn($s) => $s->id, $results0);
        
        expect($resultIds0)->toContain($serviceId0,
            "Service with 0% utilization should be included when threshold is 0%"
        );
        expect($resultIds0)->toContain($serviceId100,
            "Service with 100% utilization should be included when threshold is 0%"
        );
        
        // Test with threshold 100: only 100% service should be included
        $results100 = $serviceManager->getHighUtilizationServices(100);
        $resultIds100 = array_map(fn($s) => $s->id, $results100);
        
        expect($resultIds100)->toContain($serviceId100,
            "Service with 100% utilization should be included when threshold is 100%"
        );
        expect($resultIds100)->not->toContain($serviceId0,
            "Service with 0% utilization should NOT be included when threshold is 100%"
        );
        
        // Test with threshold 50: only 100% service should be included
        $results50 = $serviceManager->getHighUtilizationServices(50);
        $resultIds50 = array_map(fn($s) => $s->id, $results50);
        
        expect($resultIds50)->toContain($serviceId100,
            "Service with 100% utilization should be included when threshold is 50%"
        );
        expect($resultIds50)->not->toContain($serviceId0,
            "Service with 0% utilization should NOT be included when threshold is 50%"
        );
        
        // Clean up this iteration's services
        $pdo->exec("DELETE FROM services WHERE project_id = " . (9500 + $i));
    }
    
    // Clean up
    $pdo->exec("DELETE FROM services WHERE project_id >= 9500 AND project_id < 9600");
    $pdo->exec("DELETE FROM projects WHERE id >= 9500 AND id < 9600");
})->group('property', 'reporting', 'high-utilization-filter');

<?php

use App\Models\Service;

/**
 * Feature: subscription-management-module
 * Property 15: Active Subscription Status
 * Property 16: Expired Subscription Status
 * Property 32: Utilization Calculation
 * 
 * For any service where the current date is >= start_date AND <= end_date, 
 * the subscription status should be reported as 'active' (isActive() returns true).
 * 
 * For any service where the current date > end_date, the subscription status 
 * should be reported as 'expired' (isActive() returns false).
 * 
 * For any service, the utilization percentage should equal 
 * (active_user_count / user_limit) × 100.
 * 
 * **Validates: Requirements 7.5, 7.6, 14.4**
 * 
 * These tests verify the Service model methods directly without database interaction,
 * ensuring correct subscription status logic and utilization calculation.
 */

test('service is active when current date is within subscription period', function () {
    $faker = Faker\Factory::create();
    
    // Run 100 iterations with different date scenarios
    for ($i = 0; $i < 100; $i++) {
        // Generate a date range where start_date <= end_date
        $startDate = $faker->dateTimeBetween('-2 years', '+1 year');
        $endDate = $faker->dateTimeBetween($startDate, '+3 years');
        
        // Generate a current date that falls within the range (start_date <= current_date <= end_date)
        $currentDate = $faker->dateTimeBetween($startDate, $endDate);
        
        $startDateStr = $startDate->format('Y-m-d');
        $endDateStr = $endDate->format('Y-m-d');
        $currentDateStr = $currentDate->format('Y-m-d');
        
        // Create service with random valid data
        $service = new Service(
            $faker->numberBetween(1, 1000), // projectId
            $faker->randomElement(['web', 'mobile', 'other']),
            $faker->numberBetween(1, 10000), // userLimit
            $startDateStr,
            $endDateStr,
            $faker->numberBetween(0, 100) // activeUserCount
        );
        
        // Test isActive() with the current date within the subscription period
        $isActive = $service->isActive($currentDateStr);
        
        // Verify the service is reported as active
        expect($isActive)->toBeTrue(
            "Service should be active when current date ($currentDateStr) is between start ($startDateStr) and end ($endDateStr)"
        );
    }
})->group('property', 'service-model', 'subscription-status');

test('service is active when current date equals start date', function () {
    $faker = Faker\Factory::create();
    
    // Run 100 iterations
    for ($i = 0; $i < 100; $i++) {
        $startDate = $faker->dateTimeBetween('-1 year', '+1 year');
        $endDate = $faker->dateTimeBetween($startDate, '+2 years');
        
        $startDateStr = $startDate->format('Y-m-d');
        $endDateStr = $endDate->format('Y-m-d');
        
        $service = new Service(
            $faker->numberBetween(1, 1000),
            $faker->randomElement(['web', 'mobile', 'other']),
            $faker->numberBetween(1, 10000),
            $startDateStr,
            $endDateStr,
            $faker->numberBetween(0, 100)
        );
        
        // Test with current date equal to start date (boundary condition)
        $isActive = $service->isActive($startDateStr);
        
        expect($isActive)->toBeTrue(
            "Service should be active when current date equals start date ($startDateStr)"
        );
    }
})->group('property', 'service-model', 'subscription-status');

test('service is active when current date equals end date', function () {
    $faker = Faker\Factory::create();
    
    // Run 100 iterations
    for ($i = 0; $i < 100; $i++) {
        $startDate = $faker->dateTimeBetween('-1 year', '+1 year');
        $endDate = $faker->dateTimeBetween($startDate, '+2 years');
        
        $startDateStr = $startDate->format('Y-m-d');
        $endDateStr = $endDate->format('Y-m-d');
        
        $service = new Service(
            $faker->numberBetween(1, 1000),
            $faker->randomElement(['web', 'mobile', 'other']),
            $faker->numberBetween(1, 10000),
            $startDateStr,
            $endDateStr,
            $faker->numberBetween(0, 100)
        );
        
        // Test with current date equal to end date (boundary condition)
        $isActive = $service->isActive($endDateStr);
        
        expect($isActive)->toBeTrue(
            "Service should be active when current date equals end date ($endDateStr)"
        );
    }
})->group('property', 'service-model', 'subscription-status');

test('service is expired when current date is after end date', function () {
    $faker = Faker\Factory::create();
    
    // Run 100 iterations with different date scenarios
    for ($i = 0; $i < 100; $i++) {
        // Generate a date range where start_date <= end_date
        $startDate = $faker->dateTimeBetween('-3 years', '-1 year');
        $endDate = $faker->dateTimeBetween($startDate, '-6 months');
        
        // Generate a current date that falls after the end date
        $currentDate = $faker->dateTimeBetween($endDate->modify('+1 day'), '+2 years');
        
        $startDateStr = $startDate->format('Y-m-d');
        $endDateStr = $endDate->format('Y-m-d');
        $currentDateStr = $currentDate->format('Y-m-d');
        
        // Create service with random valid data
        $service = new Service(
            $faker->numberBetween(1, 1000),
            $faker->randomElement(['web', 'mobile', 'other']),
            $faker->numberBetween(1, 10000),
            $startDateStr,
            $endDateStr,
            $faker->numberBetween(0, 100)
        );
        
        // Test isActive() with the current date after the subscription period
        $isActive = $service->isActive($currentDateStr);
        
        // Verify the service is reported as expired (not active)
        expect($isActive)->toBeFalse(
            "Service should be expired when current date ($currentDateStr) is after end date ($endDateStr)"
        );
    }
})->group('property', 'service-model', 'subscription-status');

test('service is not active when current date is before start date', function () {
    $faker = Faker\Factory::create();
    
    // Run 100 iterations
    for ($i = 0; $i < 100; $i++) {
        // Generate a future date range
        $startDate = $faker->dateTimeBetween('+1 month', '+1 year');
        $endDate = $faker->dateTimeBetween($startDate, '+2 years');
        
        // Generate a current date before the start date
        $currentDate = $faker->dateTimeBetween('-2 years', $startDate->modify('-1 day'));
        
        $startDateStr = $startDate->format('Y-m-d');
        $endDateStr = $endDate->format('Y-m-d');
        $currentDateStr = $currentDate->format('Y-m-d');
        
        $service = new Service(
            $faker->numberBetween(1, 1000),
            $faker->randomElement(['web', 'mobile', 'other']),
            $faker->numberBetween(1, 10000),
            $startDateStr,
            $endDateStr,
            $faker->numberBetween(0, 100)
        );
        
        // Test with current date before start date
        $isActive = $service->isActive($currentDateStr);
        
        expect($isActive)->toBeFalse(
            "Service should not be active when current date ($currentDateStr) is before start date ($startDateStr)"
        );
    }
})->group('property', 'service-model', 'subscription-status');

test('utilization percentage is calculated correctly', function () {
    $faker = Faker\Factory::create();
    
    // Run 100 iterations with different utilization scenarios
    for ($i = 0; $i < 100; $i++) {
        // Generate random user limit and active user count
        $userLimit = $faker->numberBetween(1, 10000);
        $activeUserCount = $faker->numberBetween(0, $userLimit);
        
        // Calculate expected utilization percentage
        $expectedUtilization = ($activeUserCount / $userLimit) * 100;
        
        // Create service
        $service = new Service(
            $faker->numberBetween(1, 1000),
            $faker->randomElement(['web', 'mobile', 'other']),
            $userLimit,
            $faker->date('Y-m-d'),
            $faker->date('Y-m-d'),
            $activeUserCount
        );
        
        // Get utilization percentage from the model
        $actualUtilization = $service->getUtilizationPercentage();
        
        // Verify the calculation is correct (use toEqual for float comparison)
        expect($actualUtilization)->toEqual($expectedUtilization,
            "Utilization should be ($activeUserCount / $userLimit) × 100 = $expectedUtilization, got $actualUtilization"
        );
    }
})->group('property', 'service-model', 'utilization');

test('utilization percentage is zero when active user count is zero', function () {
    $faker = Faker\Factory::create();
    
    // Run 100 iterations
    for ($i = 0; $i < 100; $i++) {
        $userLimit = $faker->numberBetween(1, 10000);
        
        $service = new Service(
            $faker->numberBetween(1, 1000),
            $faker->randomElement(['web', 'mobile', 'other']),
            $userLimit,
            $faker->date('Y-m-d'),
            $faker->date('Y-m-d'),
            0 // activeUserCount = 0
        );
        
        $utilization = $service->getUtilizationPercentage();
        
        expect($utilization)->toBe(0.0,
            "Utilization should be 0% when active user count is 0"
        );
    }
})->group('property', 'service-model', 'utilization');

test('utilization percentage is 100 when at full capacity', function () {
    $faker = Faker\Factory::create();
    
    // Run 100 iterations
    for ($i = 0; $i < 100; $i++) {
        $userLimit = $faker->numberBetween(1, 10000);
        
        $service = new Service(
            $faker->numberBetween(1, 1000),
            $faker->randomElement(['web', 'mobile', 'other']),
            $userLimit,
            $faker->date('Y-m-d'),
            $faker->date('Y-m-d'),
            $userLimit // activeUserCount = userLimit (full capacity)
        );
        
        $utilization = $service->getUtilizationPercentage();
        
        expect($utilization)->toBe(100.0,
            "Utilization should be 100% when active user count equals user limit"
        );
    }
})->group('property', 'service-model', 'utilization');

test('utilization percentage handles various capacity levels', function () {
    $faker = Faker\Factory::create();
    
    // Run 100 iterations testing specific utilization levels
    for ($i = 0; $i < 100; $i++) {
        // Test various utilization percentages
        $targetPercentage = $faker->randomFloat(2, 0, 100);
        $userLimit = $faker->numberBetween(100, 10000);
        $activeUserCount = (int) round(($targetPercentage / 100) * $userLimit);
        
        // Ensure activeUserCount doesn't exceed userLimit
        $activeUserCount = min($activeUserCount, $userLimit);
        
        $service = new Service(
            $faker->numberBetween(1, 1000),
            $faker->randomElement(['web', 'mobile', 'other']),
            $userLimit,
            $faker->date('Y-m-d'),
            $faker->date('Y-m-d'),
            $activeUserCount
        );
        
        $actualUtilization = $service->getUtilizationPercentage();
        $expectedUtilization = ($activeUserCount / $userLimit) * 100;
        
        // Allow for small floating point differences
        expect(abs($actualUtilization - $expectedUtilization))->toBeLessThan(0.01,
            "Utilization calculation should be accurate for $activeUserCount / $userLimit"
        );
    }
})->group('property', 'service-model', 'utilization');

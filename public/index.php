<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Database;
use App\Router;
use App\Repositories\ClientRepository;
use App\Repositories\ProjectRepository;
use App\Repositories\ServiceRepository;
use App\Repositories\UserRepository;
use App\Repositories\SubscriptionHistoryRepository;
use App\Repositories\ActivityLogRepository;
use App\Services\ClientService;
use App\Services\ProjectService;
use App\Services\ServiceManager;
use App\Services\ValidationService;
use App\Services\UserService;
use App\Services\SubscriptionLifecycleManager;
use App\Controllers\ClientController;
use App\Controllers\ProjectController;
use App\Controllers\ServiceController;
use App\Controllers\ValidationController;
use App\Controllers\UserController;
use App\Controllers\SubscriptionController;
use App\Controllers\ReportingController;
use App\Controllers\ActivityLogController;
use App\Controllers\ExternalApiController;

// Initialize Database
try {
    $db = Database::getInstance();
} catch (Exception $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

// Repositories
$clientRepo   = new ClientRepository($db);
$projectRepo  = new ProjectRepository($db);
$serviceRepo  = new ServiceRepository($db);
$userRepo     = new UserRepository($db);
$historyRepo  = new SubscriptionHistoryRepository($db);
$activityRepo = new ActivityLogRepository($db);

// Services
$clientService     = new ClientService($clientRepo);
$projectService    = new ProjectService($projectRepo);
$serviceManager    = new ServiceManager($serviceRepo);
$validationService = new ValidationService($serviceRepo);
$userService       = new UserService($userRepo, $serviceRepo, $validationService);
$lifecycleManager  = new SubscriptionLifecycleManager($serviceRepo, $historyRepo);
$jwtService        = new App\Services\JwtService();

// Controllers (inject activityRepo for logging and jwtService for auth)
$clientCtrl       = new ClientController($clientService, $activityRepo, $jwtService);
$projectCtrl      = new ProjectController($projectService, $activityRepo, $jwtService);
$serviceCtrl      = new ServiceController($serviceManager, $activityRepo, $jwtService);
$validationCtrl   = new ValidationController($validationService);
$userCtrl         = new UserController($userService, $activityRepo, $jwtService);
$subscriptionCtrl = new SubscriptionController($lifecycleManager, $serviceManager, $activityRepo, $jwtService);
$reportingCtrl    = new ReportingController($serviceManager, $clientService, $projectService, $jwtService);
$activityCtrl     = new ActivityLogController($activityRepo, $jwtService);
$externalApiCtrl  = new ExternalApiController($projectRepo, $serviceRepo, $activityRepo, $userService);
$authCtrl         = new App\Controllers\AuthController($jwtService);

// Router
$router = new Router();

// Root Route (Frontend)
$router->add('GET', '/', function() {
    require_once __DIR__ . '/index.html';
});

// Client Routes
$router->add('GET', '/api/clients', [$clientCtrl, 'list']);
$router->add('GET', '/api/clients/{id}', [$clientCtrl, 'get']);
$router->add('POST', '/api/clients', [$clientCtrl, 'create']);
$router->add('PUT', '/api/clients/{id}', [$clientCtrl, 'update']);
$router->add('DELETE', '/api/clients/{id}', [$clientCtrl, 'delete']);

// Project Routes
$router->add('GET', '/api/clients/{clientId}/projects', [$projectCtrl, 'listByClient']);
$router->add('GET', '/api/projects/{id}', [$projectCtrl, 'get']);
$router->add('POST', '/api/projects', [$projectCtrl, 'create']);
$router->add('PUT', '/api/projects/{id}', [$projectCtrl, 'update']);
$router->add('DELETE', '/api/projects/{id}', [$projectCtrl, 'delete']);

// Service Routes
$router->add('GET', '/api/projects/{projectId}/services', [$serviceCtrl, 'listByProject']);
$router->add('GET', '/api/services/{id}', [$serviceCtrl, 'get']);
$router->add('POST', '/api/services', [$serviceCtrl, 'create']);
$router->add('PUT', '/api/services/{id}', [$serviceCtrl, 'update']);
$router->add('DELETE', '/api/services/{id}', [$serviceCtrl, 'delete']);

// Validation Routes
$router->add('POST', '/api/services/{serviceId}/validate-user', [$validationCtrl, 'validate']);

// User Routes
$router->add('GET', '/api/services/{serviceId}/users', [$userCtrl, 'listByService']);
$router->add('POST', '/api/services/{serviceId}/users', [$userCtrl, 'register']);
$router->add('DELETE', '/api/users/{userId}', [$userCtrl, 'deactivate']);

// Subscription Routes
$router->add('POST', '/api/services/{serviceId}/renew', [$subscriptionCtrl, 'renew']);
$router->add('POST', '/api/services/{serviceId}/extend', [$subscriptionCtrl, 'extend']);
$router->add('GET', '/api/services/{serviceId}/status', [$subscriptionCtrl, 'status']);

// Reporting Routes
$router->add('GET', '/api/clients/{clientId}/utilization', [$reportingCtrl, 'clientUtilization']);
$router->add('GET', '/api/projects/{projectId}/utilization', [$reportingCtrl, 'projectUtilization']);
$router->add('GET', '/api/services/{serviceId}/utilization', [$reportingCtrl, 'serviceUtilization']);
$router->add('GET', '/api/services/expiring', [$reportingCtrl, 'expiringServices']);
$router->add('GET', '/api/services/high-utilization', [$reportingCtrl, 'highUtilization']);

// Activity Log Routes
$router->add('GET', '/api/activity-log', [$activityCtrl, 'list']);

// Auth Routes
$router->add('POST', '/api/auth/login', [$authCtrl, 'login']);
$router->add('GET', '/api/auth/validate', [$authCtrl, 'validateToken']);

// External Public API
$router->add('POST', '/api/v1/validate-subscription', [$externalApiCtrl, 'validateSubscription']);
$router->add('POST', '/api/v1/register-user', [$externalApiCtrl, 'registerUser']);

// Handle Request
$router->handle($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);

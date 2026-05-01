<?php

namespace App;

class Router
{
    private array $routes = [];

    public function add(string $method, string $path, callable|array $handler): void
    {
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }
        $path = preg_replace('/\{(\w+)\}/', '(?P<$1>\d+)', $path);
        $this->routes[] = [
            'method' => $method,
            'path' => '#^' . $path . '$#',
            'handler' => $handler
        ];
    }

    public function handle(string $method, string $uri): void
    {
        // Handle CORS preflight
        if ($method === 'OPTIONS') {
            header("Access-Control-Allow-Origin: *");
            header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
            header("Access-Control-Allow-Headers: Content-Type, Authorization");
            exit;
        }

        header("Access-Control-Allow-Origin: *");

        $uri = parse_url($uri, PHP_URL_PATH);
        if ($uri !== '/') {
            $uri = rtrim($uri, '/');
        }
        
        // Handle subdirectory: detect the project root relative to the public folder
        $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);
        $projectRoot = str_replace('\\', '/', dirname(dirname($scriptName)));
        
        if ($projectRoot !== '/' && !empty($projectRoot) && strpos($uri, $projectRoot) === 0) {
            $uri = substr($uri, strlen($projectRoot));
        }
        
        // Also handle the /public part if it's explicitly in the URI
        if (strpos($uri, '/public') === 0) {
            $uri = substr($uri, 7);
        }
        
        if (empty($uri) || $uri === '' || $uri === '//') $uri = '/';
        if ($uri !== '/') {
            $uri = rtrim($uri, '/');
        }

        foreach ($this->routes as $route) {
            $routePath = $route['path'];
            // Allow optional trailing slash in matching
            if ($route['method'] === $method && preg_match($routePath, $uri, $matches)) {
                $handler = $route['handler'];
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                
                if (is_array($handler)) {
                    $controller = $handler[0];
                    $action = $handler[1];
                    call_user_func_array([$controller, $action], $params);
                } else {
                    call_user_func_array($handler, $params);
                }
                return;
            }
        }

        http_response_code(404);
        echo json_encode(['error' => 'Route not found']);
    }
}

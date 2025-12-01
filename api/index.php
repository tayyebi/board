<?php
declare(strict_types=1);

/**
 * API Router - RESTful API entry point
 */

require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/controllers/lanes.php';
require_once __DIR__ . '/controllers/tasks.php';

// Enable CORS
cors_headers();

// Initialize session for CSRF
init_session();

// Data directory (parent of api folder)
$dataDir = dirname(__DIR__);

// Parse request
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';

// Remove query string and base path
$path = parse_url($requestUri, PHP_URL_PATH);
$basePath = dirname($_SERVER['SCRIPT_NAME']);
if ($basePath !== '/' && strpos($path, $basePath) === 0) {
    $path = substr($path, strlen($basePath));
}

// Clean path
$path = trim($path, '/');
$pathParts = $path !== '' ? explode('/', $path) : [];

// Route to controller
$controller = array_shift($pathParts) ?? '';

try {
    switch ($controller) {
        case '':
        case 'status':
            // API status endpoint
            json_response([
                'status' => 'ok',
                'version' => '1.0',
                'csrf' => get_csrf_token(),
                'endpoints' => [
                    'GET /api/status' => 'API status',
                    'GET /api/lanes' => 'Get swimlanes structure',
                    'POST /api/lanes/swimlane' => 'Add swimlane',
                    'PUT /api/lanes/swimlane' => 'Rename swimlane',
                    'DELETE /api/lanes/swimlane' => 'Delete swimlane',
                    'POST /api/lanes/column' => 'Add column',
                    'PUT /api/lanes/column' => 'Rename column',
                    'DELETE /api/lanes/column' => 'Delete column',
                    'POST /api/lanes/meta' => 'Update colors and order',
                    'GET /api/tasks' => 'Get tasks grouped',
                    'GET /api/tasks/list' => 'Get all tasks',
                    'GET /api/tasks/stats' => 'Get task stats',
                    'GET /api/tasks/{id}' => 'Get task by ID',
                    'POST /api/tasks' => 'Create task',
                    'PUT /api/tasks/{id}' => 'Update task',
                    'PUT /api/tasks/{id}/move' => 'Move task',
                    'DELETE /api/tasks/{id}' => 'Delete task'
                ]
            ]);
            break;
            
        case 'lanes':
            $lanesController = new LanesController($dataDir);
            $lanesController->handle($method, $pathParts);
            break;
            
        case 'tasks':
            $tasksController = new TasksController($dataDir);
            $tasksController->handle($method, $pathParts);
            break;
            
        default:
            json_error('Not found', 404);
    }
} catch (Exception $e) {
    json_error('Internal server error: ' . $e->getMessage(), 500);
}

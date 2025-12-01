<?php
/**
 * Router for PHP built-in server
 * Routes requests to API or serves static UI files
 */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Handle API requests
if (str_starts_with($uri, '/api')) {
    // Remove /api prefix and route to api/index.php
    $_SERVER['REQUEST_URI'] = substr($uri, 4) ?: '/';
    require __DIR__ . '/api/index.php';
    return true;
}

// Handle UI static files
if ($uri === '/' || $uri === '') {
    $uri = '/index.html';
}

$uiPath = __DIR__ . '/ui' . $uri;

// If file exists in ui directory, serve it
if (file_exists($uiPath) && is_file($uiPath)) {
    // Set appropriate content type
    $ext = pathinfo($uiPath, PATHINFO_EXTENSION);
    $contentTypes = [
        'html' => 'text/html',
        'htm' => 'text/html',
        'css' => 'text/css',
        'js' => 'application/javascript',
        'json' => 'application/json',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon'
    ];
    
    if (isset($contentTypes[$ext])) {
        header('Content-Type: ' . $contentTypes[$ext]);
    }
    
    readfile($uiPath);
    return true;
}

// Fallback to legacy index.php if nothing else matches
if (file_exists(__DIR__ . '/index.php') && $uri !== '/router.php') {
    require __DIR__ . '/index.php';
    return true;
}

// 404 for everything else
http_response_code(404);
echo 'Not Found';
return true;

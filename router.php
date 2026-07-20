<?php
$path = parse_url($_SERVER['REST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$base = __DIR__ . '/www';

if ($path === '/' || $path === '') {
    require $base . '/index.php';
    exit;
}

if ($path === '/web' || $path === '/web/') {
    require $base . '/web/index.php';
    exit;
}

if ($path === '/api' || str_starts_with($path, '/api/')) {
    require $base . '/api/index.php';
    exit;
}

$target = $base . $path;

if (is_file($target)) {
    return false;
}

if (is_dir($target)) {
    $index = rtrim($target, '/\\') . '/index.php';
    if (is_file($index)) {
        require $index;
        exit;
    }
}

http_response_code(404);
echo 'Not Found';

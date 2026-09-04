<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if ($path === '/') {
    require __DIR__ . '/dashboard.php';
    return;
}
if ($path === '/render') {
    require __DIR__ . '/render.php';
    return;
}

http_response_code(404);
header('Content-Type: text/plain; charset=UTF-8');
echo "Not Found\n";

<?php

declare(strict_types=1);

use App\Config\UpdatePageRepository;
use App\Domain\PublicReleaseStatus;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Allow: GET, HEAD');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow, noarchive');
header('Cache-Control: no-store');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'HEAD'], true)) {
    http_response_code(405);
    echo '{"error":"method_not_allowed"}';
    return;
}

try {
    /** @var array{updatePagesPath: string, allowedHosts: list<string>} $gameConfig */
    $gameConfig = require dirname(__DIR__, 2) . '/config/game.php';
    $repository = new UpdatePageRepository(
        $gameConfig['updatePagesPath'],
        $gameConfig['allowedHosts'],
    );
    $payload = (new PublicReleaseStatus($repository, new App\SystemClock()))->get();
    $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    http_response_code(200);
} catch (Throwable $exception) {
    $body = '{"error":"temporarily_unavailable"}';
    http_response_code(503);
}

if ($method !== 'HEAD') {
    echo $body;
}

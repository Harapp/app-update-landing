<?php

declare(strict_types=1);

function requireLocalPreviewRequest(): void
{
    $remoteAddress = $_SERVER['REMOTE_ADDR'] ?? '';
    if (PHP_SAPI === 'cli-server'
        && getenv('EVENT_UPDATE_PREVIEW') === '1'
        && in_array($remoteAddress, ['127.0.0.1', '::1'], true)
    ) {
        return;
    }

    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Not Found\n";
    exit;
}

requireLocalPreviewRequest();

require dirname(__DIR__) . '/vendor/autoload.php';
require __DIR__ . '/PreviewScenarioFactory.php';

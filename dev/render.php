<?php

declare(strict_types=1);

use App\Presentation\HtmlRenderer;
use App\Presentation\TemplateRegistry;
use AppUpdateLanding\Development\PreviewScenarioFactory;

header('Content-Type: text/html; charset=UTF-8');
header("Content-Security-Policy: default-src 'none'; img-src https:; style-src 'unsafe-inline'; base-uri 'none'; frame-ancestors 'self'; form-action 'none'");
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow, noarchive');
header('Cache-Control: no-store');

$scenario = isset($_GET['scenario']) && is_string($_GET['scenario']) ? $_GET['scenario'] : '';
$locale = isset($_GET['locale']) && is_string($_GET['locale']) ? $_GET['locale'] : '';
$platform = isset($_GET['platform']) && is_string($_GET['platform']) ? $_GET['platform'] : '';

try {
    $viewModel = (new PreviewScenarioFactory(dirname(__DIR__)))->create($scenario, $locale, $platform);
    header('X-Preview-State: ' . $viewModel->state);
    $renderer = new HtmlRenderer(new TemplateRegistry(dirname(__DIR__) . '/templates'));
    echo $renderer->render($viewModel);
} catch (InvalidArgumentException $exception) {
    http_response_code(400);
    echo '<!doctype html><html lang="en"><meta charset="utf-8"><title>Invalid preview</title><p>Invalid preview selection.</p>';
} catch (Throwable $exception) {
    http_response_code(500);
    echo '<!doctype html><html lang="en"><meta charset="utf-8"><title>Preview error</title><p>Unable to render the preview.</p>';
}

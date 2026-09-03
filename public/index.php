<?php

declare(strict_types=1);

use App\Config\ThemeRepository;
use App\Config\UpdatePageRepository;
use App\Domain\LocaleResolver;
use App\Domain\UpdatePageEvaluator;
use App\Http\RequestValidator;
use App\Presentation\HtmlRenderer;
use App\Presentation\TemplateRegistry;
use App\Presentation\UpdatePageViewModel;

require dirname(__DIR__) . '/vendor/autoload.php';

header('Content-Type: text/html; charset=UTF-8');
header("Content-Security-Policy: default-src 'none'; img-src https:; style-src 'unsafe-inline'; base-uri 'none'; frame-ancestors 'none'; form-action 'none'");
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store');

$viewModel = UpdatePageViewModel::unavailable();
$statusCode = 503;

try {
    $request = (new RequestValidator())->validate($_GET);
    $repository = new UpdatePageRepository(
        dirname(__DIR__) . '/games/default/update-pages.json',
        ['cdn.example.com', 'neko.harapeco.okinawa', 'apps.apple.com', 'play.google.com', 'example.com']
    );
    $theme = (new ThemeRepository(
        dirname(__DIR__) . '/games/default/theme.json',
        ['cdn.example.com', 'neko.harapeco.okinawa', 'apps.apple.com', 'play.google.com', 'example.com']
    ))->load();
    $viewModel = (new UpdatePageEvaluator($repository, new App\SystemClock(), new LocaleResolver(), $theme))->evaluate($request);
    $statusCode = $viewModel->state === 'unavailable' ? 404 : 200;
} catch (InvalidArgumentException $exception) {
    $statusCode = 400;
} catch (Throwable $exception) {
    $statusCode = 503;
}

http_response_code($statusCode);

$renderer = new HtmlRenderer(new TemplateRegistry(dirname(__DIR__) . '/templates'));
echo $renderer->render($viewModel);

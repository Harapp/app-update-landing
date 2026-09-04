<?php

declare(strict_types=1);

use App\Config\ThemeRepository;
use App\Config\UiTextRepository;
use App\Config\UpdatePageRepository;
use App\Domain\LocaleResolver;
use App\Domain\UpdatePageEvaluator;
use App\Http\PlatformResolver;
use App\Http\RequestValidator;
use App\Presentation\HtmlRenderer;
use App\Presentation\TemplateRegistry;
use App\Presentation\UpdatePageViewModel;

require dirname(__DIR__) . '/vendor/autoload.php';

header('Content-Type: text/html; charset=UTF-8');
header("Content-Security-Policy: default-src 'none'; img-src https:; style-src 'unsafe-inline'; base-uri 'none'; frame-ancestors 'none'; form-action 'none'");
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow, noarchive');
header('Cache-Control: no-store');

$viewModel = UpdatePageViewModel::unavailable();
$statusCode = 503;

try {
    $query = $_GET;
    $query['platform'] = (new PlatformResolver())->resolve(
        $query['platform'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null,
    );
    $request = (new RequestValidator())->validate($query);
    /** @var array{key: string, updatePagesPath: string, themePath: string, uiTextsPath: string, allowedHosts: list<string>} $gameConfig */
    $gameConfig = require dirname(__DIR__) . '/config/game.php';
    $repository = new UpdatePageRepository(
        $gameConfig['updatePagesPath'],
        $gameConfig['allowedHosts'],
    );
    $theme = (new ThemeRepository(
        $gameConfig['themePath'],
        $gameConfig['allowedHosts']
    ))->load();
    $uiTexts = (new UiTextRepository($gameConfig['uiTextsPath']))->load();
    $viewModel = (new UpdatePageEvaluator(
        $repository,
        new App\SystemClock(),
        new LocaleResolver(),
        $theme,
        $uiTexts,
    ))->evaluate($request);
    $statusCode = $viewModel->state === 'unavailable' ? 404 : 200;
} catch (InvalidArgumentException $exception) {
    $statusCode = 400;
} catch (Throwable $exception) {
    $statusCode = 503;
}

http_response_code($statusCode);

$renderer = new HtmlRenderer(new TemplateRegistry(dirname(__DIR__) . '/templates'));
echo $renderer->render($viewModel);

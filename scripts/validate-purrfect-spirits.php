<?php

declare(strict_types=1);

use App\Config\ThemeRepository;
use App\Config\UpdatePageRepository;

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

$game = require $root . '/config/game.php';

$expected = [
    'key' => 'purrfect-spirits',
    'updatePagesPath' => $root . '/games/purrfect-spirits/update-pages.json',
    'themePath' => $root . '/games/purrfect-spirits/theme.json',
];

foreach ($expected as $key => $value) {
    if (($game[$key] ?? null) !== $value) {
        fwrite(STDERR, "Fixed game configuration validation failed.\n");
        exit(1);
    }
}

$allowedHosts = $game['allowedHosts'] ?? [];
if (!is_array($allowedHosts)) {
    fwrite(STDERR, "Fixed game configuration validation failed.\n");
    exit(1);
}

(new UpdatePageRepository($game['updatePagesPath'], $allowedHosts))->findByTargetVersion('0.1.0');
(new ThemeRepository($game['themePath'], $allowedHosts))->load();

fwrite(STDOUT, "PurrfectSpirits configuration and JSON Schema validation passed.\n");

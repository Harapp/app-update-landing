<?php

declare(strict_types=1);

// The deployed site is fixed to one game. Change this manifest when creating
// another game's deployment; do not select a game from request parameters.
return [
    'key' => 'purrfect-spirits',
    'updatePagesPath' => dirname(__DIR__) . '/games/purrfect-spirits/update-pages.json',
    'themePath' => dirname(__DIR__) . '/games/purrfect-spirits/theme.json',
    'uiTextsPath' => dirname(__DIR__) . '/templates/event-update/ui-texts.json',
    'allowedHosts' => [
        'neko.harapeco.okinawa',
        'itunes.apple.com',
        'play.google.com',
        'www.harapeco.okinawa',
    ],
];

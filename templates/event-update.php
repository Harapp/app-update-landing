<?php

declare(strict_types=1);

/** @var \App\Presentation\UpdatePageViewModel $viewModel */
$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?><!doctype html>
<html lang="<?= $escape($viewModel->locale) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>App update</title>
    <style>
        :root { color-scheme: light; font-family: system-ui, sans-serif; --accent-color: <?= $escape($viewModel->theme['accentColor']) ?>; }
        body { margin: 0; background: <?= $escape($viewModel->theme['backgroundColor']) ?>; color: <?= $escape($viewModel->theme['textColor']) ?>; }
        main { box-sizing: border-box; width: min(100% - 2rem, <?= $viewModel->theme['maxContentWidth'] ?>px); margin: 2rem auto; overflow: hidden; border-radius: 1rem; background: #fff; box-shadow: 0 0.5rem 2rem #2021241f; }
        .content { padding: 1.5rem; }
        .banner { display: block; width: 100%; height: auto; }
        h1 { margin: 0 0 1rem; font-size: 1.25rem; }
        .status { margin: 0 0 1rem; font-weight: 600; }
        .version, .os-version { margin: .5rem 0; color: #4b5563; }
        .update-link { display: inline-flex; flex-direction: column; align-items: center; min-width: 12rem; margin-top: 1rem; padding: .8rem 1.25rem; border-radius: .6rem; background: <?= $escape($viewModel->theme['primaryColor']) ?>; color: #fff; font-weight: 700; text-decoration: none; }
        .update-link small { margin-top: .2rem; font-size: .9rem; }
        .notice { margin: 1.5rem 0 0; color: var(--accent-color); font-size: .85rem; }
    </style>
</head>
<body>
<main>
    <?php if ($viewModel->theme['logoUrl'] !== null): ?>
        <img class="logo" src="<?= $escape($viewModel->theme['logoUrl']) ?>" alt="App logo">
    <?php endif; ?>
    <?php if ($viewModel->imageUrl !== null): ?>
        <img class="banner" src="<?= $escape($viewModel->imageUrl) ?>" alt="<?= $escape($viewModel->imageAlt ?? '') ?>">
    <?php endif; ?>
    <div class="content">
        <?php if ($viewModel->description !== ''): ?>
            <h1><?= $escape($viewModel->description) ?></h1>
        <?php endif; ?>
        <p class="status"><?= $escape($viewModel->statusMessage) ?></p>
        <?php if ($viewModel->currentVersion !== null && $viewModel->targetVersion !== null): ?>
            <p class="version">Current: V<?= $escape($viewModel->currentVersion) ?> · Target: V<?= $escape($viewModel->targetVersion) ?></p>
        <?php endif; ?>
        <?php if ($viewModel->osVersion !== null && $viewModel->minimumOsVersion !== null): ?>
            <p class="os-version">OS: <?= $escape($viewModel->osVersion) ?> · Required: <?= $escape($viewModel->minimumOsVersion) ?></p>
        <?php endif; ?>
        <?php if ($viewModel->showUpdate && $viewModel->destinationUrl !== null && $viewModel->targetVersion !== null): ?>
            <a class="update-link" href="<?= $escape($viewModel->destinationUrl) ?>" aria-label="Update to version <?= $escape($viewModel->targetVersion) ?>">
                <span>V<?= $escape($viewModel->targetVersion) ?></span>
                <small>Update</small>
            </a>
        <?php endif; ?>
        <?php if ($viewModel->showStoreNotice): ?>
            <p class="notice">Updates may take some time to appear on the App Store or Google Play. If the update is not available yet, please try again later.</p>
        <?php endif; ?>
    </div>
</main>
</body>
</html>

<?php

declare(strict_types=1);

/** @var \App\Presentation\UpdatePageViewModel $viewModel */
$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$pageTitle = $viewModel->description !== '' ? $viewModel->description : 'App update';
?><!doctype html>
<html lang="<?= $escape($viewModel->locale) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $escape($pageTitle) ?></title>
    <meta name="description" content="<?= $escape($viewModel->description) ?>">
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= $escape($pageTitle) ?>">
    <meta property="og:description" content="<?= $escape($viewModel->description) ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= $escape($pageTitle) ?>">
    <meta name="twitter:description" content="<?= $escape($viewModel->description) ?>">
    <?php if ($viewModel->imageUrl !== null): ?>
        <meta property="og:image" content="<?= $escape($viewModel->imageUrl) ?>">
        <meta property="og:image:alt" content="<?= $escape($viewModel->imageAlt ?? '') ?>">
        <meta name="twitter:image" content="<?= $escape($viewModel->imageUrl) ?>">
        <meta name="twitter:image:alt" content="<?= $escape($viewModel->imageAlt ?? '') ?>">
    <?php endif; ?>
    <style>
        :root { color-scheme: light; font-family: system-ui, sans-serif; }
        body { margin: 0; background: <?= $escape($viewModel->theme['backgroundColor']) ?>; color: <?= $escape($viewModel->theme['textColor']) ?>; }
        main { box-sizing: border-box; width: min(100% - 2rem, <?= $viewModel->theme['maxContentWidth'] ?>px); margin: 2rem auto; overflow: hidden; border-radius: 1rem; background: #fff; box-shadow: 0 0.5rem 2rem #2021241f; }
        .content { padding: 1.5rem; }
        .banner { display: block; width: 100%; height: auto; }
        h1 { margin: 0 0 1rem; font-size: 1.25rem; }
        .status { margin: 0 0 1rem; font-weight: 600; }
        .os-version { margin: .5rem 0; color: #4b5563; }
        .update-action { display: flex; justify-content: center; margin-top: 1.5rem; }
        .update-link { box-sizing: border-box; display: inline-flex; flex-direction: column; align-items: center; justify-content: center; min-width: 12rem; padding: .8rem 1.25rem; border-radius: .6rem; background: <?= $escape($viewModel->theme['primaryColor']) ?>; color: #fff; font-weight: 700; line-height: 1.25; text-align: center; text-decoration: none; }
        .update-link small { margin-top: .2rem; font-size: .9rem; }
        .notice { margin: 1.75rem 0 0; color: #6b7280; font-size: .85rem; line-height: 1.5; }
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
        <?php if ($viewModel->state !== 'available'): ?>
            <p class="status"><?= $escape($viewModel->statusMessage) ?></p>
        <?php endif; ?>
        <?php if ($viewModel->osRequirementMessage !== null): ?>
            <p class="os-version"><?= $escape($viewModel->osRequirementMessage) ?></p>
        <?php endif; ?>
        <?php if ($viewModel->showUpdate && $viewModel->destinationUrl !== null && $viewModel->targetVersion !== null): ?>
            <div class="update-action">
                <a class="update-link" href="<?= $escape($viewModel->destinationUrl) ?>" aria-label="<?= $escape($viewModel->updateButtonAriaLabel) ?>">
                    <span>V<?= $escape($viewModel->targetVersion) ?></span>
                    <small><?= $escape($viewModel->updateButtonLabel) ?></small>
                </a>
            </div>
        <?php endif; ?>
        <?php if ($viewModel->showStoreNotice): ?>
            <p class="notice"><?= $escape($viewModel->storeNotice) ?></p>
        <?php endif; ?>
    </div>
</main>
</body>
</html>

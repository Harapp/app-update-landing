<?php

declare(strict_types=1);

use AppUpdateLanding\Development\PreviewScenarioFactory;

header('Content-Type: text/html; charset=UTF-8');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; frame-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow, noarchive');
header('Cache-Control: no-store');

$factory = new PreviewScenarioFactory(dirname(__DIR__));
$locales = $factory->locales();
$locale = isset($_GET['locale']) && is_string($_GET['locale']) && in_array($_GET['locale'], $locales, true)
    ? $_GET['locale']
    : (in_array('ja', $locales, true) ? 'ja' : 'en');
$platforms = ['ios' => 'iOS', 'android' => 'Android', 'pc' => 'PC'];
$platform = isset($_GET['platform']) && is_string($_GET['platform']) && isset($platforms[$_GET['platform']])
    ? $_GET['platform']
    : 'ios';
$modes = ['grid' => '全状態', 'single' => '単体'];
$mode = isset($_GET['mode']) && is_string($_GET['mode']) && isset($modes[$_GET['mode']])
    ? $_GET['mode']
    : 'grid';
$scenario = isset($_GET['scenario'])
    && is_string($_GET['scenario'])
    && isset(PreviewScenarioFactory::SCENARIOS[$_GET['scenario']])
        ? $_GET['scenario']
        : 'available';
$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$previewUrl = static fn (string $state): string => '/render?' . http_build_query([
    'scenario' => $state,
    'locale' => $locale,
    'platform' => $platform,
], '', '&', PHP_QUERY_RFC3986);
?><!doctype html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Event Update Preview</title>
    <style>
        :root { color-scheme: light; font-family: system-ui, sans-serif; color: #182230; background: #eef2f6; }
        * { box-sizing: border-box; }
        body { margin: 0; }
        header { position: sticky; z-index: 10; top: 0; padding: 1rem 1.25rem; border-bottom: 1px solid #d0d5dd; background: #ffffffee; backdrop-filter: blur(12px); }
        .header-row { display: flex; flex-wrap: wrap; gap: 1rem; align-items: end; justify-content: space-between; max-width: 1440px; margin: auto; }
        h1 { margin: 0; font-size: 1.2rem; }
        .subtitle { margin: .25rem 0 0; color: #667085; font-size: .85rem; }
        form { display: flex; flex-wrap: wrap; gap: .65rem; align-items: end; }
        label { display: grid; gap: .25rem; color: #475467; font-size: .75rem; font-weight: 700; }
        select, button { min-height: 2.5rem; border: 1px solid #98a2b3; border-radius: .5rem; background: #fff; color: #182230; font: inherit; }
        select { min-width: 8rem; padding: .45rem 2rem .45rem .65rem; }
        button { padding: .45rem 1rem; border-color: #155eef; background: #155eef; color: #fff; font-weight: 700; cursor: pointer; }
        main { max-width: 1440px; margin: auto; padding: 1.25rem; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 390px), 1fr)); gap: 1.25rem; align-items: start; }
        .card { overflow: hidden; border: 1px solid #d0d5dd; border-radius: .75rem; background: #fff; box-shadow: 0 2px 8px #1018280d; }
        .card-heading { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: .7rem .85rem; border-bottom: 1px solid #e4e7ec; }
        h2 { margin: 0; font-size: .95rem; }
        code { color: #475467; font-size: .75rem; }
        .open-link { color: #155eef; font-size: .75rem; font-weight: 700; text-decoration: none; }
        iframe { display: block; width: 100%; height: 700px; border: 0; background: #fff; }
        .single { max-width: 920px; margin: auto; }
        .single iframe { height: calc(100vh - 11rem); min-height: 700px; }
        @media (max-width: 640px) {
            header { position: static; }
            .header-row, form { align-items: stretch; }
            form, label, select, button { width: 100%; }
            main { padding: .75rem; }
        }
    </style>
</head>
<body>
<header>
    <div class="header-row">
        <div>
            <h1>Event Update Preview</h1>
            <p class="subtitle">ローカル専用・実際のEvaluatorと翻訳設定を使用</p>
        </div>
        <form method="get" action="/">
            <label>言語
                <select name="locale">
                    <?php foreach ($locales as $option): ?>
                        <option value="<?= $escape($option) ?>"<?= $option === $locale ? ' selected' : '' ?>><?= $escape($option) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Platform
                <select name="platform">
                    <?php foreach ($platforms as $value => $label): ?>
                        <option value="<?= $value ?>"<?= $value === $platform ? ' selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>表示
                <select name="mode">
                    <?php foreach ($modes as $value => $label): ?>
                        <option value="<?= $value ?>"<?= $value === $mode ? ' selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <?php if ($mode === 'single'): ?>
                <label>状態
                    <select name="scenario">
                        <?php foreach (PreviewScenarioFactory::SCENARIOS as $value => $label): ?>
                            <option value="<?= $value ?>"<?= $value === $scenario ? ' selected' : '' ?>><?= $escape($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            <?php endif; ?>
            <button type="submit">表示を更新</button>
        </form>
    </div>
</header>
<main>
    <?php $visibleScenarios = $mode === 'single' ? [$scenario => PreviewScenarioFactory::SCENARIOS[$scenario]] : PreviewScenarioFactory::SCENARIOS; ?>
    <div class="<?= $mode === 'single' ? 'single' : 'grid' ?>">
        <?php foreach ($visibleScenarios as $state => $label): ?>
            <section class="card">
                <div class="card-heading">
                    <div>
                        <h2><?= $escape($label) ?></h2>
                        <code><?= $state ?></code>
                    </div>
                    <a class="open-link" href="<?= $escape($previewUrl($state)) ?>" target="_blank" rel="noopener">別タブで開く</a>
                </div>
                <iframe src="<?= $escape($previewUrl($state)) ?>" title="<?= $escape($label) ?>" sandbox loading="lazy"></iframe>
            </section>
        <?php endforeach; ?>
    </div>
</main>
</body>
</html>

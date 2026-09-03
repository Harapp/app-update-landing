<?php

declare(strict_types=1);

namespace Deployer;

require 'recipe/composer.php';

function removeLocalReleaseDirectory(string $path): void
{
    $expectedPrefix = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR
        . 'purrfect-spirits-release-';
    if (!str_starts_with($path, $expectedPrefix) || !is_dir($path)) {
        return;
    }

    $iterator = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
        \RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($iterator as $item) {
        if ($item->isDir() && !$item->isLink()) {
            rmdir($item->getPathname());
            continue;
        }

        unlink($item->getPathname());
    }
    rmdir($path);
}

// The SSH config entry is intentionally the source of connection credentials.
// Keep the alias as `coreserver` so Deployer uses `ssh coreserver`.
host('coreserver')
    ->set('ssh_multiplexing', false)
    // Override the local SSH config's ControlPath; this environment cannot create its socket.
    ->set('ssh_arguments', ['-o ControlMaster=no', '-o ControlPath=none'])
    ->set('deploy_path', '/home/harapeco/domains/neko.harapeco.okinawa/.deploy/event-update')
    ->set('public_path', '/home/harapeco/domains/neko.harapeco.okinawa/public_html/event-update')
    ->set('bin/composer', '/home/harapeco/bin/composer')
    ->setLabels(['stage' => 'production', 'game' => 'purrfect-spirits']);

set('application', 'purrfect-spirits-event-update');
set('keep_releases', 5);
set('default_timeout', 300);
$gameConfig = require __DIR__ . '/config/game.php';
set('public_base_url', $gameConfig['publicBaseUrl']);
set('release_target_version', $gameConfig['releaseTargetVersion']);
set('deploy_source_paths', [
    'bin',
    'composer.json',
    'composer.lock',
    'config',
    'games/purrfect-spirits',
    'public',
    'src',
    'templates',
]);

desc('Run the application test suite and PurrfectSpirits configuration validation locally.');
task('deploy:validate', function (): void {
    runLocally('composer test', timeout: 300);
    runLocally('composer validate:config', timeout: 60);
    runLocally('composer smoke', timeout: 60);
})->once();

desc('Upload only the files required by the PurrfectSpirits release.');
task('deploy:update_code', function (): void {
    $gitRoot = runLocally('git rev-parse --show-toplevel');
    $target = get('target');
    $sourcePaths = get('deploy_source_paths');
    if (!is_array($sourcePaths) || $sourcePaths === []) {
        throw new \RuntimeException('Deployment source paths are unavailable.');
    }

    $archivePath = tempnam(sys_get_temp_dir(), 'purrfect-spirits-release-');
    if ($archivePath === false) {
        throw new \RuntimeException('Unable to prepare the deployment archive.');
    }

    $stagingPath = sys_get_temp_dir() . '/purrfect-spirits-release-' . bin2hex(random_bytes(12));
    if (!mkdir($stagingPath, 0700)) {
        unlink($archivePath);
        throw new \RuntimeException('Unable to prepare the local release staging directory.');
    }

    try {
        $quotedPaths = array_map(static fn (string $path): string => quote($path), $sourcePaths);
        runLocally(
            'git -C ' . quote($gitRoot)
            . ' archive --format=tar --output=' . quote($archivePath)
            . ' ' . quote($target) . ' -- ' . implode(' ', $quotedPaths)
        );

        runLocally('tar -xf ' . quote($archivePath) . ' -C ' . quote($stagingPath));
        runLocally(
            quote(PHP_BINARY)
            . ' ' . quote($stagingPath . '/bin/build-game-assets')
            . ' purrfect-spirits ' . quote($stagingPath . '/public/assets')
        );
        if (!is_file($stagingPath . '/public/assets/banner.webp')) {
            throw new \RuntimeException(
                'The release banner is missing. Add games/purrfect-spirits/assets/banner.png, .jpg, or .jpeg.'
            );
        }
        runLocally(
            'tar -rf ' . quote($archivePath)
            . ' -C ' . quote($stagingPath)
            . ' public/assets'
        );

        $archiveEntries = preg_split('/\R/', trim(runLocally('tar -tf ' . quote($archivePath))));
        $allowedFiles = ['composer.json', 'composer.lock', 'games/'];
        $allowedPrefixes = ['bin/', 'config/', 'games/purrfect-spirits/', 'public/', 'src/', 'templates/'];
        foreach ($archiveEntries ?: [] as $entry) {
            $allowed = in_array($entry, $allowedFiles, true);
            foreach ($allowedPrefixes as $prefix) {
                if ($entry === $prefix || str_starts_with($entry, $prefix)) {
                    $allowed = true;
                    break;
                }
            }
            if (!$allowed) {
                throw new \RuntimeException('The release archive contains an unapproved path.');
            }
        }

        upload($archivePath, '{{release_path}}/.release.tar');
        run('tar -xf {{release_path}}/.release.tar -C {{release_path}}');
        run('rm {{release_path}}/.release.tar');
    } finally {
        if (is_file($archivePath)) {
            unlink($archivePath);
        }
        removeLocalReleaseDirectory($stagingPath);
    }

    $revision = quote(runLocally('git -C ' . quote($gitRoot) . ' rev-list -1 ' . quote($target)));
    run("echo $revision > {{release_path}}/REVISION");
});

desc('Verify that the uploaded release contains only approved top-level paths.');
task('deploy:verify_payload', function (): void {
    $allowedEntries = [
        'REVISION', 'bin', 'composer.json', 'composer.lock', 'config',
        'games', 'public', 'src', 'templates',
    ];
    $excludedNames = implode(' ', array_map(
        static fn (string $name): string => '! -name ' . quote($name),
        $allowedEntries,
    ));
    $unexpected = trim(run("find {{release_path}} -mindepth 1 -maxdepth 1 $excludedNames -print"));
    $unexpectedGames = trim(run(
        'find {{release_path}}/games -mindepth 1 -maxdepth 1 ! -name purrfect-spirits -print'
    ));

    if ($unexpected !== '' || $unexpectedGames !== '') {
        throw new \RuntimeException('The uploaded release contains an unapproved path.');
    }
});

desc('Validate the release candidate on the server before switching current.');
task('deploy:candidate_validate', function (): void {
    run('test -s {{release_path}}/public/assets/banner.webp');
    run("test \"\$(stat -c '%a' {{release_path}}/public/assets/banner.webp)\" = 644");
    run('cd {{release_path}} && {{bin/composer}} check-platform-reqs --no-dev');
    run("find {{release_path}}/config {{release_path}}/public {{release_path}}/src {{release_path}}/templates "
        . "-type f -name '*.php' -print0 | xargs -0 -n 1 {{bin/php}} -l >/dev/null");
    run('{{bin/php}} -l {{release_path}}/bin/validate-purrfect-spirits >/dev/null');
    run('{{bin/php}} -l {{release_path}}/bin/smoke-test-purrfect-spirits >/dev/null');
    run('cd {{release_path}} && {{bin/php}} bin/validate-purrfect-spirits');
    run('cd {{release_path}} && {{bin/php}} bin/smoke-test-purrfect-spirits');
});

desc('Link the public URL to the current release public directory.');
task('deploy:public_link', function (): void {
    $publicPath = get('public_path');
    $temporaryLink = $publicPath . '.next';

    // Never remove an existing non-empty directory. This protects an already
    // published page if the server has not been prepared for the symlink.
    run("if [ -e $publicPath ] && [ ! -L $publicPath ]; then "
        . "if [ -n \"\$(find $publicPath -mindepth 1 -maxdepth 1 -print -quit)\" ]; then "
        . "echo 'public path is not an empty directory or symlink' >&2; exit 1; fi; "
        . "rmdir $publicPath; fi; "
        . "rm -f $temporaryLink; "
        . "ln -s {{current_path}}/public $temporaryLink");

    if (get('use_atomic_symlink')) {
        run("mv -T $temporaryLink $publicPath");
        return;
    }

    run("{{bin/symlink}} {{current_path}}/public $publicPath; rm -f $temporaryLink");
});

/** @return array{page: string, banner: string} */
function publishedUrls(): array
{
    $baseUrl = rtrim((string) get('public_base_url'), '/');
    $query = http_build_query([
        'appVersion' => '0.0.0',
        'targetVersion' => (string) get('release_target_version'),
        'locale' => 'en',
        'platform' => 'pc',
        'osVersion' => '1',
    ], '', '&', PHP_QUERY_RFC3986);

    return [
        'page' => $baseUrl . '/?' . $query,
        'banner' => $baseUrl . '/assets/banner.webp',
    ];
}

desc('Verify the published PurrfectSpirits page over HTTPS.');
task('deploy:health', function (): void {
    $urls = publishedUrls();
    try {
        run("curl --fail --silent --show-error --location --max-time 15 "
            . '--output /dev/null ' . quote($urls['banner']));
        run("curl --fail --silent --show-error --location --max-time 15 "
            . quote($urls['page']) . ' '
            . "| grep -F 'PurrfectSpirits'");
    } catch (\Throwable $exception) {
        warning('Health check failed; restoring the previous release.');
        invoke('rollback');
        throw $exception;
    }
});

desc('Show the published PurrfectSpirits URLs.');
task('deploy:show_urls', function (): void {
    $urls = publishedUrls();
    writeln('');
    writeln('<info>Published URLs</info>');
    writeln('Page:   ' . $urls['page']);
    writeln('Banner: ' . $urls['banner']);
});

// The public mount is updated only after Deployer has switched current. Health
// runs before cleanup so a failed release remains available for rollback.
task('deploy:publish', [
    'deploy:symlink',
    'deploy:public_link',
    'deploy:health',
    'deploy:unlock',
    'deploy:cleanup',
    'deploy:success',
    'deploy:show_urls',
]);

after('deploy:update_code', 'deploy:verify_payload');
after('deploy:vendors', 'deploy:candidate_validate');
before('deploy:prepare', 'deploy:validate');
after('rollback', 'deploy:public_link');
after('deploy:failed', 'deploy:unlock');

<?php

declare(strict_types=1);

namespace Deployer;

require 'recipe/composer.php';

// The SSH config entry is intentionally the source of connection credentials.
// Keep the alias as `coreserver` so Deployer uses `ssh coreserver`.
host('coreserver')
    ->set('ssh_multiplexing', false)
    ->set('deploy_path', '/home/harapeco/domains/neko.harapeco.okinawa/.deploy/event-update')
    ->set('public_path', '/home/harapeco/domains/neko.harapeco.okinawa/public_html/event-update')
    ->set('bin/composer', '/home/harapeco/bin/composer')
    ->setLabels(['stage' => 'production', 'game' => 'purrfect-spirits']);

set('application', 'purrfect-spirits-event-update');
set('keep_releases', 5);
set('update_code_strategy', 'local_archive');
set('default_timeout', 300);

desc('Run the application test suite and PurrfectSpirits configuration validation locally.');
task('deploy:validate', function (): void {
    runLocally('composer test', timeout: 300);
    runLocally('composer validate:config', timeout: 60);
})->once();

desc('Remove files that are not part of the fixed PurrfectSpirits deployment.');
task('deploy:filter', function (): void {
    run("find {{release_path}}/games -mindepth 1 -maxdepth 1 ! -name purrfect-spirits -exec rm -rf -- {} +; "
        . "rm -rf {{release_path}}/docs {{release_path}}/tests");
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

desc('Verify the published PurrfectSpirits page over HTTPS.');
task('deploy:health', function (): void {
    try {
        run("curl --fail --silent --show-error --location --max-time 15 "
            . "'https://neko.harapeco.okinawa/event-update/?appVersion=0.0.0&targetVersion=0.1.0&locale=en&platform=pc&osVersion=1' "
            . "| grep -F 'PurrfectSpirits'");
    } catch (\Throwable $exception) {
        warning('Health check failed; restoring the previous release.');
        invoke('rollback');
        throw $exception;
    }
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
]);

after('deploy:update_code', 'deploy:filter');
before('deploy:prepare', 'deploy:validate');
after('rollback', 'deploy:public_link');
after('deploy:failed', 'deploy:unlock');

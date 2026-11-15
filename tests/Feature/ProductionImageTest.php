<?php

declare(strict_types=1);

/*
 * Static checks on the production image (Dockerfile on serversideup/php, fpm-nginx): the tools and
 * extensions the app needs at runtime, the limits of the uploads and of the mail, and the FormKit Pro
 * key kept out of the image. The image itself is tried by hand with `docker build` (see AGENTS.md).
 */

/**
 * The text of a file of the repository, by its path from the root.
 */
function repositoryFile(string $path): string
{
    $file = base_path($path);

    expect($file)->toBeFile();

    return (string) file_get_contents($file);
}

it('builds on serversideup/php with nginx and PHP-FPM', function (): void {
    $dockerfile = repositoryFile('Dockerfile');

    expect($dockerfile)
        ->toContain('serversideup/php')
        ->toContain('fpm-nginx');
});

it('installs the tools of the previews', function (string $package): void {
    expect(repositoryFile('Dockerfile'))->toMatch('/^\s*(&& )?apt-get install .*\b'.preg_quote($package, '/').'\b/m');
})->with(['poppler-utils', 'libheif-examples', 'libheif-plugin-libde265']);

it('installs the PHP extensions of the app', function (string $extension): void {
    expect(repositoryFile('Dockerfile'))->toMatch('/^RUN install-php-extensions [^\n]*(?<=\s)'.$extension.'(?=\s|$)/m');
})->with(['gd', 'exif', 'redis', 'intl', 'bcmath', 'pdo_mysql']);

it('fits a small server and logs to the output of the container', function (string $setting): void {
    expect(repositoryFile('Dockerfile'))->toMatch('/^\s*(ENV )?'.preg_quote($setting, '/').'( \\\\)?$/m');
})->with([
    'PHP_FPM_PM_MAX_CHILDREN=6',
    'PHP_MEMORY_LIMIT=256M',
    'S6_SERVICES_GRACETIME=30000',
    'LOG_CHANNEL=stderr',
]);

it('runs the queue worker and the scheduler in the same container', function (): void {
    expect(repositoryFile('docker/production/s6/laravel-queue/run'))->toContain('queue:work --tries=3')
        ->and(repositoryFile('docker/production/s6/laravel-queue/run'))->toContain('--memory=256')
        ->and(repositoryFile('docker/production/s6/laravel-scheduler/run'))->toContain('schedule:work');
});

it('stops with SIGTERM, so the worker ends its job and the container exits before being killed', function (): void {
    // With the image's SIGQUIT, S6 never brought the container down and Docker killed it after the timeout
    expect(repositoryFile('Dockerfile'))->toMatch('/^STOPSIGNAL SIGTERM$/m');
});

it('keeps the FormKit Pro key out of the image as a build secret', function (): void {
    $dockerfile = repositoryFile('Dockerfile');

    expect($dockerfile)
        ->toContain('--mount=type=secret,id=VITE_FORMKIT_PRO_KEY')
        // Neither an ARG or ENV line nor a continuation line of a multi-line ENV names the key
        ->not->toMatch('/^\s*(ARG|ENV)\b.*VITE_FORMKIT_PRO_KEY/m')
        ->not->toMatch('/^\s+VITE_FORMKIT_PRO_KEY=/m');
});

it('passes the source commit of Coolify to the build of the pages, never baked into the final image', function (): void {
    $dockerfile = repositoryFile('Dockerfile');
    [$beforeProduction, $production] = explode('AS production', $dockerfile, 2);
    $build = explode('AS build', $beforeProduction, 2)[1];

    // vite.config.ts writes SOURCE_COMMIT as the version of the client: the ARG must come before npm run build
    expect($build)->toMatch('/^ARG SOURCE_COMMIT$/m')
        ->and(strpos($build, 'ARG SOURCE_COMMIT'))->toBeLessThan(strpos($build, 'npm run build'))
        // At runtime Coolify provides it, a value of the build left in the image would be stale
        ->and($production)->not->toContain('SOURCE_COMMIT');
});

it('leaves out of the build context the secrets, the dependencies and the files of the families', function (string $path): void {
    $ignored = array_map(trim(...), explode("\n", repositoryFile('.dockerignore')));

    expect($ignored)->toContain($path);
})->with(['.env', 'node_modules', 'vendor', 'storage/app/*', 'tests', '.git']);

it('no longer builds with Nixpacks', function (): void {
    expect(base_path('nixpacks.toml'))->not->toBeFile();
});

it('gives production the limits of the uploads and of the mail, without the lines of Sail', function (): void {
    $ini = repositoryFile('docker/production/php.ini');

    expect($ini)
        ->toMatch('/^post_max_size = 100M$/m')
        ->toMatch('/^upload_max_filesize = 26M$/m')
        ->toMatch('/^max_file_uploads = 100$/m')
        ->not->toContain('pcov')
        ->not->toContain('variables_order');
});

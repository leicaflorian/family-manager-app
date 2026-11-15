<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Vite;

/*
 * The Link header lists the files of the page for the browser to preload. With every file of a big page (a card:
 * 74 files, about 7 KB) the headers went over the 8 KB that nginx keeps for them, and the page answered 502.
 */
test('the link header preloads at most 20 files, however many the page has', function (): void {
    $directory = 'build-preload-test-'.getmypid();
    $chunks = collect(range(1, 80))->mapWithKeys(fn (int $i): array => ["_chunk{$i}.js" => ['file' => "assets/chunk{$i}.js"]]);
    $entry = fn (string $source, string $file): array => [$source => [
        'file' => $file,
        'src' => $source,
        'isEntry' => true,
        'imports' => $chunks->keys()->all(),
    ]];
    $manifest = $chunks
        ->merge($entry('resources/js/app.ts', 'assets/app.js'))
        ->merge($entry('resources/js/pages/auth/Login.vue', 'assets/Login.js'))
        ->merge(['resources/css/app.css' => ['file' => 'assets/app.css', 'src' => 'resources/css/app.css', 'isEntry' => true]]);
    File::ensureDirectoryExists(public_path($directory));
    File::put(public_path($directory.'/manifest.json'), $manifest->toJson());

    try {
        $this->withVite();
        Vite::useBuildDirectory($directory)->useHotFile(public_path($directory.'/hot'));

        $link = (string) $this->get(route('login'))->assertOk()->headers->get('Link');
    } finally {
        File::deleteDirectory(public_path($directory));
    }

    expect(substr_count($link, 'rel="'))->toBe(20);
});

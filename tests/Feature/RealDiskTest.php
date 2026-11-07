<?php

declare(strict_types=1);

use Database\Seeders\BianchiFamilySeeder;
use Illuminate\Support\Facades\File;

/*
 * The tests never write to the real disk of the app: every test of Feature and
 * Unit has the `local` disk faked. Without it each test that seeds the Bianchi
 * left about thirty files in storage/app/private/families, and they grew to
 * half a million.
 */

/**
 * The files in the real storage of the families, by path.
 *
 * @return list<string>
 */
function realFamilyFiles(): array
{
    $root = storage_path('app/private/families');

    return File::isDirectory($root)
        ? array_values(array_map(fn (SplFileInfo $file): string => $file->getPathname(), File::allFiles($root)))
        : [];
}

it('leaves no file of the seeder in the real storage of the families', function (): void {
    $before = realFamilyFiles();

    $this->seed(BianchiFamilySeeder::class);
    $added = array_values(array_diff(realFamilyFiles(), $before));

    // A failing run must not leave its files behind either; the files already there stay
    File::delete($added);
    expect($added)->toBe([]);
});

<?php

declare(strict_types=1);

use App\Support\DefaultCategories;
use Illuminate\Support\Facades\File;

/**
 * Every text the code passes to the translator: `__()`, `trans_choice()` in PHP
 * and Blade, `$t()`, `trans()`, `transChoice()` in Vue and TypeScript.
 *
 * @return list<string>
 */
function translatedTexts(): array
{
    $files = collect([
        ...File::allFiles(app_path()),
        ...File::allFiles(resource_path('views')),
        ...File::allFiles(resource_path('js')),
    ])->reject(fn (SplFileInfo $file): bool => str_contains($file->getPathname(), '/generated/')
        || str_contains($file->getPathname(), '/wayfinder/') || str_contains($file->getPathname(), '/actions/')
        || str_contains($file->getPathname(), '/routes/'));

    $pattern = '/(?<![\w$])(?:__|trans_choice|\$t|\$tChoice|trans|wTrans|transChoice|wTransChoice)\(\s*(?:\'((?:[^\'\\\\]|\\\\.)*)\'|"((?:[^"\\\\]|\\\\.)*)")/';
    $texts = [];

    foreach ($files as $file) {
        preg_match_all($pattern, $file->getContents(), $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $texts[] = stripslashes($match[1] !== '' ? $match[1] : ($match[2] ?? ''));
        }
    }

    // The default categories are translated when a family is created.
    foreach ([DefaultCategories::documents(), DefaultCategories::expenses()] as $tree) {
        foreach ($tree as $main => $subcategories) {
            $texts = [...$texts, $main, ...$subcategories];
        }
    }

    return array_values(array_unique(array_filter($texts, fn (string $text): bool => $text !== '' && ! str_contains($text, '.') || str_contains($text, ' '))));
}

test('every English text has its Italian translation', function (): void {
    /** @var array<string, string> $italian */
    $italian = json_decode(File::get(lang_path('it.json')), true);

    $missing = array_values(array_filter(translatedTexts(), fn (string $text): bool => ! array_key_exists($text, $italian)));

    expect($missing)->toBe([]);
});

test('the labels of the enums are there in English and in Italian, for every value', function (): void {
    expect(array_keys(require lang_path('it/enums.php')))->toBe(array_keys(require lang_path('en/enums.php')));

    foreach (require lang_path('en/enums.php') as $group => $labels) {
        expect(array_keys((require lang_path('it/enums.php'))[$group]))->toBe(array_keys($labels));
    }

    foreach (File::files(app_path('Enums')) as $file) {
        $enum = 'App\\Enums\\'.$file->getFilenameWithoutExtension();

        foreach ($enum::cases() as $case) {
            expect(__('enums.'.Str::snake(class_basename($enum)).'.'.$case->value))->not->toStartWith('enums.');
        }
    }
});

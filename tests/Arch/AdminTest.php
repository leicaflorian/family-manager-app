<?php

declare(strict_types=1);

/*
 * The owner panel never reads the content of a family: no scope removal in `app/Filament`, and no model of
 * the family data except the ones allowed (counts and sums go through `app/Admin`).
 */

/**
 * The models the panel may use: it shows accounts, linked phones, costs and its own log, never family content.
 * `Owner` and `OwnerAction` are the panel's own tables (`OwnerAction` arrives with the owner log).
 *
 * @return list<class-string>
 */
function modelsOfFamilyContent(): array
{
    $allowed = ['Family', 'User', 'LinkedDevice', 'AiReading', 'MailInboxCall', 'OwnerAction', 'Owner', 'AppSetting', 'FamilyModel'];
    $models = [];

    foreach (glob(dirname(__DIR__, 2).'/app/Models/*.php') ?: [] as $file) {
        $name = basename($file, '.php');
        if (! in_array($name, $allowed, true)) {
            $models[] = 'App\\Models\\'.$name;
        }
    }

    return $models;
}

arch('the panel does not use the models of the family content')
    ->expect('App\Filament')
    ->not->toUse(modelsOfFamilyContent());

it('does not lift the family scope in the panel', function (): void {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/app/Filament'));

    foreach ($iterator as $file) {
        if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
            continue;
        }

        expect(file_get_contents($file->getPathname()))
            ->not->toContain('withoutGlobalScope')
            ->not->toContain('FamilyScope');
    }
});

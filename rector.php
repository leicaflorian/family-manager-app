<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\TypeDeclaration\Rector\StmtsAwareInterface\DeclareStrictTypesRector;
use RectorLaravel\Set\LaravelLevelSetList;
use RectorLaravel\Set\LaravelSetList;

/*
 * Rector tiene il codice al passo con PHP 8.4 e Laravel 13.
 * `sail php vendor/bin/rector --dry-run` non deve proporre modifiche.
 */
return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/app',
        __DIR__.'/bootstrap/app.php',
        __DIR__.'/config',
        __DIR__.'/database',
        __DIR__.'/routes',
        __DIR__.'/tests',
    ])
    ->withPhpSets(php84: true)
    ->withSets([
        LaravelLevelSetList::UP_TO_LARAVEL_130,
        LaravelSetList::LARAVEL_CODE_QUALITY,
    ])
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
        earlyReturn: true,
    )
    ->withRules([DeclareStrictTypesRector::class])
    ->withImportNames(importShortClasses: false, removeUnusedImports: true);

<?php

declare(strict_types=1);

namespace App\Providers;

use Spatie\LaravelTypeScriptTransformer\LaravelData\LaravelDataTypeScriptTransformerExtension;
use Spatie\LaravelTypeScriptTransformer\TypeScriptTransformerApplicationServiceProvider as BaseTypeScriptTransformerServiceProvider;
use Spatie\TypeScriptTransformer\Transformers\AttributedClassTransformer;
use Spatie\TypeScriptTransformer\Transformers\EnumTransformer;
use Spatie\TypeScriptTransformer\TypeScriptTransformerConfigFactory;
use Spatie\TypeScriptTransformer\Writers\GlobalNamespaceWriter;

/**
 * Genera i tipi TypeScript delle classi dati (`App\Data\…`) e degli enum.
 *
 * Il tipo di un documento si scrive una volta sola, in PHP: le pagine Vue lo
 * leggono da `resources/js/generated/generated.d.ts` (`App.Data.DocumentoData`),
 * che si rifà con `sail artisan typescript:transform`.
 */
final class TypeScriptTransformerServiceProvider extends BaseTypeScriptTransformerServiceProvider
{
    /**
     * Sceglie cosa trasformare e dove scriverlo.
     */
    protected function configure(TypeScriptTransformerConfigFactory $config): void
    {
        $config
            ->extension(new LaravelDataTypeScriptTransformerExtension)
            ->transformer(AttributedClassTransformer::class)
            ->transformer(EnumTransformer::class)
            ->transformDirectories(app_path())
            ->writer(new GlobalNamespaceWriter('generated.d.ts'));
    }
}

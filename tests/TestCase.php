<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

/**
 * La base di tutti i test.
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * Prepara l'app per un test: le pagine non cercano i file della build di Vite,
     * così i test non dipendono da `npm run build`.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /**
     * Salta il test se Fortify non ha la funzione indicata.
     *
     * @param  string  $feature  la funzione di Fortify
     * @param  string|null  $message  il motivo da mostrare
     */
    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}

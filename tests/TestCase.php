<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

/**
 * The base of every test.
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * Prepares the app for a test: the pages do not look for the Vite build,
     * so the tests do not depend on `npm run build`.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /**
     * Skips the test if Fortify does not have the given feature.
     *
     * @param  string  $feature  the Fortify feature
     * @param  string|null  $message  the reason to show
     */
    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The last version of the agent of the home scanner the page can offer, with the systems it is built for.
 */
#[TypeScript]
final class AgentReleaseData extends Data
{
    /**
     * @param  string  $version  The version, like `1.2.0`.
     * @param  bool  $mac  There is a zip for the Mac.
     * @param  bool  $windows  There is a zip for Windows.
     */
    public function __construct(
        public readonly string $version,
        public readonly bool $mac,
        public readonly bool $windows,
    ) {}
}

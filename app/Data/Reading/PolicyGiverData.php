<?php

declare(strict_types=1);

namespace App\Data\Reading;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A policy an insurance paper without a number can take: the one a file of the same import gives (it creates it, or
 * found it by its number), or one already registered.
 */
#[TypeScript]
final class PolicyGiverData extends Data
{
    /**
     * @param  string  $key  `file:<id>` for a file of the import, `policy:<id>` for a registered policy
     * @param  string|null  $fileName  the name of the file, as the list shows it; null for a registered policy
     * @param  string  $number  the policy number, with the correction of the person
     * @param  bool  $creates  true when the file creates the policy; false when it is already registered
     * @param  int|null  $organizationId  the company; null for one created in the same import
     * @param  string|null  $organizationName  the name of the company, also for one created in the same import
     * @param  int|null  $personId  the person the policy refers to
     * @param  string|null  $type  the insurance type (`car`, `life`, ...)
     */
    public function __construct(
        public readonly string $key,
        public readonly ?string $fileName,
        public readonly string $number,
        public readonly bool $creates,
        public readonly ?int $organizationId,
        public readonly ?string $organizationName,
        public readonly ?int $personId,
        public readonly ?string $type,
    ) {}
}

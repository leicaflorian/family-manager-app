<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Someone who already wrote to the address for the bills, in *Settings → Address for the bills*.
 */
#[TypeScript]
final class MailSenderData extends Data
{
    /**
     * @param  string  $name  who wrote, as the family reads it: the organization of a known sender, the name in the header or the address
     * @param  string  $address  the email address of the sender
     */
    public function __construct(
        public readonly string $name,
        public readonly string $address,
    ) {}
}

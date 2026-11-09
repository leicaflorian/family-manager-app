<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What the panel *Link the phone* opens with: the code made for the account, its QR and the phones already linked.
 */
#[TypeScript]
final class PhoneLinkData extends Data
{
    /**
     * @param  string  $code  the code, as `K7QM-XPA9`
     * @param  string  $qr  the QR of the link, an SVG made by the server
     * @param  string  $address  the page to write the code in, without `https://`
     * @param  string  $expires_at  when the code stops working, ISO 8601
     * @param  string  $person  the first name of the account, for the steps
     * @param  string  $family  the name of the family, for the steps
     * @param  list<LinkedDeviceData>  $devices  the phones of the account
     */
    public function __construct(
        public readonly string $code,
        public readonly string $qr,
        public readonly string $address,
        public readonly string $expires_at,
        public readonly string $person,
        public readonly string $family,
        #[DataCollectionOf(LinkedDeviceData::class)]
        public readonly array $devices,
    ) {}
}

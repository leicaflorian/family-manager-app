<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What the panel *Link the phone* opens with: the code made for the account, its QR and the phones already linked.
 * During a visit of the owner there is no code (it would let the owner link a phone that outlives the visit): code,
 * QR and expiry are null and the panel shows only the phones.
 */
#[TypeScript]
final class PhoneLinkData extends Data
{
    /**
     * @param  string|null  $code  the code, as `K7QM-XPA9`; null during a visit
     * @param  string|null  $qr  the QR of the link, an SVG made by the server; null during a visit
     * @param  string  $address  the page to write the code in, without `https://`
     * @param  string|null  $expires_at  when the code stops working, ISO 8601; null during a visit
     * @param  string  $person  the first name of the account, for the steps
     * @param  string  $family  the name of the family, for the steps
     * @param  list<LinkedDeviceData>  $devices  the phones of the account
     */
    public function __construct(
        public readonly ?string $code,
        public readonly ?string $qr,
        public readonly string $address,
        public readonly ?string $expires_at,
        public readonly string $person,
        public readonly string $family,
        #[DataCollectionOf(LinkedDeviceData::class)]
        public readonly array $devices,
    ) {}
}

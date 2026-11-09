<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\LinkedDevice;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A phone linked to an account, as the panel *Link the phone* and *My account* list it.
 */
#[TypeScript]
final class LinkedDeviceData extends Data
{
    /** Seconds in which a phone that entered still counts as «Linked now». */
    public const int NOW_SECONDS = 60;

    /**
     * @param  int  $id  the id of the phone
     * @param  string  $name  the name, as «iPhone of Marco»
     * @param  string  $linked_on  the day it was linked, `yyyy-mm-dd`
     * @param  string|null  $last_upload_on  the day of its last upload, `yyyy-mm-dd`, null if it never uploaded
     * @param  bool  $linked_now  whether it entered less than a minute ago
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $linked_on,
        public readonly ?string $last_upload_on,
        public readonly bool $linked_now,
    ) {}

    /**
     * The data of a phone.
     *
     * @param  LinkedDevice  $device  the phone
     */
    public static function fromDevice(LinkedDevice $device): self
    {
        return new self(
            id: $device->id,
            name: $device->name,
            linked_on: $device->created_at?->toDateString() ?? now()->toDateString(),
            last_upload_on: $device->last_upload_at?->toDateString(),
            linked_now: $device->last_used_at !== null && $device->last_used_at->greaterThan(now()->subSeconds(self::NOW_SECONDS)),
        );
    }
}

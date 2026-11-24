<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Family;
use Illuminate\Support\Facades\Gate;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Everything *Settings → What you use* needs.
 */
#[TypeScript]
final class FeatureSettingsData extends Data
{
    /**
     * @param  bool  $budget  whether the family uses the Budget
     * @param  bool  $mail  whether the family uses the mail for the bills
     * @param  bool  $can_change  whether who looks can change the switches (an administrator)
     */
    public function __construct(
        public readonly bool $budget,
        public readonly bool $mail,
        public readonly bool $can_change,
    ) {}

    /**
     * The switches of a family, with whether whoever is signed in can change them.
     *
     * @param  Family  $family  the family
     */
    public static function fromFamily(Family $family): self
    {
        return new self(
            budget: $family->budget_enabled,
            mail: $family->mail_enabled,
            can_change: Gate::allows('manageFeatures', $family),
        );
    }
}

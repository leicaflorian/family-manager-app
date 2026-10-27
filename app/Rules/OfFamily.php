<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\CurrentFamily;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Like the `exists` rule, but only among the data of the current family: an id
 * of another family (an organization of the Rossis in a document of the
 * Bianchis) does not pass, even if it exists.
 */
final readonly class OfFamily implements ValidationRule
{
    /**
     * @param  string  $table  the table to look for the id in, for example `organizations`
     */
    public function __construct(private string $table) {}

    /**
     * Checks that the id exists in the table and belongs to the current family.
     *
     * @param  Closure(string, ?string=):PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $family = CurrentFamily::id();

        $exists = $family !== null
            && (is_int($value) || (is_string($value) && ctype_digit($value)))
            && DB::table($this->table)->where('id', $value)->where('family_id', $family)->exists();

        if (! $exists) {
            $fail('validation.exists')->translate();
        }
    }
}

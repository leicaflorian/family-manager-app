<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\Banks\BankMatch;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * The rule *How it appears at the bank* must work: it compiles, it does not match everything and it does not hang.
 */
final readonly class BankMatchRule implements ValidationRule
{
    /**
     * Fails when the value is not a rule that `BankMatch::valid()` accepts. An empty value is left to `nullable`.
     *
     * @param  Closure(string, ?string=):PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! BankMatch::valid($value)) {
            $fail('This rule does not work.')->translate();
        }
    }
}

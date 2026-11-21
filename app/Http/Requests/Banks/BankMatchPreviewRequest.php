<?php

declare(strict_types=1);

namespace App\Http\Requests\Banks;

use App\Rules\BankMatchRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The transactions a rule recognises: the rule, the direction and, if the form has one, the account.
 */
final class BankMatchPreviewRequest extends BankMatchRequest
{
    /**
     * The rules: the rule itself must work.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'pattern' => ['required', 'string', 'max:200', new BankMatchRule],
        ];
    }

    /**
     * The rule to look for.
     */
    public function pattern(): string
    {
        return $this->string('pattern')->toString();
    }
}

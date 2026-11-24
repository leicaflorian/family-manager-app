<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * *Not needed* on a step of *To begin*: only `people` and `invite`, which someone who lives alone has no use for.
 * It changes the list of the whole family, so only whoever can invite takes a step away.
 */
final class SkipChecklistStepRequest extends FormRequest
{
    /** The steps that can be taken away. */
    public const array STEPS = ['people', 'invite'];

    /**
     * Only whoever can invite (an `admin`): a `member` sees neither step.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && Gate::forUser($user)->allows('create', Invitation::class);
    }

    /**
     * The key of the step.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'step' => ['required', 'string', 'in:'.implode(',', self::STEPS)],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests\Budget;

use App\Models\PlannedExpense;
use App\Models\PlannedIncome;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The window *Change this due date*: skip it, another amount, another date, and a note.
 */
final class ChangeOccurrenceRequest extends FormRequest
{
    /**
     * Whoever may update the planned item, which is the `admin` only. The planned item comes from the route parameter `planned`.
     */
    public function authorize(): bool
    {
        $planned = $this->route('planned');

        return ($planned instanceof PlannedExpense || $planned instanceof PlannedIncome) && (bool) $this->user()?->can('update', $planned);
    }

    /**
     * The rules of the choices.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'skip' => ['boolean'],
            'change_amount' => ['boolean'],
            'amount' => ['exclude_if:skip,true,1', 'required_if:change_amount,true,1', 'nullable', 'numeric', 'min:0'],
            'change_date' => ['boolean'],
            'date' => ['exclude_if:skip,true,1', 'required_if:change_date,true,1', 'nullable', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * The choices as the arguments of `ChangeOccurrence::handle`: the amount and the date are null when not chosen.
     *
     * @return array{skip: bool, amount: ?float, date: ?CarbonImmutable, notes: ?string}
     */
    public function choices(): array
    {
        $amount = $this->input('amount');
        $date = $this->input('date');
        $notes = $this->input('notes');

        return [
            'skip' => $this->boolean('skip'),
            'amount' => $this->boolean('change_amount') && is_numeric($amount) ? (float) $amount : null,
            'date' => $this->boolean('change_date') && is_string($date) ? CarbonImmutable::createFromFormat('Y-m-d', $date, 'Europe/Rome')?->startOfDay() : null,
            'notes' => is_string($notes) && $notes !== '' ? $notes : null,
        ];
    }
}

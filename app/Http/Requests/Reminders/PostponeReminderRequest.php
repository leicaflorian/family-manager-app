<?php

declare(strict_types=1);

namespace App\Http\Requests\Reminders;

use App\Enums\ReminderStatus;
use App\Models\Reminder;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * *Postpone*: the new day of an open reminder, after today. The reminder of the address is found by the route
 * binding (the family scope), so one of another family answers 404 before the body is read. Whoever may mark it
 * done may postpone it, an automatic one included.
 */
final class PostponeReminderRequest extends FormRequest
{
    /**
     * Whoever may mark the reminder done.
     */
    public function authorize(): bool
    {
        $reminder = $this->route('reminder');

        return $reminder instanceof Reminder && (bool) $this->user()?->can('markDone', $reminder);
    }

    /**
     * The new day: a date after today in Rome, for a reminder still to do.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $reminder = $this->route('reminder');

        return [
            'to' => [
                'required',
                'date_format:Y-m-d',
                'after:'.CarbonImmutable::today('Europe/Rome')->toDateString(),
                static function (string $attribute, mixed $value, Closure $fail) use ($reminder): void {
                    if ($reminder instanceof Reminder && $reminder->status === ReminderStatus::Done) {
                        $fail(__('A reminder that is done cannot be postponed.'));
                    }
                },
            ],
        ];
    }

    /**
     * The new day.
     */
    public function day(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->string('to')->toString(), 'Europe/Rome');
    }
}

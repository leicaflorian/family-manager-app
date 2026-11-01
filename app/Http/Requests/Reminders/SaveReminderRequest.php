<?php

declare(strict_types=1);

namespace App\Http\Requests\Reminders;

use App\Models\Reminder;
use App\Rules\OfFamily;
use App\Support\Reminders\SubjectLink;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The form of a reminder written by hand, new or changed. The reminder of the address is found by the route binding
 * (the family scope), so one of another family answers 404 before the body is read; an automatic one is refused by the
 * policy before it too.
 */
final class SaveReminderRequest extends FormRequest
{
    /**
     * A new reminder whoever may add them, a change whoever may change that one.
     */
    public function authorize(): bool
    {
        $reminder = $this->route('reminder');

        return $reminder instanceof Reminder
            ? (bool) $this->user()?->can('update', $reminder)
            : (bool) $this->user()?->can('create', Reminder::class);
    }

    /**
     * The rules of the fields. People, groups and the thing it refers to are looked for in the family only.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'due_date' => ['required', 'date_format:Y-m-d'],
            'person_id' => ['nullable', new OfFamily('people')],
            'reminder_group_id' => ['nullable', new OfFamily('reminder_groups')],
            'remindable' => ['nullable', 'string', static function (string $attribute, mixed $value, Closure $fail): void {
                if (SubjectLink::find($value) === null) {
                    $fail('validation.exists')->translate();
                }
            }],
        ];
    }

    /**
     * The columns of the reminder.
     *
     * @return array{title: string, due_date: string, person_id: ?int, reminder_group_id: ?int}
     */
    public function fields(): array
    {
        return [
            'title' => $this->string('title')->toString(),
            'due_date' => $this->string('due_date')->toString(),
            'person_id' => $this->filled('person_id') ? $this->integer('person_id') : null,
            'reminder_group_id' => $this->filled('reminder_group_id') ? $this->integer('reminder_group_id') : null,
        ];
    }

    /**
     * The thing the reminder refers to, null when the form sends none.
     *
     * @return array{type: string, id: int}|null
     */
    public function subject(): ?array
    {
        return SubjectLink::find($this->input('remindable'));
    }
}

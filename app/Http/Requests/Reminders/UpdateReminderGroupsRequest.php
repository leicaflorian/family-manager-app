<?php

declare(strict_types=1);

namespace App\Http\Requests\Reminders;

use App\Cards\DeadlineSource;
use App\Cards\DeadlineSources;
use App\Models\Person;
use App\Models\ReminderGroup;
use App\Rules\OfFamily;
use App\Support\Reminders\GroupRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use InvalidArgumentException;

/**
 * *Settings → Notices*: the whole list of groups as it should be left. Only an `admin` saves it. The checks of the groups
 * are `GroupRules`; the people chosen must be of the family and have an account.
 */
final class UpdateReminderGroupsRequest extends FormRequest
{
    /**
     * Only whoever may create groups, an `admin`.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', ReminderGroup::class);
    }

    /**
     * The rules of the shape; the meaning is checked after.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'groups' => ['required', 'array', 'list'],
            'groups.*' => ['array'],
            'groups.*.id' => ['nullable', new OfFamily('reminder_groups')],
            'groups.*.name' => ['required', 'string', 'max:255'],
            'groups.*.is_default' => ['boolean'],
            'groups.*.deadline_types' => ['nullable', 'array'],
            'groups.*.deadline_types.*' => ['string'],
            'groups.*.notifications' => ['nullable', 'array'],
        ];
    }

    /**
     * The checks of the groups, of their notices and of the people chosen, once the shape is right.
     *
     * @return list<callable(Validator):void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $errors = GroupRules::check($this->rawGroups(), $this->kinds());

            if ($errors === [] && ! $this->peopleHaveAccounts()) {
                $errors[] = __('Some of the people chosen do not have an account.');
            }

            foreach ($errors as $error) {
                $validator->errors()->add('groups', $error);
            }
        }];
    }

    /**
     * The groups to save: the kinds that exist, the notices in the saved shape.
     *
     * @return list<array{id: int|null, name: string, is_default: bool, deadline_types: list<string>, notifications: list<array{name: string, days: int, timing: string, channels: string, recipients: list<string|int>}>}>
     *
     * @throws InvalidArgumentException when a notice is not valid (validation already refused it)
     */
    public function groups(): array
    {
        return GroupRules::clean($this->rawGroups(), $this->kinds());
    }

    /**
     * Whether every person named in a notice is a person of the family with an account.
     */
    private function peopleHaveAccounts(): bool
    {
        $ids = [];

        foreach ($this->groups() as $group) {
            foreach ($group['notifications'] as $notification) {
                foreach ($notification['recipients'] as $recipient) {
                    if (is_int($recipient)) {
                        $ids[$recipient] = $recipient;
                    }
                }
            }
        }

        return $ids === [] || Person::query()->whereIn('id', $ids)->whereNotNull('user_id')->count() === count($ids);
    }

    /**
     * The groups as sent.
     *
     * @return list<array<string, mixed>>
     */
    private function rawGroups(): array
    {
        $input = $this->input('groups');
        $groups = [];

        foreach (is_array($input) ? $input : [] as $group) {
            if (is_array($group)) {
                $groups[] = array_combine(array_map(strval(...), array_keys($group)), $group);
            }
        }

        return $groups;
    }

    /**
     * The kinds of deadline that exist.
     *
     * @return list<string>
     */
    private function kinds(): array
    {
        return array_map(static fn (DeadlineSource $source): string => $source->field, DeadlineSources::all());
    }
}

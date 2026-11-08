<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\FamilySetting;
use App\Models\User;
use App\Rules\OfFamily;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * *Settings → Address for the bills*: who gets the notice of a new file and whether mails without files make one. Only
 * an `admin` saves it.
 */
final class UpdateMailSettingsRequest extends FormRequest
{
    /**
     * Only whoever may change the settings of the family, an `admin`.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('update', FamilySetting::query()->firstOrCreate([]));
    }

    /**
     * The accounts chosen must be of the family; none chosen (an empty list) is allowed and means nobody.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'notify' => ['present', 'array', 'list'],
            'notify.*' => ['integer', new OfFamily('users')],
            'attachments_only' => ['required', 'boolean'],
        ];
    }

    /**
     * The value to save in `mail_inbox_notify`: null when every account of the family is chosen (so the ones that join
     * later get the notice too), otherwise the ids chosen. An empty list stays empty: nobody gets the notice.
     *
     * @return list<int>|null
     */
    public function notify(): ?array
    {
        /** @var list<int|string> $given */
        $given = $this->validated('notify');
        $toId = static fn (mixed $id): int => is_numeric($id) ? (int) $id : 0;
        $chosen = array_values(array_unique(array_map($toId, $given)));
        sort($chosen);

        /** @var User $user */
        $user = $this->user();
        $all = array_map($toId, User::query()->where('family_id', $user->family_id)->orderBy('id')->pluck('id')->all());

        return $chosen === $all ? null : $chosen;
    }
}

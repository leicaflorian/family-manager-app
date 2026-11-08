<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Enums\Area;
use App\Models\FamilySetting;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * *Settings → Areas*: the order of every area and the ones the family hides. Only an `admin` saves it. Hiding all of
 * them is valid: the menu then shows only *All areas*.
 */
final class UpdateAreasRequest extends FormRequest
{
    /**
     * Only whoever may change the settings of the family, an `admin`.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', FamilySetting::class);
    }

    /**
     * Every area exactly once in `order`; `hidden` holds real areas.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'order' => ['required', 'list', 'size:'.count(Area::cases())],
            'order.*' => ['distinct', Rule::enum(Area::class)],
            'hidden' => ['present', 'list'],
            'hidden.*' => [Rule::enum(Area::class)],
        ];
    }

    /**
     * The areas in the chosen order.
     *
     * @return list<Area>
     */
    public function order(): array
    {
        return $this->areas('order');
    }

    /**
     * The hidden areas.
     *
     * @return list<Area>
     */
    public function hidden(): array
    {
        return $this->areas('hidden');
    }

    /**
     * Turns a validated list of values into areas.
     *
     * @param  string  $key  `order` or `hidden`
     * @return list<Area>
     */
    private function areas(string $key): array
    {
        /** @var list<string> $values */
        $values = $this->validated($key);

        return array_map(Area::from(...), $values);
    }
}

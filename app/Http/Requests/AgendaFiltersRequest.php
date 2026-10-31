<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Data\AgendaFiltersData;
use App\Enums\Area;
use App\Rules\OfFamily;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The filters of the Agenda sent by the page: the period, the person and the areas turned off.
 */
final class AgendaFiltersRequest extends FormRequest
{
    /** The area of the entries that belong to no card. */
    public const string GENERAL_AREA = 'general';

    /**
     * Everyone who sees the Agenda keeps their own filters.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * The areas the Agenda shows: the ones of the cards, plus `general`.
     *
     * @return list<string>
     */
    public static function areas(): array
    {
        return [...array_map(static fn (Area $area): string => $area->value, Area::cases()), self::GENERAL_AREA];
    }

    /**
     * The rules of the fields. The person must be one of the current family.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'months' => ['required', 'integer', Rule::in(AgendaRequest::MONTHS)],
            'person_id' => ['nullable', 'integer', new OfFamily('people')],
            'hidden_areas' => ['present', 'array'],
            'hidden_areas.*' => ['string', Rule::in(self::areas())],
        ];
    }

    /**
     * The validated filters.
     */
    public function filters(): AgendaFiltersData
    {
        /** @var array{months: int, person_id: int|null, hidden_areas: list<string>} $data */
        $data = $this->validated();

        return new AgendaFiltersData((int) $data['months'], $data['person_id'] === null ? null : (int) $data['person_id'], $data['hidden_areas']);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests\Banks;

use App\Actions\Banks\SuggestBankFilter;
use App\Enums\BankFilterAction;
use App\Models\BankFilter;
use App\Models\BankTransaction;
use App\Support\ModelId;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * What the AI is asked for a bank filter (`POST budget/bank/filters/suggest`): the movements the filter must take
 * (`keep`, at least one), the ones it must leave (`drop`) and the mode, `words` or `fill`. Every id is looked for in
 * the family first, so one of another family answers 404 before anything is read or paid. Only who may create a filter
 * asks it.
 */
final class SuggestBankFilterRequest extends FormRequest
{
    /** The most movements named on each side. */
    private const int MAX_IDS = 50;

    /** @var list<BankTransaction>|null The movements to keep, read once. */
    private ?array $keepRows = null;

    /** @var list<BankTransaction>|null The movements to leave, read once. */
    private ?array $dropRows = null;

    /**
     * Whoever may create a filter that does not pay, with every id found in the family.
     *
     * @throws NotFoundHttpException when a movement is not of the family
     */
    public function authorize(): bool
    {
        if (! $this->user()?->can('create', [BankFilter::class, BankFilterAction::Create])) {
            return false;
        }

        $this->keepRows = $this->found('keep');
        $this->dropRows = $this->found('drop');

        return true;
    }

    /**
     * The rules: at least one movement to keep, a mode that is known.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'keep' => ['required', 'array', 'list', 'min:1', 'max:'.self::MAX_IDS],
            'keep.*' => ['integer', 'min:1'],
            'drop' => ['nullable', 'array', 'list', 'max:'.self::MAX_IDS],
            'drop.*' => ['integer', 'min:1'],
            'mode' => ['required', Rule::in([SuggestBankFilter::WORDS, SuggestBankFilter::FILL])],
        ];
    }

    /**
     * The movements the filter must take.
     *
     * @return list<BankTransaction>
     */
    public function keep(): array
    {
        return $this->keepRows ?? $this->found('keep');
    }

    /**
     * The movements the filter must leave.
     *
     * @return list<BankTransaction>
     */
    public function drop(): array
    {
        return $this->dropRows ?? $this->found('drop');
    }

    /**
     * The mode asked.
     */
    public function mode(): string
    {
        return $this->string('mode')->toString();
    }

    /**
     * The movements of the family named in a field, in the order named. An id that is not a number is left to the
     * rules; one that is not of the family is a 404.
     *
     * @param  string  $field  `keep` or `drop`
     * @return list<BankTransaction>
     */
    private function found(string $field): array
    {
        $sent = $this->input($field);
        $ids = [];

        foreach (is_array($sent) ? $sent : [] as $value) {
            $id = ModelId::sent($value);

            if ($id !== null) {
                $ids[] = $id;
            }
        }

        $ids = array_values(array_unique($ids));

        // Too many ids are left to the rules (422) without a query
        if ($ids === [] || count($ids) > self::MAX_IDS) {
            return [];
        }

        $rows = BankTransaction::query()->whereIn('id', $ids)->get()->keyBy('id');

        // The scope of the family leaves out the ones of other families: any missing id is a 404
        abort_unless($rows->count() === count($ids), 404);

        return array_map(static fn (int $id): BankTransaction => $rows->findOrFail($id), $ids);
    }
}

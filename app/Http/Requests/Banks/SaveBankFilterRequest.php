<?php

declare(strict_types=1);

namespace App\Http\Requests\Banks;

use App\Enums\BankFilterAction;
use App\Enums\BankFilterFee;
use App\Http\Requests\Concerns\ReadsBankFilter;
use App\Http\Requests\Concerns\ReadsCard;
use App\Models\BankFilter;
use App\Services\Banks\BankFilters;
use App\Support\Banks\BankFilterCriteria;
use App\Support\PresetLink;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The form of a bank filter, new (`POST budget/bank/filters`) or changed (`PUT budget/bank/filters/{filter}`). The
 * ids it names are looked for in the family first (404 for one of another family); then the rules, and after them
 * the criteria: words that would take everything, an inverted amount range, or a movement to sort that another
 * filter already takes refuse the filter. Who may save: `BankFilterPolicy`, also for the action the filter takes now
 * (a `member` cannot turn a filter into one that pays).
 */
final class SaveBankFilterRequest extends FormRequest
{
    use ReadsBankFilter;
    use ReadsCard;

    /**
     * Whoever may create a filter with this action, and change the filter of the address.
     *
     * @throws ModelNotFoundException if an id or the card named is not of the family
     */
    public function authorize(): bool
    {
        $this->findSentIds();
        $card = $this->input('card');

        // A card of another family (or one gone) is 404 like the other ids; the one already on the filter stays valid
        if (is_string($card) && preg_match('/^[a-z_]+:\d+$/', $card) === 1 && $card !== $this->savedCard() && PresetLink::from($card) === null) {
            throw new ModelNotFoundException;
        }

        $user = $this->user();
        $filter = $this->filter();
        $action = BankFilterAction::tryFrom(is_string($this->input('action')) ? $this->input('action') : '');

        if ($user === null || ($filter instanceof BankFilter && ! $user->can('update', $filter))) {
            return false;
        }

        // An action that is not one is refused by the rules, with the field that is wrong
        return ! $action instanceof BankFilterAction || $user->can('create', [BankFilter::class, $action]);
    }

    /**
     * The rules of the fields.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'contains' => ['present', 'array', 'max:20'],
            'contains.*' => ['nullable', 'string', 'max:100'],
            'excludes' => ['nullable', 'array', 'max:20'],
            'excludes.*' => ['nullable', 'string', 'max:100'],
            'direction' => ['required', Rule::in(['expense', 'income'])],
            'financial_account_id' => ['nullable', 'integer'],
            'amount_min' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'amount_max' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'pattern' => ['nullable', 'string', 'max:200'],
            'fee_mode' => ['required', Rule::enum(BankFilterFee::class)],
            'fee_contains' => ['nullable', 'array', 'max:20'],
            'fee_contains.*' => ['nullable', 'string', 'max:100'],
            'fee_excludes' => ['nullable', 'array', 'max:20'],
            'fee_excludes.*' => ['nullable', 'string', 'max:100'],
            'action' => ['required', Rule::enum(BankFilterAction::class)],
            'planned_id' => ['nullable', 'required_if:action,pay', 'integer'],
            'title' => ['nullable', 'string', 'max:255'],
            'title_with_month' => ['sometimes', 'boolean'],
            'category_id' => ['nullable', 'integer'],
            'organization_id' => ['nullable', 'integer'],
            'person_id' => ['nullable', 'integer'],
            ...$this->cardRules(),
            'notes' => ['nullable', 'string', 'max:5000'],
            'transfer_account_id' => ['nullable', 'required_if:action,transfer', 'integer'],
        ];
    }

    /**
     * The checks after the rules, on the criteria: words or a pattern that can be used, an amount range that is not
     * inverted, the commission's words when the filter recognises it with words, the account of a transfer other than
     * the one read, and no movement to sort that another filter already takes.
     *
     * @return list<Closure(Validator):void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $criteria = $this->criteria();
            $columns = $this->filterColumns();

            if (! $criteria->isSafe()) {
                $criteria->pattern === null
                    ? $validator->errors()->add('contains', __('Write at least one word of three letters or more: a filter without words would take every movement.'))
                    : $validator->errors()->add('pattern', __('This expression would take every movement or cannot be read: change it.'));

                return;
            }

            if ($criteria->amountMin !== null && $criteria->amountMax !== null && (float) $criteria->amountMin > (float) $criteria->amountMax) {
                $validator->errors()->add('amount_max', __('The amount up to must be at least the amount from.'));
            }

            if ($columns['fee_mode'] === BankFilterFee::Words && ! BankFilterCriteria::fromArray(['contains' => $columns['fee_contains']])->isSafe()) {
                $validator->errors()->add('fee_contains', __('Write at least one word of three letters or more of the commission.'));
            }

            if ($columns['transfer_account_id'] !== null && $columns['transfer_account_id'] === $columns['financial_account_id']) {
                $validator->errors()->add('transfer_account_id', __('Choose another account than the one the filter reads.'));
            }

            $conflicts = resolve(BankFilters::class)->conflicts($criteria, $this->filter()?->id);

            if ($conflicts->isNotEmpty()) {
                $validator->errors()->add('contains', trans_choice(
                    '{1} A movement to sort is already taken by the filter :names: change one of the two.|[2,*] :count movements to sort are already taken by the filters :names: change one of the two.',
                    $conflicts->count(),
                    ['names' => $conflicts->map(static fn (BankFilter $filter): string => '«'.$filter->name.'»')->unique()->implode(', ')],
                ));
            }
        }];
    }

    /**
     * The filter of the address, null for a new one.
     */
    public function filter(): ?BankFilter
    {
        $filter = $this->route('filter');

        return $filter instanceof BankFilter ? $filter : null;
    }

    /**
     * The category the filter keeps when the form does not send one: the one already saved, null on a new one.
     */
    protected function savedCategoryId(): ?int
    {
        return $this->filter()?->category_id;
    }

    /**
     * The card already on the filter being changed, null on a new one or without a card.
     */
    protected function savedCard(): ?string
    {
        $filter = $this->filter();

        return $filter instanceof BankFilter ? PresetLink::key($filter->card_type, $filter->card_id) : null;
    }
}

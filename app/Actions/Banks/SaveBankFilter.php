<?php

declare(strict_types=1);

namespace App\Actions\Banks;

use App\Enums\BankFilterAction;
use App\Enums\BankFilterFee;
use App\Models\BankFilter;
use App\Support\Banks\BankFilterCriteria;
use App\Support\PresetLink;

/**
 * Saves a bank filter, new or changed, and lets it work at once on the movements still to sort that it takes.
 */
final readonly class SaveBankFilter
{
    public function __construct(private MatchBankTransactions $match) {}

    /**
     * Writes the filter, then applies it (`MatchBankTransactions::forFilter()`) once the save is committed: that
     * method must not run inside an open transaction. A single save needs no transaction of its own.
     *
     * @param  array<string, mixed>  $data  the fields of the form, validated (`SaveBankFilterRequest`)
     * @param  BankFilter|null  $filter  the filter to change, null for a new one
     * @return array{filter: BankFilter, settled: int} the filter saved and how many movements it settled
     */
    public function handle(array $data, ?BankFilter $filter = null): array
    {
        $filter ??= new BankFilter;
        $filter->fill(self::columns($data))->save();

        return ['filter' => $filter, 'settled' => $this->match->forFilter($filter)];
    }

    /**
     * The columns a filter takes from its form: the words cleaned, the amounts without sign, the planned item as the
     * column of its direction (`planned_id`), the card as its pair of columns, and empty the fields its action does not
     * use (a filter that pays keeps no title, one that creates no planned item). The preview builds its filter, not
     * saved, from the same columns.
     *
     * @param  array<string, mixed>  $data  the fields of the form
     * @return array<string, mixed>
     */
    public static function columns(array $data): array
    {
        $criteria = BankFilterCriteria::fromArray([
            'contains' => $data['contains'] ?? null,
            'excludes' => $data['excludes'] ?? null,
            'direction' => $data['direction'] ?? null,
            'accountId' => $data['financial_account_id'] ?? null,
            'amountMin' => $data['amount_min'] ?? null,
            'amountMax' => $data['amount_max'] ?? null,
            'pattern' => $data['pattern'] ?? null,
        ]);
        $action = BankFilterAction::tryFrom(is_string($data['action'] ?? null) ? $data['action'] : '');
        $fee = BankFilterFee::tryFrom(is_string($data['fee_mode'] ?? null) ? $data['fee_mode'] : '') ?? BankFilterFee::Auto;
        $feeWords = BankFilterCriteria::fromArray(['contains' => $data['fee_contains'] ?? null, 'excludes' => $data['fee_excludes'] ?? null]);
        $expense = $criteria->direction === 'expense';
        $pays = $action === BankFilterAction::Pay;
        $creates = $action === BankFilterAction::Create;
        $card = $creates && $expense ? PresetLink::from($data['card'] ?? null) : null;
        $name = $data['name'] ?? null;

        return [
            'name' => is_string($name) ? trim($name) : '',
            'contains' => $criteria->contains,
            'excludes' => $criteria->excludes,
            'direction' => $criteria->direction,
            'financial_account_id' => $criteria->accountId,
            'amount_min' => $criteria->amountMin,
            'amount_max' => $criteria->amountMax,
            'pattern' => $criteria->pattern,
            'fee_mode' => $fee,
            'fee_contains' => $fee === BankFilterFee::Words ? $feeWords->contains : null,
            'fee_excludes' => $fee === BankFilterFee::Words ? $feeWords->excludes : null,
            'action' => $action,
            'planned_expense_id' => $pays && $expense ? self::id($data['planned_id'] ?? null) : null,
            'planned_income_id' => $pays && ! $expense ? self::id($data['planned_id'] ?? null) : null,
            'title' => $creates ? self::text($data['title'] ?? null) : null,
            'title_with_month' => $creates && filter_var($data['title_with_month'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'category_id' => $creates && $expense ? self::id($data['category_id'] ?? null) : null,
            'organization_id' => $creates ? self::id($data['organization_id'] ?? null) : null,
            'person_id' => $creates ? self::id($data['person_id'] ?? null) : null,
            'card_type' => $card['type'] ?? null,
            'card_id' => $card['id'] ?? null,
            'notes' => $creates ? self::text($data['notes'] ?? null) : null,
            'transfer_account_id' => $action === BankFilterAction::Transfer ? self::id($data['transfer_account_id'] ?? null) : null,
        ];
    }

    /**
     * An id sent by the form, or null.
     *
     * @param  mixed  $value  what arrived
     */
    private static function id(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    /**
     * A text sent by the form, trimmed, or null when it is empty.
     *
     * @param  mixed  $value  what arrived
     */
    private static function text(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Banks;

use App\Actions\Banks\ApplyBankFilter;
use App\Data\BankFeeData;
use App\Data\BankFilterPreviewData;
use App\Data\BankFilterPreviewRowData;
use App\Data\BankTransactionData;
use App\Enums\BankFilterAction;
use App\Enums\BankFilterOutcome;
use App\Models\BankFilter;
use App\Models\BankTransaction;
use App\Support\Banks\BankFilterCriteria;
use App\Support\Banks\BankFilterDraft;
use App\Support\Banks\BankFilterMatch;
use App\Support\Banks\BankText;
use Illuminate\Database\Eloquent\Collection;

/**
 * The preview of a bank filter while it is written: the movements of the last days of a first read (90) that its
 * criteria take, with the words found, the commission, what saving would do with each (the same decision as
 * `ApplyBankFilter`, asked with `foresee()`) and the other filter that takes it already. It writes nothing.
 */
final readonly class BankFilterPreview
{
    public function __construct(
        private BankFilters $filters,
        private BankFees $fees,
        private ApplyBankFilter $apply,
    ) {}

    /**
     * What the filter takes and what saving it would do. A movement already sorted (linked, put aside, a transfer) is
     * listed as `linked` and never counted; one declined by *It was not this one*, or a commission, stays to sort
     * (`none`). The summary counts what saving would do with the movements to sort, those another filter takes too
     * included (while there is one the filter does not save).
     *
     * @param  BankFilterCriteria  $criteria  what the filter takes
     * @param  array<string, mixed>  $settings  the other columns of the filter (`SaveBankFilter::columns()`): the action and what it needs, the commission
     * @param  int|null  $exceptFilter  the id of the filter being changed, which never conflicts with itself
     */
    public function for(BankFilterCriteria $criteria, array $settings, ?int $exceptFilter = null): BankFilterPreviewData
    {
        $filter = $this->filter($criteria, $settings);
        $rows = new Collection($this->filters->taken($criteria, config()->integer('bank_feed.first_days'))->all());
        $rows->load(['transferAccount', 'expense', 'income.organization']);
        $forecasts = $this->forecasts($rows, $filter);
        $summary = ['pay' => 0, 'create' => 0, 'link' => 0, 'transfer' => 0, 'ignore' => 0];
        $conflicts = 0;
        $list = [];

        foreach ($rows as $row) {
            $forecast = $forecasts[$row->id];
            $takenBy = $this->filters->otherTaking($row, $exceptFilter)?->name;
            $fee = $this->fees->feeOf($row, $filter);

            if (array_key_exists($forecast['will'], $summary)) {
                $summary[$forecast['will']]++;
            }

            if ($takenBy !== null) {
                $conflicts++;
            }

            $list[] = new BankFilterPreviewRowData(
                transaction: BankTransactionData::fromTransaction($row, withLink: true),
                hits: BankFilterMatch::hits($criteria, $row->text),
                fee: $fee instanceof BankTransaction ? BankFeeData::fromTransaction($fee) : null,
                will: $forecast['will'],
                will_title: $forecast['title'],
                taken_by: $takenBy,
                will_on: $forecast['on'],
            );
        }

        return new BankFilterPreviewData($list, $summary, $conflicts);
    }

    /**
     * What saving a filter made from a proposal would do with each of the movements of a group, by id, in the order
     * the save applies them (`forecasts()`). A proposal without an action forecasts `none`.
     *
     * @param  Collection<int, BankTransaction>  $rows  the movements of the group
     * @param  BankFilterDraft|null  $draft  the filter of the group with its proposal (`BankFilterDraft::fromGroup()`)
     * @return array<int, array{will: string, title: ?string, on: ?string}>
     */
    public function forGroup(Collection $rows, ?BankFilterDraft $draft): array
    {
        $rows->loadMissing(['transferAccount', 'expense', 'income.organization']);

        return $this->forecasts($rows, (new BankFilter)->forceFill($draft?->toArray() ?? []));
    }

    /**
     * What saving would do with each movement, by its id. The movements are asked oldest first, as the save applies
     * them (`MatchBankTransactions::forFilter()`), each knowing what the earlier ones would take: the same recorded
     * expense or the same due date is never counted twice. The filter is one instance for the whole call, so its
     * planned item and the account of its transfer are read once.
     *
     * @param  Collection<int, BankTransaction>  $rows  the movements, newest first
     * @param  BankFilter  $filter  the filter, not saved
     * @return array<int, array{will: string, title: ?string, on: ?string}>
     */
    private function forecasts(Collection $rows, BankFilter $filter): array
    {
        $used = [];
        $forecasts = [];
        $oldestFirst = $rows->sortBy([
            static fn (BankTransaction $a, BankTransaction $b): int => $a->booked_on?->toDateString() <=> $b->booked_on?->toDateString(),
            static fn (BankTransaction $a, BankTransaction $b): int => $a->id <=> $b->id,
        ]);

        foreach ($oldestFirst as $row) {
            $forecast = $this->forecast($row, $filter, $used);

            if ($forecast['used'] !== null) {
                $used[] = $forecast['used'];
            }

            $forecasts[$row->id] = ['will' => $forecast['will'], 'title' => $forecast['title'], 'on' => $forecast['on']];
        }

        return $forecasts;
    }

    /**
     * What saving would do with one movement, as the preview words it.
     *
     * @param  BankTransaction  $row  the movement
     * @param  BankFilter  $filter  the filter, not saved
     * @param  list<string>  $used  what the earlier movements would take (`ApplyBankFilter::foresee()`)
     * @return array{will: string, title: ?string, on: ?string, used: ?string}
     */
    private function forecast(BankTransaction $row, BankFilter $filter, array $used): array
    {
        if (! BankFilters::toSort($row)) {
            $sorted = $row->declined_at === null && ! BankText::isFee($row->text);

            return ['will' => $sorted ? 'linked' : 'none', 'title' => null, 'on' => null, 'used' => null];
        }

        // A proposal may have no action yet: nothing would happen
        if (! $filter->getAttribute('action') instanceof BankFilterAction) {
            return ['will' => 'none', 'title' => null, 'on' => null, 'used' => null];
        }

        $foreseen = $this->apply->foresee($row, $filter, $used);

        $will = match ($foreseen['outcome']) {
            BankFilterOutcome::Paid => 'pay',
            BankFilterOutcome::Created => 'create',
            BankFilterOutcome::Linked => 'link',
            BankFilterOutcome::Transferred => 'transfer',
            BankFilterOutcome::Ignored => 'ignore',
            BankFilterOutcome::None => 'none',
        };

        return ['will' => $will, 'title' => $foreseen['title'], 'on' => $foreseen['on'], 'used' => $foreseen['used']];
    }

    /**
     * The filter the preview asks about, never saved: its criteria and the other columns of the form.
     *
     * @param  BankFilterCriteria  $criteria  what it takes
     * @param  array<string, mixed>  $settings  the other columns
     */
    private function filter(BankFilterCriteria $criteria, array $settings): BankFilter
    {
        return (new BankFilter)->forceFill([
            ...$settings,
            'contains' => $criteria->contains,
            'excludes' => $criteria->excludes,
            'direction' => $criteria->direction,
            'financial_account_id' => $criteria->accountId,
            'amount_min' => $criteria->amountMin,
            'amount_max' => $criteria->amountMax,
            'pattern' => $criteria->pattern,
        ]);
    }
}

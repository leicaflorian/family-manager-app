<?php

declare(strict_types=1);

namespace App\Actions\Cards;

use App\Actions\Budget\RecordPayment;
use App\Actions\Budget\SavePlanned;
use App\Models\PlannedExpense;
use App\Models\Subscription;
use App\Services\Deadlines;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Creates the planned expense of a new subscription from the section *Planned expense* of its form: the name, the
 * supplier, the holder and the account come from the card, the cost, how often and the first payment from the
 * section, which stores nothing on the card. Asked to, it also pays the first due date.
 */
final readonly class StartSubscriptionPlanned
{
    /**
     * @param  SavePlanned  $save  creates the planned expense
     * @param  Deadlines  $deadlines  finds its first due date
     * @param  RecordPayment  $record  pays it
     */
    public function __construct(private SavePlanned $save, private Deadlines $deadlines, private RecordPayment $record) {}

    /**
     * Creates the planned expense linked to the card and, with `paid`, the payment of its first due date.
     *
     * @param  Subscription  $card  the subscription just created
     * @param  array{amount: float, interval: int, interval_unit: string, start_date: string, category_id: ?int, paid: bool}  $plan  the values of the section
     * @return PlannedExpense the planned expense created
     *
     * @throws ValidationException if the first due date is already paid (it cannot be, on a new planned expense)
     */
    public function handle(Subscription $card, array $plan): PlannedExpense
    {
        $planned = $this->save->handle('expense', [
            'title' => $card->name,
            'amount' => $plan['amount'],
            'is_recurring' => true,
            'is_estimated' => false,
            'interval' => $plan['interval'],
            'interval_unit' => $plan['interval_unit'],
            'start_date' => $plan['start_date'],
            'organization_id' => $card->organization_id,
            'person_id' => $card->person_id,
            'financial_account_id' => $card->financial_account_id,
            'category_id' => $plan['category_id'],
            'card_type' => $card->getMorphClass(),
            'card_id' => $card->id,
        ]);

        if (! $planned instanceof PlannedExpense) {
            throw new \LogicException('An expense was asked for.');
        }

        if ($plan['paid']) {
            $first = CarbonImmutable::createFromFormat('!Y-m-d', $plan['start_date'], 'Europe/Rome') ?: throw new \LogicException('The first payment was validated as a date.');
            $this->record->handle($this->deadlines->find($planned, $first), $first, $plan['amount'], null, null);
        }

        return $planned;
    }
}

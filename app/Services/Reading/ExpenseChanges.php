<?php

declare(strict_types=1);

namespace App\Services\Reading;

use App\Data\Reading\Extraction;
use App\Data\Reading\MovementValuesData;
use App\Models\Expense;
use App\Models\FinancialAccount;
use App\Models\PlannedExpense;
use App\Services\Deadlines;
use App\Services\Occurrences;
use App\Support\Money;
use App\Support\Reading\Choices;
use App\Support\Schedule;
use App\Support\Translated;
use Carbon\CarbonImmutable;

/**
 * What the expense already recorded takes from a file linked to it (`expenseChanges` of the old reader): the
 * fields it has empty among planned expense, category and issuer; for a payment receipt also the account of the
 * IBAN and the fee, added to the amount once and only when the receipt pays the whole amount to the cent. Title and
 * date never change. The planned expense is taken only when the day of the expense falls on one of its due dates
 * that no movement pays yet: a due date is paid when a movement names it, so linking it to a paid one would pay it
 * twice. Nothing is written: the proposal shows the sentences, the import writes `data`.
 */
final readonly class ExpenseChanges
{
    /**
     * @param  Matcher  $matcher  the search of the account by the last digits of the IBAN
     * @param  Occurrences  $occurrences  the due date the day of the expense falls on
     * @param  Deadlines  $deadlines  whether that due date is already paid
     */
    public function __construct(private Matcher $matcher, private Occurrences $occurrences, private Deadlines $deadlines) {}

    /**
     * The changes of the expense, in the language of the app.
     *
     * @param  Expense  $current  the expense the file is linked to
     * @param  MovementValuesData  $values  the expense the file proposes, with the corrections of the person
     * @param  Extraction  $x  what the model read
     * @param  ReadingContext  $context  the lists whose labels name the category and the issuer
     * @return array{data: array<string, int|float>, changes: list<string>, warnings: list<string>}
     */
    public function of(Expense $current, MovementValuesData $values, Extraction $x, ReadingContext $context): array
    {
        $data = [];
        $changes = [];
        $warnings = [];

        $fill = static function (string $column, ?int $value, ?int $now, string $sentence, ?string $shown) use (&$data, &$changes): void {
            if ($now !== null || $value === null) {
                return;
            }
            $data[$column] = $value;
            $changes[] = Translated::text($sentence, ['value' => $shown ?? (string) $value]);
        };
        $fill('planned_expense_id', $this->openLead($current, $values)?->id, $current->planned_expense_id, 'Planned expense: :value', $values->lead['title'] ?? null);
        $fill('category_id', $values->category_id, $current->category_id, 'Category: :value', Choices::label($context->expenseCategories, $values->category_id));
        $fill('organization_id', $values->organization_id, $current->organization_id, 'Organization: :value', Choices::label($context->organizations, $values->organization_id));

        if ($x->kind !== 'payment_receipt') {
            return ['data' => $data, 'changes' => $changes, 'warnings' => $warnings];
        }

        $account = $this->matcher->account($x->iban_last_four);
        if ($account instanceof FinancialAccount && $current->financial_account_id !== $account->id) {
            $data['financial_account_id'] = $account->id;
            $before = $current->financialAccount?->name;
            $changes[] = $before === null
                ? Translated::text('Account: :name', ['name' => $account->name])
                : Translated::text('Account: :name, before :before', ['name' => $account->name, 'before' => $before]);
        }

        $fee = Money::cents($values->fee_amount);
        $paid = Money::cents($values->amount) - $fee;
        $amount = Money::cents($current->amount);
        $stored = $amount - Money::cents($current->fee_amount);

        if ($paid !== $stored) {
            $warnings[] = Translated::text('The receipt is :paid, the expense :amount: the amount and the fee of the expense do not change.', [
                'paid' => Money::euro($paid / 100), 'amount' => Money::euro($amount / 100),
            ]);
        } elseif ($fee > 0 && Money::cents($current->fee_amount) === 0) {
            $data['amount'] = ($amount + $fee) / 100;
            $data['fee_amount'] = $fee / 100;
            array_unshift($changes, Translated::text('Amount: :amount (:before plus :fee of fee)', [
                'amount' => Money::euro(($amount + $fee) / 100), 'before' => Money::euro($amount / 100), 'fee' => Money::euro($fee / 100),
            ]));
        }

        return ['data' => $data, 'changes' => $changes, 'warnings' => $warnings];
    }

    /**
     * The commitment of the paper, when the day of the expense falls on one of its due dates that is still open.
     *
     * @param  Expense  $current  the expense the file is linked to
     * @param  MovementValuesData  $values  the expense the file proposes, whose `lead` names the commitment
     */
    private function openLead(Expense $current, MovementValuesData $values): ?PlannedExpense
    {
        $id = $values->lead['id'] ?? null;
        $planned = $id === null || $current->planned_expense_id !== null ? null : PlannedExpense::query()->find($id);
        if (! $planned instanceof PlannedExpense || $current->date === null) {
            return null;
        }

        $day = $this->occurrences->at(Schedule::of($planned), $current->date)?->originalDate;
        if (! $day instanceof CarbonImmutable) {
            return null;
        }

        $deadline = $this->deadlines->findMany($planned, [$day])[$day->toDateString()] ?? null;

        return $deadline !== null && ! $deadline->isPaid() ? $planned : null;
    }
}

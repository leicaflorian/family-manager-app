<?php

declare(strict_types=1);

namespace App\Services\Banks;

use App\Actions\Budget\MovePlannedDocuments;
use App\Models\BankTransaction;
use App\Models\Expense;
use App\Models\PlannedExpense;
use App\Support\Banks\BankSplitMatch;
use App\Support\Deadline;
use App\Support\Money;

/**
 * Tells a bank debit that pays more planned expenses together: one direct debit for a bill of electricity and gas,
 * taken by the filters that pay its two planned expenses. Pure reading: `SettleFromBank::split` does the paying.
 */
final readonly class BankSplit
{
    public function __construct(
        private BankMatcher $matcher,
        private BankFilters $filters,
        private BankDuplicates $duplicates,
        private MovePlannedDocuments $documents,
    ) {}

    /**
     * The planned expenses the debit pays together, only for money out taken by two or more filters that all pay a
     * planned expense (`BankFilters::payingTogether`). For each of them the due date the debit would settle
     * (`BankMatcher::deadline`); then, in this order:
     *
     * 1. `pay`: the unpaid due dates whose waiting documents (as *I paid it* would move them) share a document among
     *    two or more planned expenses, when their amounts (with the exceptions) add up to the debit to the cent;
     * 2. `link`: the payments already recorded on the paid due dates, not linked to another bank transaction, that
     *    share a document, when their amounts add up to the debit to the cent.
     *
     * Otherwise none, and the debit goes to the filters as one movement (`BankFilters::settling`). The match carries
     * the filter of its first planned expense, which says how the commission of the debit is found.
     *
     * @param  BankTransaction  $transaction  The bank transaction.
     */
    public function find(BankTransaction $transaction): ?BankSplitMatch
    {
        $filters = $this->filters->payingTogether($transaction);

        if (count($filters) < 2) {
            return null;
        }

        $cents = Money::absCents($transaction->amount);
        $unpaid = [];
        $paid = [];

        foreach ($filters as $filter) {
            /** @var PlannedExpense $planned */
            $planned = $filter->plannedExpense;
            $deadline = $this->matcher->deadline($transaction, $planned);

            if (! $deadline instanceof Deadline || $deadline->isSkipped()) {
                continue;
            }

            if (! $deadline->isPaid()) {
                $unpaid[] = ['deadline' => $deadline, 'payment' => null, 'documents' => $this->waitingDocuments($deadline)];
            } elseif ($deadline->payment instanceof Expense && $this->duplicates->isFree($deadline->payment, $transaction)) {
                /** @var list<int> $documents */
                $documents = $deadline->payment->documents()->pluck('documents.id')->all();
                $paid[] = ['deadline' => $deadline, 'payment' => $deadline->payment, 'documents' => $documents];
            }
        }

        $match = $this->together('pay', $unpaid, $cents) ?? $this->together('link', $paid, $cents);

        return $match instanceof BankSplitMatch ? $match->withFilter($filters[$match->plannedIds()[0]] ?? null) : null;
    }

    /**
     * The first document, in order of id, shared by two or more of the due dates whose amounts (of the due dates for
     * `pay`, of the payments for `link`) add up to the debit, as a match.
     *
     * @param  'pay'|'link'  $mode  The kind of match.
     * @param  list<array{deadline: Deadline, payment: ?Expense, documents: list<int>}>  $items  The due dates, in order of id of their planned expense.
     * @param  int  $cents  The amount of the debit in cents.
     */
    private function together(string $mode, array $items, int $cents): ?BankSplitMatch
    {
        $documentIds = array_unique(array_merge(...array_column($items, 'documents')));
        sort($documentIds);

        foreach ($documentIds as $documentId) {
            $sharing = array_values(array_filter($items, static fn (array $item): bool => in_array($documentId, $item['documents'], true)));
            $total = array_sum(array_map(static fn (array $item): int => Money::absCents($item['payment'] instanceof Expense ? $item['payment']->amount : $item['deadline']->occurrence->amount), $sharing));

            if (count($sharing) >= 2 && $total === $cents) {
                return new BankSplitMatch(
                    $mode,
                    array_column($sharing, 'deadline'),
                    $mode === 'link' ? array_values(array_filter(array_column($sharing, 'payment'))) : [],
                    $documentId,
                );
            }
        }

        return null;
    }

    /**
     * The ids of the documents waiting on the planned expense that paying the due date would move to its expense.
     *
     * @param  Deadline  $deadline  The unpaid due date of a planned expense.
     * @return list<int>
     */
    private function waitingDocuments(Deadline $deadline): array
    {
        $planned = $deadline->planned;

        if (! $planned instanceof PlannedExpense) {
            return [];
        }

        /** @var list<int> $ids */
        $ids = $this->documents->waiting($planned, $deadline->occurrence->date)->pluck('documents.id')->all();

        return $ids;
    }
}

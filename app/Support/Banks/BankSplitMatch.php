<?php

declare(strict_types=1);

namespace App\Support\Banks;

use App\Models\BankFilter;
use App\Models\Expense;
use App\Support\Deadline;

/**
 * One bank debit that pays more planned expenses together, found by `BankSplit`: a bill of electricity and gas paid
 * with one direct debit. Either the due dates are still to pay (`pay`) or their payments are already recorded and the
 * debit only links to them (`link`).
 */
final readonly class BankSplitMatch
{
    /**
     * @param  'pay'|'link'  $mode  Whether the due dates are to pay, or already paid and only to link.
     * @param  list<Deadline>  $deadlines  The due dates, in order of id of their planned expense.
     * @param  list<Expense>  $payments  The payments already recorded, in the same order: only for `link`.
     * @param  int  $documentId  The document the due dates or the payments share.
     * @param  BankFilter|null  $filter  The filter that pays the first planned expense: it says how the commission of the debit is found, and the debit is written as settled by it.
     */
    public function __construct(
        public string $mode,
        public array $deadlines,
        public array $payments,
        public int $documentId,
        public ?BankFilter $filter = null,
    ) {}

    /**
     * The same match with the filter of its first planned expense.
     *
     * @param  BankFilter|null  $filter  The filter.
     */
    public function withFilter(?BankFilter $filter): self
    {
        return new self($this->mode, $this->deadlines, $this->payments, $this->documentId, $filter);
    }

    /**
     * Whether another match is the same: same mode, document, due dates and payments. A match found again under the
     * locks must be this one, or the debit is no longer paid this way.
     *
     * @param  BankSplitMatch|null  $other  The match found again, if any.
     */
    public function sameAs(?self $other): bool
    {
        return $other instanceof self
            && $other->mode === $this->mode
            && $other->documentId === $this->documentId
            && $other->deadlineKeys() === $this->deadlineKeys()
            && $other->paymentIds() === $this->paymentIds();
    }

    /**
     * The ids of the planned expenses, in order.
     *
     * @return list<int>
     */
    public function plannedIds(): array
    {
        return array_map(static fn (Deadline $deadline): int => (int) $deadline->planned->id, $this->deadlines);
    }

    /**
     * Each due date as its planned item and original date.
     *
     * @return list<string>
     */
    private function deadlineKeys(): array
    {
        return array_map(static fn (Deadline $deadline): string => $deadline->planned->id.'@'.$deadline->occurrence->originalDate->toDateString(), $this->deadlines);
    }

    /**
     * The ids of the payments, in order.
     *
     * @return list<int>
     */
    private function paymentIds(): array
    {
        return array_map(static fn (Expense $payment): int => $payment->id, $this->payments);
    }
}

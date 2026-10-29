<?php

declare(strict_types=1);

namespace App\Actions\Budget;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\Expense;
use App\Models\Income;
use App\Models\PlannedExpense;
use App\Services\Deadlines;
use App\Services\FileStore;
use App\Services\Occurrences;
use App\Support\Deadline;
use App\Support\DefaultAccount;
use App\Support\PaymentTitle;
use App\Support\PlannedKind;
use App\Support\Schedule;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * *I paid it* and *It arrived*: turns a due date into the expense or income that settles it.
 */
final readonly class RecordPayment
{
    public function __construct(
        private Deadlines $deadlines,
        private Occurrences $occurrences,
        private FileStore $files,
    ) {}

    /**
     * Records the payment of a due date, in one transaction.
     *
     * @param  Deadline  $deadline  The due date to settle.
     * @param  CarbonImmutable  $date  The day of the payment.
     * @param  float  $amount  What was paid or received.
     * @param  ?int  $accountId  The account; empty takes the one of the planned item, then the default one.
     * @param  ?UploadedFile  $receipt  A file that becomes a document linked to the payment.
     *
     * @throws ValidationException if the due date is already paid
     */
    public function handle(Deadline $deadline, CarbonImmutable $date, float $amount, ?int $accountId, ?UploadedFile $receipt): Expense|Income
    {
        return DB::transaction(function () use ($deadline, $date, $amount, $accountId, $receipt): Expense|Income {
            // The caller's due date can be stale: with the planned item locked, read again whether something already pays it.
            PlannedKind::lock($deadline->planned);
            $current = $this->deadlines->find($deadline->planned, $deadline->occurrence->originalDate);

            if ($current->isPaid()) {
                throw ValidationException::withMessages(['date' => __('This due date is already paid.')]);
            }

            $planned = $current->planned;
            $original = $current->occurrence->originalDate;
            $title = PaymentTitle::for($planned->displayTitle(), $original);
            $account = $accountId ?? $planned->financial_account_id ?? DefaultAccount::id();

            $columns = [
                ...PlannedKind::paymentColumns($planned),
                'title' => $title,
                'amount' => $amount,
                'date' => $date,
                'occurrence_date' => $original,
                'financial_account_id' => $account,
            ];
            $payment = $planned instanceof PlannedExpense
                ? Expense::query()->create([...$columns, 'planned_expense_id' => $planned->id])
                : Income::query()->create([...$columns, 'planned_income_id' => $planned->id]);

            if ($planned instanceof PlannedExpense && $payment instanceof Expense) {
                $this->moveDocuments($planned, $current, $payment);
            }

            if ($receipt instanceof UploadedFile) {
                $this->attachReceipt($payment, $receipt, $title, $date);
            }

            return $payment;
        });
    }

    /**
     * Moves the documents waiting on the planned expense to the new expense: all of them for a one-off planned
     * expense, those from one period before the due date to half a period after for a recurring one.
     *
     * @param  PlannedExpense  $planned  The planned expense.
     * @param  Deadline  $deadline  The due date.
     * @param  Expense  $expense  The expense just created.
     */
    private function moveDocuments(PlannedExpense $planned, Deadline $deadline, Expense $expense): void
    {
        $query = Document::query()->where('planned_expense_id', $planned->id)->whereDoesntHave('expenses');
        $schedule = Schedule::of($planned);

        if ($schedule->recurring) {
            $step = $this->occurrences->stepDays($schedule);
            $due = $deadline->occurrence->date->startOfDay();
            $query->whereBetween('date', [$due->subDays((int) round($step)), $due->addDays((int) floor($step / 2))]);
        }

        $expense->documents()->syncWithoutDetaching($query->pluck('id')->all());
    }

    /**
     * Stores the receipt as a document with the title of the payment, linked to it.
     *
     * @param  Expense|Income  $payment  The payment.
     * @param  UploadedFile  $receipt  The file.
     * @param  string  $title  The title of the payment.
     * @param  CarbonImmutable  $date  The day of the payment.
     */
    private function attachReceipt(Expense|Income $payment, UploadedFile $receipt, string $title, CarbonImmutable $date): void
    {
        $document = Document::query()->create([
            'title' => $title,
            'file_id' => $this->files->store($receipt)->id,
            'status' => DocumentStatus::Active,
            'date' => $date,
            'person_id' => $payment->person_id,
            'organization_id' => $payment->organization_id,
        ]);

        $payment->documents()->attach($document);
    }
}

<?php

declare(strict_types=1);

namespace App\Actions\Banks;

use App\Models\BankTransaction;
use App\Models\Expense;
use App\Models\Income;
use App\Models\ReminderNotice;
use App\Models\User;
use App\Support\Deadline;
use App\Support\Money;
use App\Support\Translated;
use Closure;

/**
 * Tells everyone in the family that the bank paid a due date: a notice of the bell for each account, both roles,
 * about the bank transaction, except the person who assigned it (they already know). The lines are written in the language of each account.
 */
final readonly class NotifyBankPayment
{
    /**
     * Writes the notices: «72,40 € dal Conto BancoPosta», and «72,40 € invece di 45,00 €» when the amount is more than
     * 10% away from the one of the due date and the planned item is not an estimate.
     *
     * @param  BankTransaction  $transaction  The transaction of the bank that paid.
     * @param  Expense|Income  $payment  The payment it is linked to.
     * @param  Deadline  $deadline  The due date it settled.
     * @param  int|null  $except  The account that assigned the transaction, which gets no notice.
     */
    public function handle(BankTransaction $transaction, Expense|Income $payment, Deadline $deadline, ?int $except = null): void
    {
        $amount = abs((float) $transaction->amount);
        $planned = $deadline->occurrence->amount;
        $account = $transaction->financialAccount->name ?? $payment->financialAccount->name ?? '';
        $far = ! $deadline->occurrence->estimated && $this->isFar($amount, $planned);

        $this->tell($transaction, $except, function (string $locale) use ($amount, $planned, $account, $far): array {
            $lines = [Translated::text(':amount from :account', ['amount' => Money::euro($amount), 'account' => $account], $locale)];

            if ($far) {
                $lines[] = Translated::text(':amount instead of :planned', ['amount' => Money::euro($amount), 'planned' => Money::euro($planned)], $locale);
            }

            return $lines;
        });
    }

    /**
     * Writes one notice for each account about a debit that paid more planned expenses together (a bill of
     * electricity and gas): «165,65 € dal Conto BPER». The row of the bell names the planned expenses.
     *
     * @param  BankTransaction  $transaction  The transaction of the bank that paid.
     * @param  list<Expense>  $payments  The payments it is linked to, the first one in `expense_id`.
     * @param  int|null  $except  The account that assigned the transaction, which gets no notice.
     */
    public function handleSplit(BankTransaction $transaction, array $payments, ?int $except = null): void
    {
        $amount = abs((float) $transaction->amount);
        $account = $transaction->financialAccount->name ?? ($payments[0] ?? null)?->financialAccount->name ?? '';

        $this->tell($transaction, $except, static fn (string $locale): array => [
            Translated::text(':amount from :account', ['amount' => Money::euro($amount), 'account' => $account], $locale),
        ]);
    }

    /**
     * Writes a notice about the transaction for each account of the family but one, with the lines written in the
     * language of each account.
     *
     * @param  BankTransaction  $transaction  The transaction of the bank.
     * @param  int|null  $except  The account that gets no notice.
     * @param  Closure(string): list<string>  $lines  The lines of the notice in a language.
     */
    private function tell(BankTransaction $transaction, ?int $except, Closure $lines): void
    {
        User::query()->inFamily($transaction->family_id)->when($except !== null, static fn ($query) => $query->whereKeyNot($except))->get()
            ->each(static function (User $user) use ($transaction, $lines): void {
                ReminderNotice::query()->create(['user_id' => $user->id, 'bank_transaction_id' => $transaction->id, 'sent_at' => now(), 'lines' => $lines($user->mailLocale())]);
            });
    }

    /**
     * Whether the amount of the bank is more than a tenth away from the planned one, compared in cents.
     *
     * @param  float  $amount  What the bank says.
     * @param  float  $planned  What the due date says.
     */
    private function isFar(float $amount, float $planned): bool
    {
        $cents = (int) round($amount * 100);
        $plannedCents = (int) round($planned * 100);

        return abs($cents - $plannedCents) * 10 > abs($plannedCents);
    }
}

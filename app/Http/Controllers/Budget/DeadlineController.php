<?php

declare(strict_types=1);

namespace App\Http\Controllers\Budget;

use App\Actions\Budget\ChangeOccurrence;
use App\Actions\Budget\RecordPayment;
use App\Data\DeadlineData;
use App\Data\OptionData;
use App\Enums\FinancialAccountStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Budget\ChangeOccurrenceRequest;
use App\Http\Requests\Budget\RecordPaymentRequest;
use App\Models\Expense;
use App\Models\FinancialAccount;
use App\Models\Income;
use App\Models\PlannedExpense;
use App\Models\User;
use App\Services\Deadlines;
use App\Support\Deadline;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * *A due date*: one due date of a planned expense or income, with *I paid it* (*It arrived*)
 * and *Change this due date*. The address names the planned item and the day the rules give.
 */
final class DeadlineController extends Controller
{
    /** How many of the following due dates the page lists. */
    private const int NEXT = 3;

    /** How many years ahead the following due dates are looked for. */
    private const int NEXT_YEARS = 2;

    public function __construct(private readonly Deadlines $deadlines) {}

    /**
     * *A due date*, with the next ones of the same planned item and the accounts to pay with.
     * With `?pay=1` the panel *I paid it* opens at once.
     *
     * @throws NotFoundHttpException if the address is not a due date of the family
     */
    public function show(Request $request, string $kind, string $id, string $date, #[CurrentUser] User $user): Response
    {
        $deadline = $this->deadlines->findByAddress($kind, $id, $date);
        $planned = $deadline->planned;
        $today = CarbonImmutable::today('Europe/Rome');
        $after = $deadline->occurrence->date->addDay();
        $next = array_slice($this->deadlines->between([$planned], $after, $after->addYears(self::NEXT_YEARS)), 0, self::NEXT);

        return Inertia::render('budget/Deadline', [
            'deadline' => DeadlineData::fromDeadline($deadline, $today),
            'next' => array_map(fn (Deadline $following): DeadlineData => DeadlineData::fromDeadline($following, $today), $next),
            'accounts' => $this->accounts(),
            'canPay' => $user->can('create', $planned instanceof PlannedExpense ? Expense::class : Income::class),
            'canChange' => $user->can('update', $planned),
            'pay' => $request->boolean('pay'),
        ]);
    }

    /**
     * *I paid it* / *It arrived*: records the payment and goes back to the due date.
     *
     * @throws NotFoundHttpException if the address is not a due date of the family
     */
    public function payment(RecordPaymentRequest $request, string $kind, string $id, string $date, RecordPayment $record): RedirectResponse
    {
        $deadline = $request->deadline();
        $paid = CarbonImmutable::createFromFormat('!Y-m-d', $request->string('date')->toString(), 'Europe/Rome') ?: throw new NotFoundHttpException;
        $account = $request->integer('financial_account_id');
        $receipt = $request->file('receipt');

        $record->handle($deadline, $paid, $request->float('amount'), $account === 0 ? null : $account, $receipt instanceof UploadedFile ? $receipt : null);
        Inertia::flash('toast', ['type' => 'success', 'message' => $kind === 'income' ? __('Income recorded.') : __('Payment recorded.')]);

        return $this->back($kind, $id, $date);
    }

    /**
     * *Change this due date*: saves the exceptions of the due date and goes back to it.
     *
     * @throws NotFoundHttpException if the address is not a due date of the family
     */
    public function exceptions(ChangeOccurrenceRequest $request, string $kind, string $id, string $date, ChangeOccurrence $change): RedirectResponse
    {
        $deadline = $request->deadline();
        $choices = $request->choices();

        $change->handle($deadline->planned, $deadline->occurrence->originalDate, $choices['skip'], $choices['amount'], $choices['date'], $choices['notes']);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Saved.')]);

        return $this->back($kind, $id, $date);
    }

    /**
     * Back to the page of the due date.
     *
     * @param  string  $kind  `expense` or `income`
     * @param  string  $id  the id of the planned item
     * @param  string  $date  the day the rules give
     */
    private function back(string $kind, string $id, string $date): RedirectResponse
    {
        return to_route('budget.deadlines.show', ['kind' => $kind, 'id' => $id, 'date' => $date]);
    }

    /**
     * The accounts to pay with: the active ones, the default first.
     *
     * @return list<OptionData>
     */
    private function accounts(): array
    {
        $accounts = FinancialAccount::query()
            ->where('status', FinancialAccountStatus::Active)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->map(static fn (FinancialAccount $account): OptionData => new OptionData((string) $account->id, $account->name))
            ->all();

        return array_values($accounts);
    }
}

<?php

declare(strict_types=1);

namespace App\Actions\Budget;

use App\Enums\IncomeType;
use App\Models\Expense;
use App\Models\Income;
use App\Models\PlannedExpense;
use App\Models\PlannedIncome;
use App\Services\Deadlines;
use App\Services\Occurrences;
use App\Support\DefaultAccount;
use App\Support\PlannedKind;
use App\Support\Schedule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Saves an expense or an income, new or changed. A chosen planned expense or income gives what the form left empty
 * (category, organization, account, holder), as `on_planned_expense_change` did in Directus; an empty account takes
 * the default one; an empty due date takes the one the day registers.
 */
final readonly class SaveMovement
{
    /** The columns that an empty field takes from the planned item. */
    private const array FROM_PLANNED = ['organization_id', 'financial_account_id', 'person_id'];

    public function __construct(private Occurrences $occurrences, private Deadlines $deadlines) {}

    /**
     * Creates the movement, or changes the one given.
     *
     * @param  string  $kind  `expense` or `income`
     * @param  array<string, mixed>  $columns  the columns to write, with `planned_id` for the planned item; a change writes only the ones it sends
     * @param  Expense|Income|null  $movement  the movement to change, null for a new one
     *
     * @throws InvalidArgumentException if the kind is not `expense` or `income`
     * @throws ValidationException if the due date is already paid by another movement
     */
    public function handle(string $kind, array $columns, Expense|Income|null $movement = null): Expense|Income
    {
        // The request checked the due date, but a payment can have arrived since: with the planned item locked,
        // read it again, as RecordPayment does. The planned item is found before the transaction, so the lock comes first in it.
        $planned = $this->planned($kind, $columns, $movement);

        return DB::transaction(function () use ($kind, $columns, $movement, $planned): Expense|Income {
            if ($planned instanceof PlannedExpense || $planned instanceof PlannedIncome) {
                PlannedKind::lock($planned);
            }

            $columns = $this->complete($kind, $columns, $movement, $planned);
            $this->assertOpen($kind, $columns, $movement);

            if ($movement instanceof Expense || $movement instanceof Income) {
                $movement->update($columns);

                return $movement;
            }

            return match ($kind) {
                'expense' => Expense::query()->create($columns),
                'income' => Income::query()->create($columns),
                default => throw new InvalidArgumentException("Unknown kind {$kind}."),
            };
        });
    }

    /**
     * The planned item the movement will belong to: the one sent, else the one it has.
     *
     * @param  string  $kind  `expense` or `income`
     * @param  array<string, mixed>  $columns  what the form sent
     * @param  Expense|Income|null  $movement  the movement being changed
     */
    private function planned(string $kind, array $columns, Expense|Income|null $movement): PlannedExpense|PlannedIncome|null
    {
        $current = $movement instanceof Expense ? $movement->planned_expense_id : $movement?->planned_income_id;
        $plannedId = array_key_exists('planned_id', $columns) ? $this->id($columns['planned_id']) : $current;

        return $plannedId === null ? null : PlannedKind::find($kind, (string) $plannedId);
    }

    /**
     * Checks that no other movement pays the due date this one is going to settle.
     *
     * @param  string  $kind  `expense` or `income`
     * @param  array<string, mixed>  $columns  the columns to write
     * @param  Expense|Income|null  $movement  the movement being changed
     *
     * @throws ValidationException if another movement pays that due date
     */
    private function assertOpen(string $kind, array $columns, Expense|Income|null $movement): void
    {
        $plannedId = $columns[$kind === 'expense' ? 'planned_expense_id' : 'planned_income_id'] ?? null;
        $day = $columns['occurrence_date'] ?? null;

        if (! is_numeric($plannedId) || ! is_string($day)) {
            return;
        }

        $payment = $this->deadlines->find(PlannedKind::find($kind, (string) $plannedId), CarbonImmutable::parse($day, 'Europe/Rome'))->payment;

        if ($payment !== null && ! $payment->is($movement)) {
            throw ValidationException::withMessages(['occurrence_date' => __('This due date is already paid: choose another one.')]);
        }
    }

    /**
     * The columns to write: the link to the planned item with what it gives, the account and the due date.
     *
     * @param  string  $kind  `expense` or `income`
     * @param  array<string, mixed>  $columns  what the form sent
     * @param  Expense|Income|null  $movement  the movement being changed
     * @param  PlannedExpense|PlannedIncome|null  $planned  the planned item it will belong to, from `planned()`
     * @return array<string, mixed>
     */
    private function complete(string $kind, array $columns, Expense|Income|null $movement, PlannedExpense|PlannedIncome|null $planned): array
    {
        $new = $movement === null;
        $link = $kind === 'expense' ? 'planned_expense_id' : 'planned_income_id';
        $sent = array_key_exists('planned_id', $columns);
        $current = $movement instanceof Expense ? $movement->planned_expense_id : $movement?->planned_income_id;
        $plannedId = $planned?->id;
        unset($columns['planned_id']);

        // What the planned item gives is taken when it is chosen, not at every save: a change must not bring back what the person emptied
        $chosen = $planned !== null && $plannedId !== $current;

        if ($sent || $new) {
            $columns[$link] = $plannedId;
        }

        if ($chosen) {
            $columns = $this->fill($columns, $planned);
        }

        if ($new || array_key_exists('financial_account_id', $columns)) {
            $columns['financial_account_id'] = $this->id($columns['financial_account_id'] ?? null) ?? DefaultAccount::id();
        }

        if ($kind === 'income' && $new) {
            $columns['type'] ??= IncomeType::Other->value;
        }

        return $this->withOccurrence($columns, $planned, $chosen, $movement);
    }

    /**
     * Fills the empty columns from the planned item.
     *
     * @param  array<string, mixed>  $columns  what the form sent
     * @param  PlannedExpense|PlannedIncome  $planned  the planned item chosen
     * @return array<string, mixed>
     */
    private function fill(array $columns, PlannedExpense|PlannedIncome $planned): array
    {
        $own = $planned instanceof PlannedExpense
            ? ['category_id' => $planned->category_id]
            : ['employment_id' => $planned->employment_id, 'type' => $planned->type->value];

        foreach ([...self::FROM_PLANNED, ...array_keys($own)] as $column) {
            if (($columns[$column] ?? null) === null || $columns[$column] === '') {
                $columns[$column] = $own[$column] ?? $planned->getAttribute($column);
            }
        }

        return $columns;
    }

    /**
     * Sets the due date that the movement settles: the one sent, else the one its day registers. Without a planned
     * item there is none.
     *
     * @param  array<string, mixed>  $columns  the columns so far
     * @param  PlannedExpense|PlannedIncome|null  $planned  the planned item, if there is one
     * @param  bool  $chosen  whether the planned item was just chosen
     * @param  Expense|Income|null  $movement  the movement being changed
     * @return array<string, mixed>
     */
    private function withOccurrence(array $columns, PlannedExpense|PlannedIncome|null $planned, bool $chosen, Expense|Income|null $movement): array
    {
        if ($planned === null) {
            return array_key_exists('planned_expense_id', $columns) || array_key_exists('planned_income_id', $columns)
                ? [...$columns, 'occurrence_date' => null]
                : $columns;
        }

        $sent = $columns['occurrence_date'] ?? null;

        if (($sent === null || $sent === '') && ($chosen || array_key_exists('occurrence_date', $columns))) {
            $sentDate = $columns['date'] ?? null;
            $date = is_string($sentDate) ? CarbonImmutable::parse($sentDate, 'Europe/Rome') : $movement?->date;
            $columns['occurrence_date'] = $date instanceof CarbonImmutable
                ? $this->occurrences->at(Schedule::of($planned), $date)?->originalDate->toDateString()
                : null;
        }

        return $columns;
    }

    /**
     * An id sent by the form as a number or as text.
     *
     * @param  mixed  $value  what was sent
     */
    private function id(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}

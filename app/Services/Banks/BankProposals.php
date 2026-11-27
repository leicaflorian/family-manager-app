<?php

declare(strict_types=1);

namespace App\Services\Banks;

use App\Data\BankProposalData;
use App\Enums\BankTransactionStatus;
use App\Enums\FinancialAccountStatus;
use App\Models\BankTransaction;
use App\Models\Expense;
use App\Models\FinancialAccount;
use App\Models\Income;
use App\Models\Organization;
use App\Models\PlannedExpense;
use App\Models\PlannedIncome;
use App\Services\Deadlines;
use App\Services\Occurrences;
use App\Support\Banks\BankMatch;
use App\Support\Banks\BankText;
use App\Support\Deadline;
use App\Support\PaymentTitle;
use App\Support\Schedule;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * «Maybe it is…» under the lone movements of *From the bank*: for each booked euro movement still to sort that is in no
 * group and is not a commission, the one expense or income already recorded, unpaid due date or transfer it most
 * likely is. Only reads: `AcceptBankProposal` does what the person says yes to.
 *
 * Points: 4 for the amount equal to the cent (required, except an estimated due date within 20%, which gets 1), 3 for
 * the same day, 2 within 2 days, 1 within 7, and 2 for the name of the organization in the text of the bank. A
 * transfer gets 4 and the points of the date for the opposite half on the other account, 2 when the text names the
 * account (its name or its last four digits) and 1 for a word of the transfers between accounts. Two candidates with
 * the same points at the top give no proposal. The keys the person rejected are skipped.
 */
final readonly class BankProposals
{
    /** The days between a movement and an expense or income recorded by hand. */
    private const int RECORDED_DAYS = 7;

    /** The days between the two halves of a transfer. */
    private const int TRANSFER_DAYS = 3;

    /** The most days a due date can be from the movement, whatever the period (as `BankMatcher::deadline()`). */
    private const int MAX_REACH = 45;

    /** How far an estimated amount can be from the amount of the bank, as a share of the estimate. */
    private const float ESTIMATE_SHARE = 0.2;

    /** The months back a late due date is looked for. */
    private const int MONTHS_BACK = 12;

    /** The shortest word of a name that counts when looking for the name in a text. */
    private const int MIN_WORD = 3;

    /** The words of a company name that tell nothing about who it is (`S.p.A.` once normalised is `SPA`). */
    private const array LEGAL = ['SPA', 'SRL', 'SRLS', 'SNC', 'SAS', 'SAPA', 'SCARL', 'SCRL', 'COOP', 'ONLUS', 'SOC', 'SOCIETA', 'LTD', 'INC', 'GMBH'];

    /** The words a bank writes on a transfer between two accounts of the same person. */
    private const array TRANSFER_WORDS = ['GIROCONTO', 'POSTAGIRO', 'GIRO DA', 'GIRO A'];

    public function __construct(private BankGroups $groups, private BankFees $fees, private Deadlines $deadlines, private Occurrences $occurrences) {}

    /**
     * The proposal of each movement of a block, with a fixed number of queries whatever the size of the block. A
     * candidate (not a transfer) that is the best of more than one movement goes to the one with the closest date
     * (then the most points, then the lowest id); the others take their next one.
     *
     * @param  Collection<int, BankTransaction>  $rows  the movements of the block
     * @return array<int, BankProposalData> by the id of the movement; a movement with no proposal is left out
     */
    public function for(Collection $rows): array
    {
        $candidates = $this->candidatesOf($rows);

        return $this->assign($candidates);
    }

    /**
     * Every candidate of one movement, the best first, without looking at the other movements: what `AcceptBankProposal`
     * checks a key against.
     *
     * @param  BankTransaction  $transaction  the movement
     * @return list<BankProposalData>
     */
    public function candidates(BankTransaction $transaction): array
    {
        $found = $this->candidatesOf(new Collection([$transaction]))[$transaction->id] ?? [];

        return array_map(static fn (array $candidate): BankProposalData => $candidate['proposal'], $found);
    }

    /**
     * The candidates of each movement that can have one, the best first.
     *
     * @param  Collection<int, BankTransaction>  $rows  the movements
     * @return array<int, list<array{proposal: BankProposalData, points: int, days: int}>> by the id of the movement
     */
    private function candidatesOf(Collection $rows): array
    {
        $rows = $this->eligible($rows);

        if ($rows->isEmpty()) {
            return [];
        }

        $fees = $this->fees->pairs($rows);
        $amounts = [];

        foreach ($rows as $row) {
            $fee = $fees[$row->id] ?? null;
            $amounts[$row->id] = $this->cents($row->amount) + ($fee instanceof BankTransaction ? $this->cents($fee->amount) : 0);
        }

        $context = $this->context($rows, $amounts);
        $found = [];

        foreach ($rows as $row) {
            $text = BankMatch::normalize($row->text.' '.$row->counterparty);
            $list = [
                ...$this->recorded($row, $amounts[$row->id], $text, $context),
                ...$this->dueDates($row, $amounts[$row->id], $fees[$row->id] ?? null, $text, $context),
                ...$this->transfers($row, $text, $context),
            ];
            $list = array_values(array_filter($list, fn (array $candidate): bool => ! $this->rejected($row, $candidate['proposal']->key)));

            if ($list !== []) {
                // The most points first; on a tie the closest date, so the order is stable
                usort($list, static fn (array $a, array $b): int => [$b['points'], $a['days']] <=> [$a['points'], $b['days']]);
                $found[$row->id] = $list;
            }
        }

        return $found;
    }

    /**
     * The movements that can have a proposal: booked, in euros, still to sort, not a commission and in no group.
     *
     * @param  Collection<int, BankTransaction>  $rows  the movements
     * @return Collection<int, BankTransaction>
     */
    private function eligible(Collection $rows): Collection
    {
        $rows = $rows->filter(static fn (BankTransaction $row): bool => $row->status === BankTransactionStatus::Booked
            && $row->booked_on instanceof CarbonImmutable
            && $row->currency === 'EUR'
            && $row->expense_id === null && $row->income_id === null
            && $row->ignored_at === null && $row->transfer_account_id === null
            && ! BankText::isFee($row->text))->values();

        if ($rows->isEmpty()) {
            return $rows;
        }

        $grouped = [];

        foreach ($this->groups->all() as $group) {
            foreach ($group->transactions as $member) {
                $grouped[$member->id] = true;
            }
        }

        return $rows->reject(static fn (BankTransaction $row): bool => isset($grouped[$row->id]))->values();
    }

    /**
     * What the candidates of a block are made from, read once for all its movements.
     *
     * @param  Collection<int, BankTransaction>  $rows  the movements, booked
     * @param  array<int, int>  $amounts  the amount of each movement in cents, without sign, its commission included
     * @return array{expenses: Collection<int, Expense>, incomes: Collection<int, Income>, deadlines: array<string, list<Deadline>>, planned: list<PlannedExpense|PlannedIncome>, accounts: Collection<int, FinancialAccount>, halves: Collection<int, BankTransaction>}
     */
    private function context(Collection $rows, array $amounts): array
    {
        $days = $rows->map(static fn (BankTransaction $row): string => $row->booked_on?->toDateString() ?? '')->sort()->values();
        $first = CarbonImmutable::parse((string) $days->first());
        $last = CarbonImmutable::parse((string) $days->last());
        $cents = array_values(array_unique($amounts));
        // The movements with their own sign, for the opposite halves of the transfers
        $signed = array_values(array_unique($rows->map(fn (BankTransaction $row): int => -$this->signedCents($row->amount))->all()));
        $window = [$first->subDays(self::RECORDED_DAYS)->toDateString(), $last->addDays(self::RECORDED_DAYS)->toDateString()];

        $expenses = Expense::query()->with('organization')
            ->whereBetween('date', $window)
            ->whereIn(DB::raw('round(amount * 100)'), $cents)
            ->whereNotIn('id', BankTransaction::query()->withoutFees()->whereNotNull('expense_id')->select('expense_id'))
            ->get();
        $incomes = Income::query()->with('organization')
            ->whereBetween('date', $window)
            ->whereIn(DB::raw('round(amount * 100)'), $cents)
            ->whereNotIn('id', BankTransaction::query()->withoutFees()->whereNotNull('income_id')->select('income_id'))
            ->get();

        /** @var Builder<PlannedExpense> $expenseQuery */
        $expenseQuery = PlannedExpense::query();
        /** @var Builder<PlannedIncome> $incomeQuery */
        $incomeQuery = PlannedIncome::query();
        /** @var list<PlannedExpense|PlannedIncome> $planned */
        $planned = [...$this->open($expenseQuery, $first)->get()->all(), ...$this->open($incomeQuery, $first)->get()->all()];

        $recurring = array_values(array_filter($planned, static fn (PlannedExpense|PlannedIncome $item): bool => Schedule::of($item)->recurring));
        $once = array_values(array_filter($planned, static fn (PlannedExpense|PlannedIncome $item): bool => ! Schedule::of($item)->recurring));
        $deadlines = [];
        $found = [
            ...($recurring === [] ? [] : $this->deadlines->between($recurring, $first->subMonthsNoOverflow(self::MONTHS_BACK), $last->addDays(self::MAX_REACH))),
            ...($once === [] ? [] : $this->deadlines->between($once, CarbonImmutable::parse('1900-01-01'), CarbonImmutable::parse('2999-12-31'))),
        ];

        foreach ($found as $deadline) {
            $deadlines[$this->plannedKey($deadline->planned)][] = $deadline;
        }

        return [
            'expenses' => $expenses,
            'incomes' => $incomes,
            'deadlines' => $deadlines,
            'planned' => $planned,
            'accounts' => FinancialAccount::query()->where('status', FinancialAccountStatus::Active)->orderBy('id')->get(),
            'halves' => BankTransaction::query()->unsorted()
                ->whereBetween('booked_on', [$first->subDays(self::TRANSFER_DAYS)->toDateString(), $last->addDays(self::TRANSFER_DAYS)->toDateString()])
                ->whereIn(DB::raw('round(amount * 100)'), $signed)
                ->orderBy('id')
                ->get(),
        ];
    }

    /**
     * The planned items that have not ended before a day, with what the due dates and the points read.
     *
     * @template T of PlannedExpense|PlannedIncome
     *
     * @param  Builder<T>  $query  the planned expenses or incomes of the family
     * @param  CarbonImmutable  $first  the first day of the block
     * @return Builder<T>
     */
    private function open(Builder $query, CarbonImmutable $first): Builder
    {
        return $query->with(['organization', 'exceptions'])
            ->where(static fn (Builder $query): Builder => $query->whereNull('end_date')->orWhere('end_date', '>=', $first->toDateString()))
            ->orderBy('id');
    }

    /**
     * The expenses (for money out) or incomes (for money in) already recorded with the amount of the movement, dated
     * within seven days, on its account or on none, and not linked to another movement of the bank.
     *
     * @param  BankTransaction  $row  the movement
     * @param  int  $cents  its amount in cents, its commission included
     * @param  string  $text  its text and counterparty, normalised
     * @param  array{expenses: Collection<int, Expense>, incomes: Collection<int, Income>}  $context  what the block read
     * @return list<array{proposal: BankProposalData, points: int, days: int}>
     */
    private function recorded(BankTransaction $row, int $cents, string $text, array $context): array
    {
        /** @var Collection<int, Expense|Income> $movements */
        $movements = $row->kind() === 'expense' ? $context['expenses'] : $context['incomes'];
        $found = [];

        foreach ($movements as $movement) {
            $date = $movement->date;

            if (! $date instanceof CarbonImmutable || $this->cents((float) $movement->amount) !== $cents
                || ($movement->financial_account_id !== null && $movement->financial_account_id !== $row->financial_account_id)) {
                continue;
            }

            $days = $this->days($row, $date);

            if ($days > self::RECORDED_DAYS) {
                continue;
            }

            $isExpense = $movement instanceof Expense;
            $found[] = [
                'proposal' => new BankProposalData(
                    ($isExpense ? 'expense:' : 'income:').$movement->id,
                    'movement',
                    $movement->label(),
                    $date->toDateString(),
                    round($cents / 100, 2),
                    true,
                ),
                'points' => 4 + $this->datePoints($days) + $this->namePoints($movement->organization, $text),
                'days' => $days,
            ];
        }

        return $found;
    }

    /**
     * The unpaid due dates the movement can pay: of each planned item of its kind that has not ended, the due date
     * `BankMatcher::deadline()` would choose (the closest within half a period, at most 45 days, and none when that
     * one is paid; else the oldest late one), with the expected amount equal to the movement or, for an estimated
     * one, within 20%. A movement that the person declined pays no due date from here.
     *
     * @param  BankTransaction  $row  the movement
     * @param  int  $cents  its amount in cents, its commission included
     * @param  BankTransaction|null  $fee  its commission, when the bank wrote one
     * @param  string  $text  its text and counterparty, normalised
     * @param  array{deadlines: array<string, list<Deadline>>, planned: list<PlannedExpense|PlannedIncome>}  $context  what the block read
     * @return list<array{proposal: BankProposalData, points: int, days: int}>
     */
    private function dueDates(BankTransaction $row, int $cents, ?BankTransaction $fee, string $text, array $context): array
    {
        if ($row->declined_at !== null || ! $row->booked_on instanceof CarbonImmutable) {
            return [];
        }

        $day = $row->booked_on->toDateString();
        $found = [];

        foreach ($context['planned'] as $planned) {
            if (($planned instanceof PlannedExpense) !== ($row->kind() === 'expense')
                || ($planned->end_date !== null && $planned->end_date->toDateString() < $day)) {
                continue;
            }

            $deadline = $this->dueDateOf($row, $planned, $context['deadlines'][$this->plannedKey($planned)] ?? []);

            if (! $deadline instanceof Deadline) {
                continue;
            }

            $amountPoints = $this->amountPoints($deadline, $cents, $fee instanceof BankTransaction);

            if ($amountPoints === 0) {
                continue;
            }

            $occurrence = $deadline->occurrence;
            $days = $this->days($row, $occurrence->date);
            $isExpense = $planned instanceof PlannedExpense;
            $found[] = [
                'proposal' => new BankProposalData(
                    ($isExpense ? 'planned_expense:' : 'planned_income:').$planned->id.':'.$occurrence->originalDate->toDateString(),
                    'deadline',
                    PaymentTitle::for($planned->displayTitle(), $occurrence->originalDate),
                    $occurrence->date->toDateString(),
                    round($occurrence->amount + ($isExpense ? $occurrence->fee : 0.0), 2),
                    false,
                ),
                'points' => $amountPoints + $this->datePoints($days) + $this->namePoints($planned->organization, $text),
                'days' => $days,
            ];
        }

        return $found;
    }

    /**
     * The due date of a planned item a movement settles, as `BankMatcher::deadline()` chooses it, from the due dates
     * the block read; null when that one is paid or there is none.
     *
     * @param  BankTransaction  $row  the movement, booked
     * @param  PlannedExpense|PlannedIncome  $planned  the planned item
     * @param  list<Deadline>  $deadlines  its due dates, in order of date, the skipped ones left out
     */
    private function dueDateOf(BankTransaction $row, PlannedExpense|PlannedIncome $planned, array $deadlines): ?Deadline
    {
        $day = $row->booked_on;

        if (! $day instanceof CarbonImmutable || $deadlines === []) {
            return null;
        }

        $schedule = Schedule::of($planned);

        if (! $schedule->recurring) {
            return array_find($deadlines, static fn (Deadline $deadline): bool => ! $deadline->isPaid());
        }

        $reach = min(self::MAX_REACH, (int) floor($this->occurrences->stepDays($schedule) / 2));
        $near = array_values(array_filter($deadlines, fn (Deadline $deadline): bool => $this->days($row, $deadline->occurrence->date) <= $reach));
        // In order of date: on a tie the earlier due date stays first
        usort($near, fn (Deadline $a, Deadline $b): int => $this->days($row, $a->occurrence->date) <=> $this->days($row, $b->occurrence->date));

        if ($near !== []) {
            return $near[0]->isPaid() ? null : $near[0];
        }

        $since = $day->subMonthsNoOverflow(self::MONTHS_BACK)->toDateString();

        return array_find($deadlines, static fn (Deadline $deadline): bool => ! $deadline->isPaid()
            && $deadline->occurrence->date->toDateString() < $day->toDateString()
            && $deadline->occurrence->date->toDateString() >= $since);
    }

    /**
     * The points of the amount of a due date: 4 when the expected amount (with the fee of a planned expense, or
     * without it when the commission may come apart) is the amount of the movement to the cent, 1 for an estimated
     * one within 20%, else 0 (not a candidate).
     *
     * @param  Deadline  $deadline  the due date
     * @param  int  $cents  the amount of the movement in cents, its commission included
     * @param  bool  $withFee  the commission of the movement is in `$cents`
     */
    private function amountPoints(Deadline $deadline, int $cents, bool $withFee): int
    {
        $occurrence = $deadline->occurrence;
        $amount = (int) round($occurrence->amount * 100);
        $fee = $deadline->planned instanceof PlannedExpense ? (int) round($occurrence->fee * 100) : 0;
        $expected = $fee > 0 && ! $withFee ? [$amount + $fee, $amount] : [$amount + $fee];

        if (in_array($cents, $expected, true)) {
            return 4;
        }

        $estimate = $amount + $fee;

        return $occurrence->estimated && $estimate > 0 && abs($cents - $estimate) <= $estimate * self::ESTIMATE_SHARE ? 1 : 0;
    }

    /**
     * The accounts of the family the movement may be a transfer to or from: the one with the opposite half (the same
     * amount the other way within three days, still to sort), the ones whose name or last four digits are in the text,
     * and, when the text has a word of the transfers and the family has only one other account, that one.
     *
     * @param  BankTransaction  $row  the movement
     * @param  string  $text  its text and counterparty, normalised
     * @param  array{accounts: Collection<int, FinancialAccount>, halves: Collection<int, BankTransaction>}  $context  what the block read
     * @return list<array{proposal: BankProposalData, points: int, days: int}>
     */
    private function transfers(BankTransaction $row, string $text, array $context): array
    {
        $others = $context['accounts']->filter(static fn (FinancialAccount $account): bool => $account->id !== $row->financial_account_id)->values();
        $giro = array_any(self::TRANSFER_WORDS, static fn (string $word): bool => preg_match('/(?:^| )'.$word.'(?: |$)/', $text) === 1);
        $cents = $this->signedCents($row->amount);
        $found = [];

        foreach ($others as $account) {
            $half = $context['halves']
                ->filter(fn (BankTransaction $other): bool => $other->financial_account_id === $account->id
                    && $other->id !== $row->id
                    && $this->signedCents($other->amount) === -$cents
                    && $other->booked_on instanceof CarbonImmutable
                    && $this->days($row, $other->booked_on) <= self::TRANSFER_DAYS)
                ->sortBy(fn (BankTransaction $other): int => $other->booked_on instanceof CarbonImmutable ? $this->days($row, $other->booked_on) : PHP_INT_MAX)
                ->first();
            $named = $this->names($account, $text);
            $points = ($half instanceof BankTransaction ? 4 : 0) + ($named ? 2 : 0) + ($giro ? 1 : 0);

            // A word of the transfers alone says nothing about which account, unless there is only one
            if (! $half instanceof BankTransaction && ! $named && ! ($giro && $others->count() === 1)) {
                continue;
            }

            $days = $half instanceof BankTransaction && $half->booked_on instanceof CarbonImmutable ? $this->days($row, $half->booked_on) : self::TRANSFER_DAYS + 1;
            $found[] = [
                'proposal' => new BankProposalData('transfer:'.$account->id, 'transfer', $account->name, null, null, false),
                'points' => $points + ($half instanceof BankTransaction ? $this->datePoints($days) : 0),
                'days' => $days,
            ];
        }

        return $found;
    }

    /**
     * The proposal of each movement: its best candidate when no other has the same points. A candidate that is not
     * a transfer is given to one movement only: when it is the best of more than one, the closest date wins (then the
     * most points, then the lowest id) and the others are looked at again without it.
     *
     * @param  array<int, list<array{proposal: BankProposalData, points: int, days: int}>>  $candidates  by the id of the movement, the best first
     * @return array<int, BankProposalData>
     */
    private function assign(array $candidates): array
    {
        $taken = [];
        $result = [];
        $pending = $candidates;

        while ($pending !== []) {
            $claims = [];

            foreach ($pending as $id => $list) {
                $list = array_values(array_filter($list, static fn (array $candidate): bool => ! isset($taken[$candidate['proposal']->key])));
                $pending[$id] = $list;

                if ($list === []) {
                    unset($pending[$id]);

                    continue;
                }

                // A tie at the top waits: another movement may take one of the two
                if (isset($list[1]) && $list[1]['points'] === $list[0]['points']) {
                    continue;
                }

                if ($list[0]['proposal']->type === 'transfer') {
                    $result[$id] = $list[0]['proposal'];
                    unset($pending[$id]);

                    continue;
                }

                $claims[$list[0]['proposal']->key][] = ['id' => $id, 'points' => $list[0]['points'], 'days' => $list[0]['days']];
            }

            if ($claims === []) {
                break;
            }

            foreach ($claims as $key => $claimants) {
                usort($claimants, static fn (array $a, array $b): int => [$a['days'], $b['points'], $a['id']] <=> [$b['days'], $a['points'], $b['id']]);
                $winner = $claimants[0]['id'];
                $result[$winner] = $pending[$winner][0]['proposal'];
                $taken[(string) $key] = true;
                unset($pending[$winner]);
            }
        }

        return $result;
    }

    /**
     * Whether the person said no to a key for this movement: as a value of the list (`expense:812`) or as a key of it
     * (`group:<key> => true`).
     *
     * @param  BankTransaction  $row  the movement
     * @param  string  $key  the key of the candidate
     */
    private function rejected(BankTransaction $row, string $key): bool
    {
        $rejected = $row->rejected_proposals ?? [];

        return array_key_exists($key, $rejected) || in_array($key, $rejected, true);
    }

    /**
     * Whether the text names the account: its name without the short words, or its last four digits alone.
     *
     * @param  FinancialAccount  $account  the account
     * @param  string  $text  the text and counterparty of the movement, normalised
     */
    private function names(FinancialAccount $account, string $text): bool
    {
        $digits = (string) $account->last_four_digits;

        return $this->nameIn($account->name, $text)
            || ($digits !== '' && preg_match('/(?<!\d)'.preg_quote($digits, '/').'(?!\d)/', $text) === 1);
    }

    /**
     * 2 points when the name of the organization is in the text, else 0.
     *
     * @param  Organization|null  $organization  the organization of the candidate
     * @param  string  $text  the text and counterparty of the movement, normalised
     */
    private function namePoints(?Organization $organization, string $text): int
    {
        return $organization instanceof Organization && $this->nameIn($organization->name, $text) ? 2 : 0;
    }

    /**
     * Whether a name is in a text, as whole words: without the words shorter than three letters and the legal forms
     * (`Famila S.p.A.` is found as `FAMILA`).
     *
     * @param  string  $name  the name
     * @param  string  $text  the text, normalised
     */
    private function nameIn(string $name, string $text): bool
    {
        $words = array_filter(
            explode(' ', BankMatch::normalize((string) preg_replace('/(?<=\p{L})\.(?=\p{L})|\.$/u', '', $name))),
            static fn (string $word): bool => mb_strlen($word) >= self::MIN_WORD && ! in_array(str_replace('.', '', $word), self::LEGAL, true),
        );

        if ($words === []) {
            return false;
        }

        return preg_match('/(?:^|[^A-Z0-9])'.preg_quote(implode(' ', $words), '/').'(?:[^A-Z0-9]|$)/', $text) === 1;
    }

    /**
     * The points of the date: 3 the same day, 2 within two days, 1 within seven, else 0.
     *
     * @param  int  $days  the whole days between the movement and the candidate
     */
    private function datePoints(int $days): int
    {
        return match (true) {
            $days === 0 => 3,
            $days <= 2 => 2,
            $days <= self::RECORDED_DAYS => 1,
            default => 0,
        };
    }

    /**
     * The whole days between the day of the movement and a date, without sign.
     *
     * @param  BankTransaction  $row  the movement, booked
     * @param  CarbonImmutable  $date  the date
     */
    private function days(BankTransaction $row, CarbonImmutable $date): int
    {
        $day = CarbonImmutable::parse($row->booked_on?->toDateString() ?? 'today', 'UTC');

        return (int) abs(round($day->diffInDays(CarbonImmutable::parse($date->toDateString(), 'UTC'), false)));
    }

    /**
     * The key of a planned item among the due dates read.
     *
     * @param  PlannedExpense|PlannedIncome  $planned  the planned item
     */
    private function plannedKey(PlannedExpense|PlannedIncome $planned): string
    {
        return ($planned instanceof PlannedExpense ? 'expense:' : 'income:').$planned->id;
    }

    /**
     * An amount in cents, without the sign.
     *
     * @param  numeric  $amount  the amount
     */
    private function cents(mixed $amount): int
    {
        return (int) round(abs((float) $amount) * 100);
    }

    /**
     * An amount in cents, with the sign.
     *
     * @param  numeric  $amount  the amount
     */
    private function signedCents(mixed $amount): int
    {
        return (int) round((float) $amount * 100);
    }
}

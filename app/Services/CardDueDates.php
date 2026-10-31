<?php

declare(strict_types=1);

namespace App\Services;

use App\Cards\CardType;
use App\Models\Employment;
use App\Models\Enrollment;
use App\Models\Home;
use App\Models\InsurancePolicy;
use App\Models\Loan;
use App\Models\PlannedExpense;
use App\Models\PlannedIncome;
use App\Models\Subscription;
use App\Models\Utility;
use App\Models\Vehicle;
use App\Support\ModelId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * The open due date of each card of a type, for the rows of *An area*. The due dates come from {@see Deadlines}.
 */
final readonly class CardDueDates
{
    /** How many days ahead an open due date is shown as *To pay*. */
    public const int DUE_DAYS = 30;

    /** The models of the cards a planned expense refers to with its pair `card_type`, `card_id`. */
    private const array PLANNED_CARDS = [Utility::class, InsurancePolicy::class, Loan::class, Enrollment::class, Home::class, Vehicle::class, Subscription::class];

    public function __construct(private Deadlines $deadlines) {}

    /**
     * For the rows of *An area*: the open due date of each card that is late or falls in the next 30 days, with one
     * query of planned items per type, whatever the number of cards. The oldest one wins.
     *
     * @param  CardType  $type  the type of the cards
     * @param  iterable<Model>  $cards  the cards of that type
     * @param  CarbonImmutable  $today  today in Rome
     * @return array<int, array{amount: float, late: bool}> by id of the card; the cards without one are missing
     */
    public function of(CardType $type, iterable $cards, CarbonImmutable $today): array
    {
        $ids = [];

        foreach ($cards as $card) {
            $ids[] = ModelId::of($card);
        }

        $income = $type->model() === Employment::class;

        if ($ids === [] || (! $income && ! in_array($type->model(), self::PLANNED_CARDS, true))) {
            return [];
        }

        $column = $income ? 'employment_id' : 'card_id';
        $planned = $income
            ? PlannedIncome::query()->whereIn($column, $ids)->get()->all()
            : PlannedExpense::query()->where('card_type', $type->type())->whereIn($column, $ids)->get()->all();
        $due = [];

        foreach ($this->deadlines->unpaidUntil($planned, $today->addDays(self::DUE_DAYS), $today) as $deadline) {
            $id = $deadline->planned->getAttribute($column);

            if (is_int($id) && ! isset($due[$id])) {
                $due[$id] = ['amount' => $deadline->occurrence->amount, 'late' => $deadline->isLate($today)];
            }
        }

        return $due;
    }
}

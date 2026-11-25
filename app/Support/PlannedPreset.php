<?php

declare(strict_types=1);

namespace App\Support;

use App\Cards\CardTypes;
use App\Cards\DeadlineSource;
use App\Cards\DeadlineSources;
use App\Enums\EnrollmentType;
use App\Enums\InsurancePolicyType;
use App\Models\BankTransaction;
use App\Models\Employment;
use App\Models\Enrollment;
use App\Models\ExpenseCategory;
use App\Models\InsurancePolicy;
use App\Models\Loan;
use App\Models\PlannedExpense;
use App\Models\PlannedIncome;
use App\Models\Subscription;
use App\Models\Utility;
use App\Support\Banks\BankMatch;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;

/**
 * The starting values of a new planned expense that comes from a card (*Add* in *Planned expenses* of a card):
 * the card is linked and the fields it knows are filled in, with the category of its type. The ids are text, as the options of the form are.
 */
final class PlannedPreset
{
    /** Every how many years a deadline with an expense of its own is paid; one year when it is not here. */
    private const array DEADLINE_INTERVALS = ['vehicles.inspection_due_date' => 2];

    /**
     * The starting values of a new planned expense or income without a card.
     *
     * @param  string  $kind  `expense` or `income`
     * @return array<string, mixed>
     */
    public static function defaults(string $kind): array
    {
        return [
            'title' => '',
            'amount' => null,
            'is_estimated' => false,
            'notes' => null,
            'start_date' => null,
            'end_date' => null,
            'is_recurring' => true,
            'interval' => 1,
            'interval_unit' => 'month',
            'organization_id' => null,
            'financial_account_id' => null,
            'person_id' => null,
            'bank_match' => null,
            ...$kind === 'expense' ? ['fee_amount' => null, 'category_id' => null, 'card' => null, 'deadline_field' => null] : ['type' => 'salary', 'employment_id' => null],
        ];
    }

    /**
     * The values of the form of a saved planned expense or income.
     *
     * @param  PlannedExpense|PlannedIncome  $planned  the planned item
     * @return array<string, mixed>
     */
    public static function of(PlannedExpense|PlannedIncome $planned): array
    {
        $values = [
            'title' => $planned instanceof PlannedExpense ? $planned->title : $planned->title ?? '',
            'amount' => $planned->amount === null ? null : (float) $planned->amount,
            'is_estimated' => (bool) $planned->is_estimated,
            'notes' => $planned->notes,
            'start_date' => $planned->start_date?->toDateString(),
            'end_date' => $planned->end_date?->toDateString(),
            'is_recurring' => (bool) $planned->is_recurring,
            'interval' => max(1, (int) $planned->interval),
            'interval_unit' => $planned->interval_unit->value,
            'organization_id' => $planned->organization_id,
            'financial_account_id' => $planned->financial_account_id,
            'person_id' => $planned->person_id,
            'bank_match' => $planned->bank_match,
        ];

        if ($planned instanceof PlannedIncome) {
            return self::asText([...$values, 'type' => $planned->type->value, 'employment_id' => $planned->employment_id]);
        }

        $card = PlannedKind::card($planned);

        return self::asText([
            ...$values,
            'fee_amount' => $planned->fee_amount === null ? null : (float) $planned->fee_amount,
            'category_id' => $planned->category_id,
            'deadline_field' => $planned->deadline_field,
            'card' => $card instanceof Model ? CardTypes::for($card)->type().':'.ModelId::of($card) : null,
        ]);
    }

    /**
     * The starting values of a new planned income that comes from the employment of `?card=employment:id`: the
     * employment, its person and its employer.
     *
     * @param  mixed  $link  the `card` of the query
     * @return array<string, mixed> nothing when the text is not an employment
     *
     * @throws ModelNotFoundException if the family has no such employment (Laravel answers 404)
     */
    public static function incomeFromQuery(mixed $link): array
    {
        $card = PresetLink::from($link);

        if ($card === null || $card['type'] !== 'employment') {
            return [];
        }

        $employment = Employment::query()->findOrFail($card['id']);

        return self::asText(array_filter([
            'employment_id' => $employment->id,
            'person_id' => $employment->person_id,
            'organization_id' => $employment->organization_id,
        ], static fn (?int $value): bool => $value !== null));
    }

    /**
     * The starting values of a new planned expense that comes from the card of `?card=type:id`: what the card knows
     * and the first main category (by order, then name) whose expenses refer to cards of its type, when there is one.
     *
     * @param  mixed  $link  the `card` of the query
     * @param  mixed  $deadline  the `deadline` of the query: the deadline of the card the expense pays (`vehicles.inspection_due_date`); the title, the first due date and how often come from it
     * @return array<string, mixed> nothing when the text is not a card of the family
     */
    public static function fromQuery(mixed $link, mixed $deadline = null): array
    {
        $card = PresetLink::from($link);

        if ($card === null) {
            return [];
        }

        $category = ExpenseCategory::firstOfCardType($card['type']);
        $model = CardTypes::findOrFail($card['type'])->findOrFail($card['id']);

        return [
            ...self::from($model),
            ...self::forDeadline($model, $deadline),
            ...is_int($category) ? ['category_id' => (string) $category] : [],
        ];
    }

    /**
     * What a deadline with an expense of its own tells a new planned expense: its name as the title, the date of the
     * card as the first due date, every year (every 2 years for the inspection) and an estimated amount to write.
     *
     * @param  Model  $card  the card
     * @param  mixed  $deadline  the key of the deadline
     * @return array<string, mixed> nothing when the deadline is not one of the card's with an expense of its own
     */
    private static function forDeadline(Model $card, mixed $deadline): array
    {
        $source = is_string($deadline) ? collect(DeadlineSources::ownExpenseFor($card::class))->first(static fn (DeadlineSource $source): bool => $source->field === $deadline) : null;
        $date = $source instanceof DeadlineSource ? $card->getAttribute($source->column) : null;

        if (! $source instanceof DeadlineSource) {
            return [];
        }

        return [
            'title' => (string) $source->label,
            ...$date instanceof CarbonInterface ? ['start_date' => $date->toDateString()] : [],
            'interval' => self::DEADLINE_INTERVALS[$source->field] ?? 1,
            'interval_unit' => 'year',
            'is_estimated' => true,
            'deadline_field' => $source->field,
        ];
    }

    /**
     * The starting values of a new planned expense or income that comes from a bank transaction whose text comes back
     * every month: the title (who was paid, else the text), the amount of the latest one (estimated when the amounts
     * differ), the day of the oldest one as the first due date, every month, the account and the rule that finds it.
     *
     * @param  BankTransaction  $transaction  The transaction the person started from.
     * @param  list<BankTransaction>  $chain  The three transactions of the months in a row, oldest first.
     * @return array<string, mixed>
     */
    public static function fromBank(BankTransaction $transaction, array $chain): array
    {
        $cents = array_map(static fn (BankTransaction $row): int => (int) round(abs((float) $row->amount) * 100), $chain);

        return self::asText([
            'title' => filled($transaction->counterparty) ? $transaction->counterparty : $transaction->text,
            'amount' => round(abs((float) (collect($chain)->last() ?? $transaction)->amount), 2),
            'is_estimated' => count(array_unique($cents)) > 1,
            'start_date' => (collect($chain)->first() ?? $transaction)->booked_on?->toDateString(),
            'is_recurring' => true,
            'interval' => 1,
            'interval_unit' => 'month',
            'financial_account_id' => $transaction->financial_account_id,
            'bank_match' => BankMatch::fromText($transaction->text),
        ]);
    }

    /**
     * The values with the ids as text, as the options of the form have them (FormKit compares them strictly).
     *
     * @param  array<string, mixed>  $values  the values
     * @return array<string, mixed>
     */
    private static function asText(array $values): array
    {
        foreach ($values as $column => $value) {
            if (is_int($value) && str_ends_with($column, '_id')) {
                $values[$column] = (string) $value;
            }
        }

        return $values;
    }

    /**
     * The values of a planned expense that starts from a card.
     *
     * @param  Model  $card  one of the cards a planned expense can refer to
     * @return array<string, mixed> `card` (`type:id`), `is_recurring`, `interval`, `interval_unit` and what the card knows
     *
     * @throws InvalidArgumentException if the model is not a card
     */
    public static function from(Model $card): array
    {
        $type = CardTypes::for($card)->type();
        $known = match (true) {
            $card instanceof Loan => [
                'title' => $card->name,
                'amount' => $card->installment_amount === null ? null : (float) $card->installment_amount,
                'organization_id' => $card->organization_id,
                'financial_account_id' => $card->financial_account_id,
                'start_date' => $card->start_date,
                'end_date' => $card->end_date,
                'person_id' => $card->person_id,
            ],
            $card instanceof InsurancePolicy => [
                'title' => self::policyTitle($card->type),
                'organization_id' => $card->organization_id,
                'start_date' => $card->start_date,
                'person_id' => $card->person_id,
            ],
            $card instanceof Utility => [
                'title' => $card->type->label(),
                'organization_id' => $card->organization_id,
                'start_date' => $card->activation_date,
                'person_id' => $card->person_id,
            ],
            $card instanceof Subscription => [
                'title' => $card->name,
                'organization_id' => $card->organization_id,
                'financial_account_id' => $card->financial_account_id,
                'start_date' => $card->renewal_date ?? $card->activation_date,
                'person_id' => $card->person_id,
            ],
            $card instanceof Enrollment => [
                'title' => self::enrollmentTitle($card->type),
                'organization_id' => $card->organization_id,
                'person_id' => $card->person_id,
            ],
            default => ['person_id' => $card->getAttribute('person_id')],
        };

        return [
            ...array_map(static fn (mixed $value): mixed => match (true) {
                $value instanceof CarbonInterface => $value->toDateString(),
                is_int($value) => (string) $value,
                default => $value,
            }, array_filter($known, static fn (mixed $value): bool => $value !== null)),
            'card' => $type.':'.ModelId::of($card),
            'is_recurring' => true,
            'interval' => 1,
            'interval_unit' => $card instanceof InsurancePolicy ? 'year' : 'month',
        ];
    }

    /**
     * The title of a planned expense for a policy, from its type; also the name of the expiry of a policy in the
     * section *Deadlines* of a vehicle with the Budget off.
     *
     * @param  InsurancePolicyType  $type  the type of the policy
     */
    public static function policyTitle(InsurancePolicyType $type): string
    {
        return match ($type) {
            InsurancePolicyType::Car => __('Car insurance'),
            InsurancePolicyType::Life => __('Life insurance'),
            InsurancePolicyType::Home => __('Home insurance'),
            InsurancePolicyType::Health => __('Health insurance'),
            InsurancePolicyType::Accident => __('Accident insurance'),
            InsurancePolicyType::Fund, InsurancePolicyType::Other => __('Insurance'),
        };
    }

    /**
     * The title of a planned expense for an enrollment, from its type.
     *
     * @param  EnrollmentType  $type  the type of the enrollment
     */
    private static function enrollmentTitle(EnrollmentType $type): string
    {
        return match ($type) {
            EnrollmentType::School => __('School fee'),
            EnrollmentType::Sport => __('Sport fee'),
            EnrollmentType::Course => __('Course fee'),
            EnrollmentType::Other => __('Fee'),
        };
    }
}

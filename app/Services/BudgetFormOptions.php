<?php

declare(strict_types=1);

namespace App\Services;

use App\Cards\CardTypes;
use App\Data\OptionData;
use App\Data\PlannedChoiceData;
use App\Enums\FinancialAccountStatus;
use App\Enums\IncomeType;
use App\Models\Employment;
use App\Models\FinancialAccount;
use App\Models\Organization;
use App\Models\Person;
use App\Models\PlannedExpense;
use App\Models\PlannedIncome;
use App\Support\ModelId;
use App\Support\PlannedPreset;
use Illuminate\Database\Eloquent\Model;

/**
 * The choices of the forms of the budget: the accounts, the categories, the organizations, the people, the cards
 * a planned expense can refer to, the employments and the types of an income. Always the ones of the current family.
 */
final class BudgetFormOptions
{
    /**
     * The choices of the form of a planned expense or income; the lists that do not belong to the kind are empty.
     *
     * @param  string  $kind  `expense` or `income`
     * @return array{accounts: list<OptionData>, categories: list<OptionData>, organizations: list<OptionData>, people: list<OptionData>, cards: list<OptionData>, employments: list<OptionData>, income_types: list<OptionData>}
     */
    public static function for(string $kind): array
    {
        $expense = $kind === 'expense';

        return [
            'accounts' => self::accounts(),
            'categories' => $expense ? self::categories() : [],
            'organizations' => self::organizations(),
            'people' => self::people(),
            'cards' => $expense ? self::cards() : [],
            'employments' => $expense ? [] : self::employments(),
            'income_types' => $expense ? [] : OptionData::list(IncomeType::options()),
        ];
    }

    /**
     * The choices of the form of a movement, an expense or an income: the lists of both kinds, because the first
     * choice of the form is which one it is.
     *
     * @return array{accounts: list<OptionData>, categories: list<OptionData>, organizations: list<OptionData>, people: list<OptionData>, planned: list<PlannedChoiceData>, income_types: list<OptionData>}
     */
    public static function forMovement(): array
    {
        return [
            'accounts' => self::accounts(),
            'categories' => self::categories(),
            'organizations' => self::organizations(),
            'people' => self::people(),
            'planned' => self::planned(),
            'income_types' => OptionData::list(IncomeType::options()),
        ];
    }

    /**
     * The accounts to pay with: the active ones, the default first.
     *
     * @return list<OptionData>
     */
    public static function accounts(): array
    {
        return self::options(
            FinancialAccount::query()->where('status', FinancialAccountStatus::Active)->orderByDesc('is_default')->orderBy('name')->get(),
            static fn (FinancialAccount $account): string => $account->name,
        );
    }

    /**
     * The planned expenses and incomes, by title, each with what a movement takes from it.
     *
     * @return list<PlannedChoiceData>
     */
    private static function planned(): array
    {
        return [
            ...PlannedExpense::query()->orderBy('title')->get()->map(PlannedChoiceData::fromPlanned(...))->all(),
            ...PlannedIncome::query()->orderBy('title')->get()->map(PlannedChoiceData::fromPlanned(...))->all(),
        ];
    }

    /**
     * The expense categories, main ones followed by their subcategories, each with its path («Home › Gas»).
     *
     * @return list<OptionData>
     */
    private static function categories(): array
    {
        $options = [];

        foreach (ExpenseCategoryPaths::all() as $id => $path) {
            $options[] = new OptionData((string) $id, $path);
        }

        return $options;
    }

    /**
     * The organizations, by name.
     *
     * @return list<OptionData>
     */
    private static function organizations(): array
    {
        return self::options(Organization::query()->orderBy('name')->get(), static fn (Organization $organization): string => $organization->name);
    }

    /**
     * The people, by first name.
     *
     * @return list<OptionData>
     */
    private static function people(): array
    {
        return self::options(
            Person::query()->orderBy('first_name')->orderBy('last_name')->get(),
            static fn (Person $person): string => "{$person->first_name} {$person->last_name}",
        );
    }

    /**
     * The cards a planned expense can refer to, by type and name; the value is `type:id`.
     *
     * @return list<OptionData>
     */
    private static function cards(): array
    {
        $options = [];

        foreach (PlannedPreset::CARD_TYPES as $name) {
            $type = CardTypes::findOrFail($name);
            $cards = $type->model()::query()->with($type->listRelations())->get()
                ->map(static fn (Model $card): array => [(string) ModelId::of($card), method_exists($card, 'label') ? (string) $card->label() : ''])
                ->sortBy(1, SORT_NATURAL | SORT_FLAG_CASE);

            foreach ($cards as [$id, $label]) {
                $options[] = new OptionData("{$name}:{$id}", "{$type->label()} · {$label}");
            }
        }

        return $options;
    }

    /**
     * The employments, for the salary of a person.
     *
     * @return list<OptionData>
     */
    private static function employments(): array
    {
        return self::options(Employment::query()->with(['person', 'organization'])->get(), static fn (Employment $employment): string => $employment->label());
    }

    /**
     * Records as options: the id as text (FormKit compares values strictly) and the text of each.
     *
     * @template T of Model
     *
     * @param  iterable<T>  $records  the records, in the order to show
     * @param  callable(T): string  $label  the text of a record
     * @return list<OptionData>
     */
    private static function options(iterable $records, callable $label): array
    {
        $options = [];

        foreach ($records as $record) {
            $options[] = new OptionData((string) ModelId::of($record), $label($record));
        }

        return $options;
    }
}

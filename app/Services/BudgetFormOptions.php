<?php

declare(strict_types=1);

namespace App\Services;

use App\Cards\CardType;
use App\Cards\CardTypes;
use App\Cards\FieldKind;
use App\Data\CardChoiceData;
use App\Data\OptionData;
use App\Data\PlannedChoiceData;
use App\Enums\FinancialAccountStatus;
use App\Enums\IncomeType;
use App\Models\Employment;
use App\Models\ExpenseCategory;
use App\Models\FinancialAccount;
use App\Models\Organization;
use App\Models\Person;
use App\Models\PlannedExpense;
use App\Models\PlannedIncome;
use App\Support\ModelId;
use Illuminate\Database\Eloquent\Model;

/**
 * The choices of the forms of the budget: the accounts, the categories with the card type of each, the organizations,
 * the people, the cards an expense can refer to, the employments and the types of an income. Always the ones of the
 * current family.
 */
final class BudgetFormOptions
{
    /**
     * The choices of the form of a planned expense or income; the lists that do not belong to the kind are empty.
     *
     * @param  string  $kind  `expense` or `income`
     * @return array{accounts: list<OptionData>, categories: list<OptionData>, organizations: list<OptionData>, people: list<OptionData>, cards: list<CardChoiceData>, category_card_types: array<int, string>, card_type_labels: array<string, string>, employments: list<OptionData>, income_types: list<OptionData>}
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
            'category_card_types' => $expense ? self::categoryCardTypes() : [],
            'card_type_labels' => $expense ? self::cardTypeLabels() : [],
            'employments' => $expense ? [] : self::employments(),
            'income_types' => $expense ? [] : OptionData::list(IncomeType::options()),
        ];
    }

    /**
     * The choices of the form of a movement, an expense or an income: the lists of both kinds, because the first
     * choice of the form is which one it is.
     *
     * @return array{accounts: list<OptionData>, categories: list<OptionData>, organizations: list<OptionData>, people: list<OptionData>, planned: list<PlannedChoiceData>, cards: list<CardChoiceData>, category_card_types: array<int, string>, card_type_labels: array<string, string>, employments: list<OptionData>, income_types: list<OptionData>}
     */
    public static function forMovement(): array
    {
        return [
            'accounts' => self::accounts(),
            'categories' => self::categories(),
            'organizations' => self::organizations(),
            'people' => self::people(),
            'planned' => self::planned(),
            'cards' => self::cards(),
            'category_card_types' => self::categoryCardTypes(),
            'card_type_labels' => self::cardTypeLabels(),
            'employments' => self::employments(),
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
     * The cards an expense or a planned expense can refer to, of every type: for each type the open ones by name, then
     * the closed ones. The value is `type:id`, the text «Type · name»; each brings its holder, its organization and
     * the category of its type, which the form fills in when the card is chosen.
     *
     * @return list<CardChoiceData>
     */
    private static function cards(): array
    {
        $options = [];

        foreach (CardTypes::all() as $type) {
            $category = ExpenseCategory::firstOfCardType($type->type());
            $cards = $type->model()::query()->with($type->listRelations())->get()
                ->map(static fn (Model $card): array => [
                    $card,
                    method_exists($card, 'label') ? (string) $card->label() : '',
                    $type->isClosed($card),
                ])
                ->sort(static fn (array $a, array $b): int => ($a[2] <=> $b[2]) ?: strnatcasecmp($a[1], $b[1]));

            foreach ($cards as [$card, $label]) {
                $options[] = new CardChoiceData(
                    value: "{$type->type()}:".ModelId::of($card),
                    label: "{$type->label()} · {$label}",
                    person_id: self::fieldId($type, $card, FieldKind::Person),
                    organization_id: self::fieldId($type, $card, FieldKind::Organization),
                    category_id: $category === null ? null : (string) $category,
                );
            }
        }

        return $options;
    }

    /**
     * The id, as text, in the first field of a kind of a card: its holder or its organization.
     *
     * @param  CardType  $type  the type of the card
     * @param  Model  $card  the card
     * @param  FieldKind  $kind  `person` or `organization`
     * @return string|null null when the type has no such field or the card leaves it empty
     */
    private static function fieldId(CardType $type, Model $card, FieldKind $kind): ?string
    {
        foreach ($type->fields() as $field) {
            if ($field->kind === $kind) {
                $id = $card->getAttribute($field->column);

                return is_int($id) ? (string) $id : null;
            }
        }

        return null;
    }

    /**
     * The card type of each expense category that has one (a subcategory takes the one of its main category), by
     * the id of the category. The page reads it as an object keyed by the id as text, as the options of the
     * categories have it.
     *
     * @return array<int, string>
     */
    private static function categoryCardTypes(): array
    {
        return ExpenseCategory::cardTypes();
    }

    /**
     * The name of each card type, for the label of the field of the card.
     *
     * @return array<string, string>
     */
    private static function cardTypeLabels(): array
    {
        $labels = [];

        foreach (CardTypes::all() as $type) {
            $labels[$type->type()] = $type->label();
        }

        return $labels;
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

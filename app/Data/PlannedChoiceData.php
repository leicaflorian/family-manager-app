<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\PlannedExpense;
use App\Models\PlannedIncome;
use App\Support\PlannedKind;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A planned expense or income in the menu of the form of a movement, with what the form takes from it when it is chosen.
 * The ids are text, as the menus of FormKit compare them.
 */
#[TypeScript]
final class PlannedChoiceData extends Data
{
    /**
     * @param  string  $kind  `expense` or `income`
     * @param  string  $value  the id of the planned item
     * @param  string  $label  its title
     * @param  string|null  $category_id  the category of a planned expense
     * @param  string|null  $card  the card of a planned expense, `type:id`
     * @param  string|null  $organization_id  the organization
     * @param  string|null  $financial_account_id  the account
     * @param  string|null  $person_id  the person
     * @param  string|null  $employment_id  the employment of a planned income
     * @param  string|null  $type  the type of a planned income
     */
    public function __construct(
        public readonly string $kind,
        public readonly string $value,
        public readonly string $label,
        public readonly ?string $category_id,
        public readonly ?string $card,
        public readonly ?string $organization_id,
        public readonly ?string $financial_account_id,
        public readonly ?string $person_id,
        public readonly ?string $employment_id,
        public readonly ?string $type,
    ) {}

    /**
     * The choice of a planned expense or income.
     *
     * @param  PlannedExpense|PlannedIncome  $planned  the planned item
     */
    public static function fromPlanned(PlannedExpense|PlannedIncome $planned): self
    {
        $columns = PlannedKind::paymentColumns($planned);

        return new self(
            kind: $planned instanceof PlannedExpense ? 'expense' : 'income',
            value: (string) $planned->id,
            label: $planned->displayTitle(),
            category_id: self::text($columns['category_id'] ?? null),
            card: isset($columns['card_type'], $columns['card_id']) ? $columns['card_type'].':'.$columns['card_id'] : null,
            organization_id: self::text($columns['organization_id']),
            financial_account_id: self::text($columns['financial_account_id']),
            person_id: self::text($columns['person_id']),
            employment_id: self::text($columns['employment_id'] ?? null),
            type: $columns['type'] ?? null,
        );
    }

    /**
     * An id as text, null when there is none.
     *
     * @param  int|null  $id  the id
     */
    private static function text(?int $id): ?string
    {
        return $id === null ? null : (string) $id;
    }
}

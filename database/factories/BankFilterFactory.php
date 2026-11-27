<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BankFilterAction;
use App\Enums\BankFilterFee;
use App\Models\BankFilter;
use App\Models\Family;
use App\Models\PlannedExpense;
use App\Models\PlannedIncome;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankFilter>
 */
final class BankFilterFactory extends Factory
{
    /**
     * A filter on outgoing payments with the word `edison` that creates the expense.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'family_id' => Family::factory(),
            'name' => 'Edison',
            'contains' => ['edison'],
            'excludes' => [],
            'direction' => 'expense',
            'fee_mode' => BankFilterFee::None,
            'action' => BankFilterAction::Create,
            'title' => 'Bolletta luce',
        ];
    }

    /**
     * A filter that pays the due date of a planned expense (or of the given planned item).
     *
     * @param  PlannedExpense|PlannedIncome|null  $planned  the planned item; a new planned expense of the family when none
     */
    public function pays(PlannedExpense|PlannedIncome|null $planned = null): static
    {
        return $this->state(function (array $attributes) use ($planned): array {
            $planned ??= PlannedExpense::factory()->create(['family_id' => $attributes['family_id']]);

            return [
                'action' => BankFilterAction::Pay,
                'direction' => $planned instanceof PlannedIncome ? 'income' : 'expense',
                'planned_expense_id' => $planned instanceof PlannedExpense ? $planned->id : null,
                'planned_income_id' => $planned instanceof PlannedIncome ? $planned->id : null,
            ];
        });
    }
}

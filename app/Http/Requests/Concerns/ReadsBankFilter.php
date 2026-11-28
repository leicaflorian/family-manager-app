<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Actions\Banks\SaveBankFilter;
use App\Models\ExpenseCategory;
use App\Models\FinancialAccount;
use App\Models\Organization;
use App\Models\Person;
use App\Models\PlannedExpense;
use App\Models\PlannedIncome;
use App\Support\Banks\BankFilterCriteria;
use App\Support\ModelId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * The fields of the form of a bank filter, shared by its save and its preview: the ids it names are looked for in the
 * family before anything else (one of another family answers 404, even when the rest of the body is wrong), and the
 * criteria and the columns are read from one place (`SaveBankFilter::columns()`).
 */
trait ReadsBankFilter
{
    /**
     * Looks for every id the form names in the current family: the accounts, the planned item (of the direction of
     * the filter), the category, the organization and the person.
     *
     * @throws ModelNotFoundException if one is not of the family
     */
    protected function findSentIds(): void
    {
        $income = $this->input('direction') === 'income';
        $models = [
            'financial_account_id' => FinancialAccount::class,
            'transfer_account_id' => FinancialAccount::class,
            'planned_id' => $income ? PlannedIncome::class : PlannedExpense::class,
            'category_id' => ExpenseCategory::class,
            'organization_id' => Organization::class,
            'person_id' => Person::class,
        ];

        foreach ($models as $field => $model) {
            // The same reading as the save, so that no id is saved without being looked for (a JSON `12.0` too)
            $id = ModelId::sent($this->input($field));

            if ($id !== null) {
                /** @var class-string<Model> $model */
                $model::query()->findOrFail($id);
            }
        }
    }

    /**
     * The criteria the form describes.
     */
    public function criteria(): BankFilterCriteria
    {
        $columns = $this->filterColumns();

        return BankFilterCriteria::fromArray([
            'contains' => $columns['contains'],
            'excludes' => $columns['excludes'],
            'direction' => $columns['direction'],
            'accountId' => $columns['financial_account_id'],
            'amountMin' => $columns['amount_min'],
            'amountMax' => $columns['amount_max'],
            'pattern' => $columns['pattern'],
        ]);
    }

    /**
     * The columns of the filter the form describes.
     *
     * @return array<string, mixed>
     */
    public function filterColumns(): array
    {
        /** @var array<string, mixed> $all */
        $all = $this->all();

        return SaveBankFilter::columns($all);
    }
}

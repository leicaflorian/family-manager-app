<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Expense;
use App\Models\Income;
use App\Models\User;
use App\Services\CategoryTree;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * An expense or an income, as the lists of the budget show it.
 */
#[TypeScript]
final class MovementData extends Data
{
    /**
     * @param  string  $kind  `expense` or `income`
     * @param  int  $id  the id
     * @param  string  $title  the title
     * @param  string  $date  the day, ISO
     * @param  float  $amount  the amount
     * @param  float|null  $fee_amount  the fee of an expense
     * @param  int|null  $account_id  the account
     * @param  string|null  $account  the name of the account
     * @param  int|null  $category_id  the category of an expense
     * @param  string|null  $category  the name of the category
     * @param  string|null  $icon  the icon of the category
     * @param  string|null  $color  the colour of the category
     * @param  int|null  $organization_id  the organization
     * @param  string|null  $organization  the name of the organization
     * @param  int|null  $person_id  the person
     * @param  int|null  $planned_id  the planned expense or income it settles
     * @param  string|null  $occurrence_date  the original day of the due date it settles, ISO
     * @param  string|null  $type  the type of an income
     * @param  int|null  $employment_id  the employment of an income
     * @param  list<DocumentData>  $documents  the documents linked to it
     * @param  bool  $can_edit  whether the person can change it
     * @param  bool  $can_delete  whether the person can delete it
     */
    public function __construct(
        public readonly string $kind,
        public readonly int $id,
        public readonly string $title,
        public readonly string $date,
        public readonly float $amount,
        public readonly ?float $fee_amount,
        public readonly ?int $account_id,
        public readonly ?string $account,
        public readonly ?int $category_id,
        public readonly ?string $category,
        public readonly ?string $icon,
        public readonly ?string $color,
        public readonly ?int $organization_id,
        public readonly ?string $organization,
        public readonly ?int $person_id,
        public readonly ?int $planned_id,
        public readonly ?string $occurrence_date,
        public readonly ?string $type,
        public readonly ?int $employment_id,
        public readonly array $documents,
        public readonly bool $can_edit,
        public readonly bool $can_delete,
    ) {}

    /**
     * The data of an expense or an income.
     *
     * @param  Expense|Income  $movement  the expense or income
     * @param  User  $user  who looks at it, for the permissions
     * @param  array<int, list<string>>|null  $paths  the paths of the document categories; read here when missing
     */
    public static function fromMovement(Expense|Income $movement, User $user, ?array $paths = null): self
    {
        $expense = $movement instanceof Expense ? $movement : null;
        $income = $movement instanceof Income ? $movement : null;
        $documents = $movement->documents;
        $paths ??= $documents->isEmpty() ? [] : resolve(CategoryTree::class)->paths();

        return new self(
            kind: $expense instanceof Expense ? 'expense' : 'income',
            id: $movement->id,
            title: $movement->title ?? $income?->type->label() ?? '',
            date: $movement->date?->toDateString() ?? '',
            amount: round((float) $movement->amount, 2),
            fee_amount: $expense?->fee_amount === null ? null : round((float) $expense->fee_amount, 2),
            account_id: $movement->financial_account_id,
            account: $movement->financialAccount?->name,
            category_id: $expense?->category_id,
            category: $expense?->category?->name,
            icon: $expense?->category?->icon,
            color: $expense?->category?->color,
            organization_id: $movement->organization_id,
            organization: $movement->organization?->name,
            person_id: $movement->person_id,
            planned_id: $movement instanceof Expense ? $movement->planned_expense_id : $movement->planned_income_id,
            occurrence_date: $movement->occurrence_date?->toDateString(),
            type: $income?->type->value,
            employment_id: $income?->employment_id,
            documents: array_values($documents->map(static fn ($document): DocumentData => DocumentData::fromDocument($document, $paths))->all()),
            can_edit: $user->can('update', $movement),
            can_delete: $user->can('delete', $movement),
        );
    }
}

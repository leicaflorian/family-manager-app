<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * The type of an income, received or planned (`incomes.type`, `planned_incomes.type`).
 * The values are those of Directus.
 */
enum IncomeType: string
{
    use HasLabel;

    case Salary = 'salary';
    case Benefit = 'benefit';
    case Refund = 'refund';
    case Other = 'other';
}

<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\FinancialAccount;
use App\Models\Organization;
use App\Models\Person;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * For the money that comes and goes (expenses, incomes and their planned versions): the organization, the person and
 * the financial account it can be linked to.
 */
trait HasMovementLinks
{
    /**
     * The linked organization.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * The person it refers to.
     *
     * @return BelongsTo<Person, $this>
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * The linked financial account.
     *
     * @return BelongsTo<FinancialAccount, $this>
     */
    public function financialAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class);
    }
}

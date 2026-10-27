<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SubscriptionStatus;
use App\Enums\SubscriptionType;
use App\Models\Concerns\HasDocuments;
use Carbon\CarbonImmutable;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A subscription: streaming, software, cloud (`subscriptions`).
 *
 * @property int $id
 * @property int $family_id
 * @property string $name
 * @property SubscriptionType $type
 * @property SubscriptionStatus $status
 * @property int|null $organization_id
 * @property string|null $account
 * @property int|null $financial_account_id
 * @property CarbonImmutable|null $activation_date
 * @property CarbonImmutable|null $renewal_date
 * @property bool $is_auto_renew
 * @property CarbonImmutable|null $trial_end_date
 * @property string|null $manage_url
 * @property string|null $notes
 * @property int|null $person_id
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, Document> $documents
 * @property-read int|null $documents_count
 * @property-read Family $family
 * @property-read FinancialAccount|null $financialAccount
 * @property-read Organization|null $organization
 * @property-read Person|null $person
 *
 * @method static SubscriptionFactory factory($count = null, $state = [])
 * @method static Builder<static>|Subscription newModelQuery()
 * @method static Builder<static>|Subscription newQuery()
 * @method static Builder<static>|Subscription query()
 *
 * @mixin \Eloquent
 */
#[Fillable(['name', 'type', 'status', 'organization_id', 'account', 'financial_account_id', 'activation_date', 'renewal_date', 'is_auto_renew', 'trial_end_date', 'manage_url', 'notes', 'person_id'])]
final class Subscription extends FamilyModel
{
    use HasDocuments;

    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory;

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
     * The linked financial account.
     *
     * @return BelongsTo<FinancialAccount, $this>
     */
    public function financialAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class);
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
     * The types of the columns.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => SubscriptionType::class,
            'status' => SubscriptionStatus::class,
            'activation_date' => 'date',
            'renewal_date' => 'date',
            'is_auto_renew' => 'boolean',
            'trial_end_date' => 'date',
        ];
    }

    /**
     * The name among a document's links: the name.
     */
    public function label(): string
    {
        return $this->name;
    }
}

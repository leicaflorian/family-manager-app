<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FinancialAccountStatus;
use App\Enums\FinancialAccountType;
use App\Models\Concerns\HasDocuments;
use Carbon\CarbonImmutable;
use Database\Factories\FinancialAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An account, a card or a deposit (`financial_accounts`). Never full IBANs, card numbers, PINs or passwords.
 *
 * @property int $id
 * @property int $family_id
 * @property string $name
 * @property FinancialAccountType $type
 * @property FinancialAccountStatus $status
 * @property int|null $organization_id
 * @property int|null $person_id
 * @property string|null $last_four_digits
 * @property bool $is_default
 * @property string|null $notes
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, Document> $documents
 * @property-read int|null $documents_count
 * @property-read Family $family
 * @property-read Organization|null $organization
 * @property-read Person|null $person
 *
 * @method static FinancialAccountFactory factory($count = null, $state = [])
 * @method static Builder<static>|FinancialAccount newModelQuery()
 * @method static Builder<static>|FinancialAccount newQuery()
 * @method static Builder<static>|FinancialAccount query()
 *
 * @mixin \Eloquent
 */
#[Fillable(['name', 'type', 'status', 'organization_id', 'person_id', 'last_four_digits', 'is_default', 'notes'])]
final class FinancialAccount extends FamilyModel
{
    use HasDocuments;

    /** @use HasFactory<FinancialAccountFactory> */
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
            'type' => FinancialAccountType::class,
            'status' => FinancialAccountStatus::class,
            'is_default' => 'boolean',
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

<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UtilityStatus;
use App\Enums\UtilityType;
use App\Models\Concerns\HasDocuments;
use Carbon\CarbonImmutable;
use Database\Factories\UtilityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A utility: electricity, gas, water, internet, phone, waste tax, TV licence (`utilities`).
 *
 * @property int $id
 * @property int $family_id
 * @property UtilityType $type
 * @property UtilityStatus $status
 * @property int|null $organization_id
 * @property int|null $home_id
 * @property int|null $person_id
 * @property string|null $customer_code
 * @property string|null $supply_point_code
 * @property CarbonImmutable|null $activation_date
 * @property string|null $notes
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, Document> $documents
 * @property-read int|null $documents_count
 * @property-read Family $family
 * @property-read Home|null $home
 * @property-read Organization|null $organization
 * @property-read Person|null $person
 *
 * @method static UtilityFactory factory($count = null, $state = [])
 * @method static Builder<static>|Utility newModelQuery()
 * @method static Builder<static>|Utility newQuery()
 * @method static Builder<static>|Utility query()
 *
 * @mixin \Eloquent
 */
#[Fillable(['type', 'status', 'organization_id', 'home_id', 'person_id', 'customer_code', 'supply_point_code', 'activation_date', 'notes'])]
final class Utility extends FamilyModel
{
    use HasDocuments;

    /** @use HasFactory<UtilityFactory> */
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
     * The linked home.
     *
     * @return BelongsTo<Home, $this>
     */
    public function home(): BelongsTo
    {
        return $this->belongsTo(Home::class);
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
            'type' => UtilityType::class,
            'status' => UtilityStatus::class,
            'activation_date' => 'date',
        ];
    }

    /**
     * The name among a document's links: the type and the home, for example «Electricity - Via Roma 12».
     */
    public function label(): string
    {
        return self::joinLabel($this->type->label(), $this->home?->name);
    }

    /**
     * The relations the name reads.
     *
     * @return list<string>
     */
    public static function labelRelations(): array
    {
        return ['home'];
    }
}

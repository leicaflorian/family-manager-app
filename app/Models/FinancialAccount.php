<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StatoConto;
use App\Enums\TipoConto;
use App\Models\Concerns\HaDocumenti;
use Carbon\CarbonImmutable;
use Database\Factories\FinancialAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un conto, una carta o un deposito (`financial_accounts`). Mai IBAN completi, numeri di carta, PIN o password.
 *
 * @property int $id
 * @property int $family_id
 * @property string $name
 * @property TipoConto $type
 * @property StatoConto $status
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
final class FinancialAccount extends DatoDellaFamiglia
{
    use HaDocumenti;

    /** @use HasFactory<FinancialAccountFactory> */
    use HasFactory;

    /**
     * L'ente collegato.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * La persona a cui si riferisce.
     *
     * @return BelongsTo<Person, $this>
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * I tipi delle colonne.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TipoConto::class,
            'status' => StatoConto::class,
            'is_default' => 'boolean',
        ];
    }
}

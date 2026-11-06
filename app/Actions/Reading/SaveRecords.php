<?php

declare(strict_types=1);

namespace App\Actions\Reading;

use App\Actions\Cards\SaveCard;
use App\Cards\CardTypes;
use App\Data\Reading\RecordDraftData;
use App\Enums\RecordKind;
use App\Models\Belonging;
use App\Models\Document;
use App\Models\Expense;
use App\Models\InsurancePolicy;
use App\Models\MaintenanceRecord;
use App\Models\Organization;
use App\Models\Utility;
use App\Models\Vehicle;
use App\Services\Reading\RecordDrafts;
use App\Support\Reading\ImportErrors;
use App\Support\Reading\RecordLabel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

/**
 * The cards a file creates or links when it is imported (`saveRecords` of the old reader), in two steps:
 * organization, vehicle, utility, policy and belonging *before* the document, which is linked to them; the
 * intervention *after* the movement, whose expense it takes. The ones saved first give the next ones what they
 * left empty: the organization its supplier, the vehicle the policy and the intervention. It runs inside the
 * transaction of the file: a card that cannot be saved throws, and nothing of the file stays.
 */
final readonly class SaveRecords
{
    /** The kinds saved before the document. */
    private const array BEFORE = [RecordKind::Organization, RecordKind::Vehicle, RecordKind::Utility, RecordKind::Policy, RecordKind::Belonging];

    /**
     * @param  SaveCard  $saveCard  creates a card
     */
    public function __construct(private SaveCard $saveCard) {}

    /**
     * Creates or links the ticked cards that come before the document, in the order of the boxes.
     *
     * @param  list<RecordDraftData>  $drafts  the boxes of the proposal
     * @param  list<RecordKind>  $ticked  the boxes ticked
     * @return array{records: array<string, Model>, links: list<array{type: string, id: int}>, organization: ?int, ids: array<string, int>}
     *                                                                                                                                      the cards by kind, the links of the document, the organization created or linked (the document and the movement take it), and the id of each card for the ones after
     *
     * @throws ValidationException if a card misses a required field
     * @throws ModelNotFoundException if a card to link is no longer there
     */
    public function before(array $drafts, array $ticked): array
    {
        $out = ['records' => [], 'links' => [], 'organization' => null, 'ids' => []];

        foreach ($drafts as $draft) {
            if (! in_array($draft->kind, $ticked, true) || ! in_array($draft->kind, self::BEFORE, true)) {
                continue;
            }

            $card = $this->existing($draft) ?? $this->create($draft, $out['ids']);
            $out['records'][$draft->kind->value] = $card;
            $out['ids'][$draft->kind->value] = $card->id;

            $type = $draft->kind->cardType();
            if ($type === null) {
                $out['organization'] = $card->id;
            } else {
                $out['links'][] = ['type' => $type, 'id' => $card->id];
            }
        }

        return $out;
    }

    /**
     * Creates or links the ticked intervention: it takes the document and the expense of the file, and the
     * organization and vehicle saved before; one already there takes the expense only when it has none.
     *
     * @param  list<RecordDraftData>  $drafts  the boxes of the proposal
     * @param  list<RecordKind>  $ticked  the boxes ticked
     * @param  array<string, int>  $ids  the cards saved before, by kind
     * @param  Document  $document  the document of the file
     * @param  Expense|null  $expense  the expense of the file, created or linked
     * @return array<string, MaintenanceRecord> the intervention, by kind
     *
     * @throws ValidationException if the intervention misses a required field
     * @throws ModelNotFoundException if the intervention to link is no longer there
     */
    public function after(array $drafts, array $ticked, array $ids, Document $document, ?Expense $expense): array
    {
        $records = [];

        foreach ($drafts as $draft) {
            if ($draft->kind !== RecordKind::Maintenance || ! in_array($draft->kind, $ticked, true)) {
                continue;
            }

            $record = $this->existing($draft);
            if ($record instanceof MaintenanceRecord) {
                if ($expense instanceof Expense && $record->expense_id === null) {
                    $record->update(['expense_id' => $expense->id]);
                }
            } else {
                $record = $this->create($draft, $ids, ['expense_id' => $expense?->id]);
            }

            if (! $record instanceof MaintenanceRecord) {
                continue;
            }

            $record->documents()->syncWithoutDetaching([$document->id]);
            $records[$draft->kind->value] = $record;
        }

        return $records;
    }

    /**
     * The card already there the box links to, if it has one.
     *
     * @param  RecordDraftData  $draft  the box
     *
     * @throws ModelNotFoundException if it is no longer there
     */
    private function existing(RecordDraftData $draft): Organization|Vehicle|Utility|InsurancePolicy|Belonging|MaintenanceRecord|null
    {
        return $draft->existing === null ? null : RecordLabel::model($draft->kind)::query()->findOrFail($draft->existing['id']);
    }

    /**
     * Creates the card of a box, with what the cards saved before give.
     *
     * @param  RecordDraftData  $draft  the box
     * @param  array<string, int>  $ids  the cards saved before, by kind
     * @param  array<string, int|null>  $extra  more columns (the expense of an intervention)
     *
     * @throws ValidationException if it misses a required field
     */
    private function create(RecordDraftData $draft, array $ids, array $extra = []): Organization|Vehicle|Utility|InsurancePolicy|Belonging|MaintenanceRecord
    {
        $values = $this->filled($draft, $ids);
        $missing = RecordDrafts::missing($draft->kind, $values);
        if ($missing !== []) {
            throw ValidationException::withMessages(['records.'.$draft->kind->value => ImportErrors::record($draft->title, $missing)]);
        }

        // Empty values are left out, so the columns keep their defaults
        $columns = array_filter([...$values, ...$extra], static fn (mixed $value): bool => ! in_array($value, [null, '', []], true));
        $type = $draft->kind->cardType();

        $card = $type === null
            ? Organization::query()->create($columns)
            : $this->saveCard->handle(CardTypes::findOrFail($type), $columns);

        return $card instanceof Organization || $card instanceof Vehicle || $card instanceof Utility || $card instanceof InsurancePolicy
            || $card instanceof Belonging || $card instanceof MaintenanceRecord
            ? $card
            : throw new ModelNotFoundException("The card {$draft->kind->value} was not created.");
    }

    /**
     * The values of a box with what the cards saved before give: the organization fills an empty supplier, the
     * vehicle an empty vehicle (an intervention on a vehicle has no home), and only a home policy keeps its home,
     * since the type may have changed after the home was proposed.
     *
     * @param  RecordDraftData  $draft  the box
     * @param  array<string, int>  $ids  the cards saved before, by kind
     * @return array<string, mixed>
     */
    private function filled(RecordDraftData $draft, array $ids): array
    {
        $values = $draft->values;
        $organization = $ids[RecordKind::Organization->value] ?? null;
        $vehicle = $ids[RecordKind::Vehicle->value] ?? null;

        if (array_key_exists('organization_id', $values) && $values['organization_id'] === null && $organization !== null) {
            $values['organization_id'] = $organization;
        }

        if ($draft->kind === RecordKind::Policy && ($values['type'] ?? null) !== 'home') {
            $values['home_id'] = null;
        }

        if (array_key_exists('vehicle_id', $values) && $values['vehicle_id'] === null && $vehicle !== null) {
            $values['vehicle_id'] = $vehicle;
            if ($draft->kind === RecordKind::Maintenance) {
                $values['home_id'] = null;
            }
        }

        return $values;
    }
}

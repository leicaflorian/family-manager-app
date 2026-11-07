<?php

declare(strict_types=1);

namespace App\Services\Reading;

use App\Cards\CardField;
use App\Data\CardFieldData;
use App\Data\Reading\Extraction;
use App\Data\Reading\ImportChoices;
use App\Data\Reading\RecordDraftData;
use App\Enums\RecordKind;
use App\Models\Belonging;
use App\Models\InsurancePolicy;
use App\Models\MaintenanceRecord;
use App\Models\Organization;
use App\Models\Utility;
use App\Models\Vehicle;
use App\Services\CardDetails;
use App\Support\Reading\RecordLabel;
use App\Support\Translated;
use Carbon\CarbonImmutable;

/**
 * The cards side of a file (`recordsSide` of the old reader): the missing cards the paper is about, searched with
 * the codes the person corrected, with the corrections, the one already there or picked by hand, and the cards of
 * each kind to pick from. It runs in the family of the file and writes nothing.
 */
final readonly class RecordsProposal
{
    /** The kinds that, when found, are linked instead of proposed: a utility, vehicle or policy found has no box. */
    private const array LINKED_WHEN_FOUND = [RecordKind::Organization, RecordKind::Belonging, RecordKind::Maintenance];

    /**
     * The fields of the reading a correction of a card replaces, with the kind and column of the correction. The
     * proposed title and home of a policy follow a type changed by hand.
     */
    private const array CORRECTED = [
        'supply_point_code' => ['utility', 'supply_point_code'],
        'customer_code' => ['utility', 'customer_code'],
        'plate_number' => ['vehicle', 'plate_number'],
        'policy_number' => ['policy', 'policy_number'],
        'insurance_type' => ['policy', 'type'],
        'item_brand' => ['belonging', 'brand'],
        'item_model' => ['belonging', 'model'],
        'issuer_name' => ['organization', 'name'],
    ];

    /**
     * @param  Matcher  $matcher  the cards by code, plate, number and name
     * @param  RecordSearch  $search  the terminated utility, the homes, the belonging and the intervention
     * @param  CardDetails  $details  the fields of a card as its form draws them
     */
    public function __construct(private Matcher $matcher, private RecordSearch $search, private CardDetails $details) {}

    /**
     * The boxes of the cards and the codes that no card has and no box creates.
     *
     * @param  Extraction  $x  what the model read
     * @param  array{organization_id: ?int, organization_name: ?string, person_id: ?int, date: ?CarbonImmutable}  $document  the document as it will be saved
     * @param  float|null  $amount  the amount of the paper, with the correction of the person
     * @param  ImportChoices  $choices  the choices of the person
     * @return array{records: list<RecordDraftData>, warnings: list<string>}
     */
    public function of(Extraction $x, array $document, ?float $amount, ImportChoices $choices): array
    {
        $edits = $choices->overrides['records'];
        $targets = $choices->targets['records'];
        $xc = $this->corrected($x, $edits);

        $vehicle = $this->matcher->vehicle($xc->plate_number);
        $homes = $this->search->activeHomes();
        $onlyHome = $homes->count() === 1 ? $homes->first()?->id : null;
        $maintenance = $edits['maintenance'] ?? [];
        $found = [
            'utility' => $this->matcher->utility($xc->supply_point_code, $xc->customer_code),
            'terminatedUtility' => $this->search->terminatedUtility($xc->supply_point_code, $xc->customer_code),
            'vehicle' => $vehicle,
            'policy' => $this->matcher->policy($xc->policy_number),
            'organization' => $this->matcher->organizationByName($xc->issuer_name),
            'belonging' => $xc->durable_good === true
                ? $this->search->belonging($xc->item_brand, $xc->item_model, $document['organization_id'], $document['date'], $amount)
                : null,
            'maintenance' => $this->search->maintenance(
                $vehicle?->id,
                $vehicle instanceof Vehicle ? null : $this->int($maintenance['home_id'] ?? null) ?? $onlyHome,
                $this->text($maintenance['type'] ?? null) ?? $xc->maintenance_type,
                $document['date'],
            ),
            'homes' => $homes,
        ];

        // A card created or picked by hand is proposed even when the search found one; one picked and gone is
        // looked for again, like the movement and the event, and the confirmation tells the person
        foreach ([RecordKind::Utility, RecordKind::Vehicle, RecordKind::Policy] as $kind) {
            $target = $targets[$kind->value] ?? 'auto';
            if ($target === 'new' || (is_int($target) && RecordLabel::model($kind)::query()->whereKey($target)->exists())) {
                $found[$kind->value] = null;
            }
        }

        $records = array_map(
            fn (RecordDraftData $draft): RecordDraftData => $this->withChoices($draft, $edits[$draft->kind->value] ?? [], $targets[$draft->kind->value] ?? 'auto'),
            RecordDrafts::of($xc, $found, $document, $amount),
        );

        return ['records' => $records, 'warnings' => $this->warnings($xc, $found['utility'], $found['vehicle'], $found['policy'], $records)];
    }

    /**
     * A box with the corrections and the choice of the person: the card picked by hand, or the one found, is linked
     * and needs nothing; a new one needs its required fields.
     *
     * @param  RecordDraftData  $draft  the box the reading proposes
     * @param  array<string, mixed>  $edits  the corrections of the card
     * @param  'auto'|'new'|int  $target  the choice of the person
     */
    private function withChoices(RecordDraftData $draft, array $edits, string|int $target): RecordDraftData
    {
        $values = [...$draft->values, ...$edits];
        $picked = is_int($target) ? RecordLabel::model($draft->kind)::query()->find($target) : null;
        $existing = match (true) {
            $picked !== null => ['id' => $picked->id, 'title' => RecordLabel::of($picked)],
            $target === 'new' => null,
            default => in_array($draft->kind, self::LINKED_WHEN_FOUND, true) ? $draft->existing : null,
        };
        $missing = $existing === null ? RecordDrafts::missing($draft->kind, $values) : [];
        $warranty = $values['warranty_expiry_date'] ?? null;

        return new RecordDraftData(
            kind: $draft->kind,
            title: $draft->title,
            values: $values,
            suggested: $draft->suggested && $missing === [],
            missing: $missing,
            existing: $existing,
            candidates: $this->candidates($draft->kind),
            reminder: $draft->kind === RecordKind::Belonging && $existing === null && is_string($warranty) && $warranty !== ''
                ? Translated::text("I'll remind you before the warranty ends")
                : null,
            fields: $existing === null ? $this->fields($draft->kind, $values) : [],
        );
    }

    /**
     * The required fields of a new card still empty that the person can write in its box, as the card form draws
     * them; the ones that come from the document (the date of an intervention) are written in its form instead.
     *
     * @param  RecordKind  $kind  which card
     * @param  array<string, mixed>  $values  the fields it would be created with
     * @return list<CardFieldData>
     */
    private function fields(RecordKind $kind, array $values): array
    {
        // An organization has no card class: its box exists only with the name read, and emptying the name drops it
        $empty = static fn (string $column): bool => ($values[$column] ?? null) === null || $values[$column] === '';
        $editable = ImportChoices::RECORD_FIELDS[$kind->value];

        return array_values(array_map(
            $this->details->fieldData(...),
            array_filter(
                RecordDrafts::requiredFields($kind),
                static fn (CardField $field): bool => array_key_exists($field->column, $editable) && $empty($field->column),
            ),
        ));
    }

    /**
     * The cards of a kind, by name, to pick one by hand.
     *
     * @param  RecordKind  $kind  which card
     * @return list<array{id: int, label: string}>
     */
    private function candidates(RecordKind $kind): array
    {
        $cards = RecordLabel::model($kind)::query()->orderBy('id')->get()
            ->map(static fn (Organization|Vehicle|Utility|InsurancePolicy|Belonging|MaintenanceRecord $card): array => ['id' => $card->id, 'label' => RecordLabel::of($card)])
            ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE);

        return array_values($cards->all());
    }

    /**
     * The reading with the codes and names the person corrected in the cards, for the searches.
     *
     * @param  Extraction  $x  what the model read
     * @param  array<string, array<string, mixed>>  $edits  the corrections, by kind
     */
    private function corrected(Extraction $x, array $edits): Extraction
    {
        $changes = [];
        foreach (self::CORRECTED as $field => [$kind, $column]) {
            if (array_key_exists($column, $edits[$kind] ?? [])) {
                $changes[$field] = $edits[$kind][$column];
            }
        }

        return $changes === [] ? $x : Extraction::from([...$x->toArray(), ...$changes]);
    }

    /**
     * The codes on the paper that no card has and no box creates.
     *
     * @param  Extraction  $x  the reading with the corrections
     * @param  Utility|null  $utility  the active utility found
     * @param  Vehicle|null  $vehicle  the vehicle found
     * @param  InsurancePolicy|null  $policy  the policy found
     * @param  list<RecordDraftData>  $records  the boxes
     * @return list<string>
     */
    private function warnings(Extraction $x, ?Utility $utility, ?Vehicle $vehicle, ?InsurancePolicy $policy, array $records): array
    {
        $proposed = array_map(static fn (RecordDraftData $draft): RecordKind => $draft->kind, $records);
        $code = $x->supply_point_code ?? $x->customer_code;

        return array_values(array_filter([
            ! $utility instanceof Utility && $x->kind === 'utility_bill' && $code !== null && ! in_array(RecordKind::Utility, $proposed, true)
                ? Translated::text('Utility not found (:code): choose it by hand.', ['code' => $code]) : null,
            ! $vehicle instanceof Vehicle && $x->plate_number !== null && ! in_array(RecordKind::Vehicle, $proposed, true)
                ? Translated::text('No vehicle with the plate :plate.', ['plate' => $x->plate_number]) : null,
            ! $policy instanceof InsurancePolicy && $x->kind === 'insurance' && $x->policy_number !== null && ! in_array(RecordKind::Policy, $proposed, true)
                ? Translated::text('No policy with the number :number.', ['number' => $x->policy_number]) : null,
        ]));
    }

    /**
     * A cleaned correction as text.
     *
     * @param  mixed  $value  the correction
     */
    private function text(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    /**
     * A cleaned correction as an id.
     *
     * @param  mixed  $value  the correction
     */
    private function int(mixed $value): ?int
    {
        return is_int($value) ? $value : null;
    }
}

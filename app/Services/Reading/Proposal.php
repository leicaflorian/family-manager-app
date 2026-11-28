<?php

declare(strict_types=1);

namespace App\Services\Reading;

use App\Data\Reading\ConsumptionProposalData;
use App\Data\Reading\ConsumptionValuesData;
use App\Data\Reading\DocumentProposalData;
use App\Data\Reading\EventProposalData;
use App\Data\Reading\EventValuesData;
use App\Data\Reading\Extraction;
use App\Data\Reading\ImportChoices;
use App\Data\Reading\MovementProposalData;
use App\Data\Reading\PolicyGiverData;
use App\Data\Reading\ProposalData;
use App\Data\Reading\RecordDraftData;
use App\Data\Reading\SupplyProposalData;
use App\Enums\ConsumptionOutcome;
use App\Enums\ConsumptionUnit;
use App\Enums\Feature;
use App\Enums\RecordKind;
use App\Enums\UtilityType;
use App\Models\DocumentCategory;
use App\Models\Expense;
use App\Models\File;
use App\Models\HealthEvent;
use App\Models\Income;
use App\Models\Utility;
use App\Services\FamilyFeatures;
use App\Support\CurrentFamily;
use App\Support\DocumentRules;
use App\Support\Reading\BillTitle;
use App\Support\Reading\Choices;
use App\Support\Reading\ConsumptionCheck;
use App\Support\Reading\IssuerOrder;
use App\Support\Reading\ValidDate;
use App\Support\Translated;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\App;

/**
 * What a read file would do when confirmed (`previewFile` of the old reader): the document, the expense or income
 * ({@see MovementProposal}), the health event, the missing cards ({@see RecordsProposal}), the policy taken from
 * another file or from one registered and the consumption of a utility bill. It works on the reading saved in `files.reading`, inside the family of the
 * file, with the choices of the person, and writes nothing.
 *
 * While it runs the language of the app is the one of the proposal: the card titles of {@see RecordDrafts} and the
 * sentences of the collaborators are written in the language of the app, while the document and the movement take
 * it as an argument, so without the switch one proposal could mix two languages. `finally` gives the app its
 * language back, also when the proposal fails.
 *
 * A payment receipt linked to an expense already recorded is filed next to the paper it pays: its document takes the
 * category of the first document of that expense that has one (`receiptCategory` of the old reader).
 */
final readonly class Proposal
{
    /** Days around the date of a paper within which the events of the person are offered to pick by hand. */
    public const int EVENT_CANDIDATE_DAYS = 30;

    /** How many events the person is offered to pick by hand. */
    public const int EVENT_CANDIDATE_LIMIT = 20;

    /**
     * @param  DocumentProposal  $documents  the fields of the document
     * @param  MovementDraft  $drafts  the movement and the event the reading proposes
     * @param  MovementProposal  $movements  the movement side
     * @param  RecordsProposal  $records  the cards side
     * @param  RecordSearch  $search  the event already there
     * @param  PolicyFromOtherFiles  $policies  the policy of a paper without a number
     * @param  FamilyFeatures  $features  the parts of the app the family has: without the Budget no money is proposed
     * @param  Matcher  $matcher  the utility of each supply of a bill with more than one
     */
    public function __construct(
        private DocumentProposal $documents,
        private MovementDraft $drafts,
        private MovementProposal $movements,
        private RecordsProposal $records,
        private RecordSearch $search,
        private PolicyFromOtherFiles $policies,
        private FamilyFeatures $features,
        private Matcher $matcher,
    ) {}

    /**
     * The proposal of a file. A file without a reading (the reading was off) proposes from an empty one.
     *
     * @param  File  $file  the file, waiting in *To sort*
     * @param  ImportChoices  $choices  the choices of the person
     * @param  list<PolicyGiverData>  $roundGivers  the other files confirmed with this one that give a policy
     * @param  string  $locale  the language of the texts (`it` or `en`)
     */
    public function for(File $file, ImportChoices $choices, array $roundGivers, string $locale): ProposalData
    {
        // The card titles and the sentences are written in the language of the app: the proposal's, while it runs
        $previous = App::getLocale();
        App::setLocale($locale);

        try {
            return CurrentFamily::using($file->family_id, fn (): ProposalData => $this->build($file, $choices, $roundGivers, $locale));
        } finally {
            App::setLocale($previous);
        }
    }

    /**
     * Builds the proposal in the family of the file.
     *
     * @param  File  $file  the file
     * @param  ImportChoices  $choices  the choices of the person
     * @param  list<PolicyGiverData>  $roundGivers  the other files that give a policy
     * @param  string  $locale  the language of the texts
     */
    private function build(File $file, ImportChoices $choices, array $roundGivers, string $locale): ProposalData
    {
        $x = Extraction::ofFile($file);
        $multi = $x->isMultiSupply();
        $context = ReadingContext::for($file);
        $document = $this->documents->of($x, $context, $locale);
        $side = $this->documentSide($document, $context, $choices);
        $cardType = DocumentCategory::cardTypeOf($this->categoryId($document, $choices));
        $draft = $this->drafts->of($x, $context, $locale, $cardType, $side['person_id'], withMovement: ! $multi);

        // Without the Budget the reading proposes no movement, no due date to pay and no consumption: only the
        // document, the cards and the event. With more than one supply each supply has its own.
        $budget = $this->features->on(Feature::Budget);
        $movement = $draft['movement'] === null || ! $budget
            ? null
            : $this->movements->of($x, $context, $draft['movement'], $draft['movementSuggested'], $this->fromMovement($file, $draft['movement']->kind, $choices), $side['date']);
        $document = $this->receiptCategory($x, $document, $movement);
        $amount = $choices->movementOverrides($movement->values->kind ?? 'expense')['amount'] ?? $x->total_amount;
        // The utilities of the supplies are in their own boxes: the cards of the document are searched without codes
        $cardsOf = $multi ? Extraction::from([...$x->toArray(), 'supply_point_code' => null, 'customer_code' => null, 'utility_type' => null, 'supplies' => []]) : $x;
        ['records' => $records, 'warnings' => $warnings] = $this->records->of($cardsOf, $side, is_numeric($amount) ? (float) $amount : null, $choices, $cardType);
        $records = $multi ? array_values(array_filter($records, static fn (RecordDraftData $box): bool => $box->kind !== RecordKind::Utility)) : $records;
        $eventDraft = $this->healthEvent($draft['event'], $cardType, $document);
        $event = $eventDraft instanceof EventValuesData ? $this->event($eventDraft, $draft['eventSuggested'], $side, $choices) : null;
        $supplies = $multi ? $this->supplies($x, $context, $side, $choices, $budget, $locale) : [];
        $supplyRecords = array_values(array_filter(array_map(static fn (SupplyProposalData $supply): ?RecordDraftData => $supply->record, $supplies)));

        return new ProposalData(
            fileId: $file->id,
            document: $document,
            movement: $movement,
            event: $event,
            records: $records,
            policy: $this->policy($x, $document, $side, $records, $choices, $roundGivers),
            warnings: $warnings,
            // With the card of the category the boxes already say which card the document belongs to
            createCard: $cardType === null && $this->namesNoCard($document, $event, [...$records, ...$supplyRecords]),
            cardType: $cardType,
            consumption: $budget && ! $multi ? $this->consumption($x, $document, $records, $choices) : null,
            supplies: $supplies,
        );
    }

    /**
     * What each supply of a bill with more than one proposes: the box of its utility, its expense and its consumption,
     * each worked out on the reading seen from the supply with the choices of that supply, as for a bill alone.
     *
     * @param  Extraction  $x  what the model read
     * @param  ReadingContext  $context  the lists the model chose from
     * @param  array{organization_id: ?int, organization_name: ?string, person_id: ?int, date: ?CarbonImmutable}  $side  the document as it will be saved
     * @param  ImportChoices  $choices  the choices of the person
     * @param  bool  $budget  whether the family has the Budget: without it no expense and no consumption
     * @param  string  $locale  the language of the texts
     * @return list<SupplyProposalData>
     */
    private function supplies(Extraction $x, ReadingContext $context, array $side, ImportChoices $choices, bool $budget, string $locale): array
    {
        $found = $this->matcher->supplyUtilities($x->supplies, $x->customer_code);
        $proposals = [];

        foreach ($x->supplies as $index => $supply) {
            $view = $x->forSupply($index);
            $own = $choices->forSupply($index);
            $record = $this->records->supply($view, $side, $own, $found[$index] ?? null);
            // The utility of the box, found or picked by hand and ticked, is the one of the expense and of the
            // consumption: an unticked box links no utility to the document, so neither refers to it
            $ticked = $record instanceof RecordDraftData && $choices->supplyRecordTicked($index, $record->suggested);
            $utility = ! $ticked || $record->existing === null ? null : Utility::query()->find($record->existing['id']);
            $created = $utility instanceof Utility ? null : $this->createdType($record, $ticked);

            $proposals[] = new SupplyProposalData(
                index: $index,
                utility_type: $supply->utility_type,
                supply_point_code: $supply->supply_point_code,
                amount: $supply->amount,
                heading: $this->heading($supply->utility_type, $supply->supply_point_code, $locale),
                record: $record,
                movement: $budget ? $this->supplyMovement($view, $context, $utility, $own, $side['date'], $locale) : null,
                consumption: $budget ? $this->consumptionOf($view, $utility, $created, $own, supply: true) : null,
            );
        }

        return $proposals;
    }

    /**
     * The expense of one supply, ticked by the same rule as the expense of a bill alone.
     *
     * @param  Extraction  $view  the reading seen from the supply
     * @param  ReadingContext  $context  the lists the model chose from
     * @param  Utility|null  $utility  the utility of the supply
     * @param  ImportChoices  $own  the choices of the supply
     * @param  CarbonImmutable|null  $date  the date of the document as it will be saved
     * @param  string  $locale  the language of the texts
     */
    private function supplyMovement(Extraction $view, ReadingContext $context, ?Utility $utility, ImportChoices $own, ?CarbonImmutable $date, string $locale): MovementProposalData
    {
        $values = $this->drafts->forSupply($view, $context, $utility, $locale);

        return $this->movements->of($view, $context, $values, MovementDraft::ticks($view, $values), $own, $date);
    }

    /**
     * The title of the group of a supply: the type and the code, POD for electricity and PDR for gas («Luce · POD IT
     * 001 E 87654321»); the type alone without a code, the code alone without a type.
     *
     * @param  string|null  $type  a value of `UtilityType`
     * @param  string|null  $code  the supply code as printed
     * @param  string  $locale  the language of the type
     */
    private function heading(?string $type, ?string $code, string $locale): string
    {
        $code = $code === null || trim($code) === '' ? null : trim($code);
        $prefix = match ($type) {
            UtilityType::Electricity->value => 'POD ',
            UtilityType::Gas->value => 'PDR ',
            default => '',
        };

        return implode(' · ', array_filter([
            $type === null ? null : BillTitle::typeLabel($type, $locale),
            $code === null ? null : $prefix.$code,
        ]));
    }

    /**
     * The consumption of a bill of electricity, gas or water, with the corrections of the person: only for a utility
     * bill whose reading found a consumption. The utility is the first of electricity, gas or water among the links of
     * the document (as the page sends them, otherwise as the reading proposes them), otherwise the one of the box of
     * the utility, ticked: found, or created with the file. Without either, the type read decides the unit and the
     * person is asked to choose the utility above.
     *
     * @param  Extraction  $x  what the model read
     * @param  DocumentProposalData  $document  the document the reading proposes, with the cards it links
     * @param  list<RecordDraftData>  $records  the boxes of the cards
     * @param  ImportChoices  $choices  the choices of the person
     */
    private function consumption(Extraction $x, DocumentProposalData $document, array $records, ImportChoices $choices): ?ConsumptionProposalData
    {
        // Before looking for the utility: most papers are not a bill with a consumption
        if (! $this->readsConsumption($x)) {
            return null;
        }

        $links = array_key_exists('links', $choices->document) ? DocumentRules::links($choices->document['links']) : $document->links;
        $ids = array_column(array_filter($links, static fn (array $link): bool => $link['type'] === 'utility'), 'id');
        $utility = $ids === [] ? null : Utility::query()->whereKey($ids)->orderBy('id')->get()
            ->first(static fn (Utility $card): bool => ConsumptionUnit::forUtility($card->type) instanceof ConsumptionUnit);

        // The box of the utility, ticked, gives it: one found is linked, a new one is created with the file
        $draft = collect($records)->first(static fn (RecordDraftData $draft): bool => $draft->kind === RecordKind::Utility && in_array(RecordKind::Utility, $choices->records, true));
        $utility ??= $draft instanceof RecordDraftData && $draft->existing !== null ? Utility::query()->find($draft->existing['id']) : null;

        return $this->consumptionOf($x, $utility, $utility instanceof Utility ? null : $this->createdType($draft, $draft instanceof RecordDraftData), $choices);
    }

    /**
     * Whether the reading is of a utility bill that found a consumption: only that proposes one.
     *
     * @param  Extraction  $x  what the model read, or the reading seen from one supply
     */
    private function readsConsumption(Extraction $x): bool
    {
        return $x->kind === 'utility_bill' && ($x->consumption_quantity !== null || $x->consumption_unit !== null);
    }

    /**
     * The type of the utility a ticked box creates with the file; null for a box that links one, or not ticked.
     *
     * @param  RecordDraftData|null  $draft  the box of the utility
     * @param  bool  $ticked  whether the box is ticked
     */
    private function createdType(?RecordDraftData $draft, bool $ticked): ?UtilityType
    {
        if (! $draft instanceof RecordDraftData || $draft->existing !== null || ! $ticked) {
            return null;
        }

        return UtilityType::tryFrom(is_string($draft->values['type'] ?? null) ? $draft->values['type'] : '');
    }

    /**
     * The consumption of a bill with the utility it goes to, only for a utility bill whose reading found a
     * consumption: one already there, or the type of one created with the file; without either the type read decides the unit and the person is asked to choose the utility. The amount
     * is the one of the expense, with the correction of the person, for the price per unit.
     *
     * @param  Extraction  $x  what the model read, or the reading seen from one supply
     * @param  Utility|null  $utility  the utility already there
     * @param  UtilityType|null  $created  the type of the utility created with the file
     * @param  ImportChoices  $choices  the choices of the person, or of the supply
     * @param  bool  $supply  the consumption of one supply of a bill with more than one: without a utility, its box asks to tick the utility of the supply
     */
    private function consumptionOf(Extraction $x, ?Utility $utility, ?UtilityType $created, ImportChoices $choices, bool $supply = false): ?ConsumptionProposalData
    {
        if (! $this->readsConsumption($x)) {
            return null;
        }

        $type = $utility->type ?? $created ?? UtilityType::tryFrom($x->utility_type ?? '');
        $unit = $type instanceof UtilityType ? ConsumptionUnit::forUtility($type) : null;
        if (! $type instanceof UtilityType || ! $unit instanceof ConsumptionUnit) {
            return null;
        }

        $edits = $choices->consumptionOverrides();
        $movementAmount = $choices->overrides['movement']['amount'] ?? null;
        $amount = is_numeric($movementAmount) ? (float) $movementAmount : $x->total_amount;
        $quantity = $edits['quantity'] ?? $x->consumption_quantity;
        $quantity = is_numeric($quantity) ? (float) $quantity : null;
        $values = new ConsumptionValuesData(
            period_start: is_string($edits['period_start'] ?? null) ? $edits['period_start'] : ValidDate::of($x->period_start),
            period_end: is_string($edits['period_end'] ?? null) ? $edits['period_end'] : ValidDate::of($x->period_end),
            quantity: $quantity,
            unit: $unit->value,
            amount: $amount,
            is_estimated: is_bool($edits['is_estimated'] ?? null) ? $edits['is_estimated'] : $x->consumption_estimated ?? false,
        );

        $complete = $utility instanceof Utility || $created instanceof UtilityType ? [] : ['utility'];
        $missing = array_values(array_filter([
            $values->quantity === null ? 'quantity' : null,
            $values->period_start === null ? 'period_start' : null,
            $values->period_end === null ? 'period_end' : null,
        ]));
        // The check with the unit of the utility rates only quantity and period: a value out of range or a period
        // reversed or too long is written again in the box, like a missing one
        $rated = $missing === [] ? ConsumptionCheck::outcome($type, $values->quantity, $unit->value, $values->period_start, $values->period_end) : null;
        $fill = match ($rated) {
            ConsumptionOutcome::OutOfRange => ['quantity'],
            ConsumptionOutcome::BadPeriod => ['period_start', 'period_end'],
            default => $missing,
        };
        $blocked = match (true) {
            $complete !== [] => $supply ? ConsumptionProposalData::tickSupplyUtility() : ConsumptionProposalData::chooseUtility(),
            $missing !== [] => Translated::text('To record the consumption write the quantity and the period in its box.'),
            $rated === ConsumptionOutcome::OutOfRange => Translated::text('To record the consumption correct the quantity in its box.'),
            $rated === ConsumptionOutcome::BadPeriod => Translated::text('To record the consumption correct the period in its box: the first day comes before the last, and it lasts at most 400 days.'),
            default => null,
        };
        $saved = ConsumptionCheck::outcome($type, $values->quantity, $x->consumption_unit, $values->period_start, $values->period_end) === ConsumptionOutcome::Saved;

        return new ConsumptionProposalData(
            values: $values,
            suggested: $saved && $blocked === null,
            utility: $utility instanceof Utility ? ['id' => $utility->id, 'title' => $utility->label()] : null,
            blocked: $blocked,
            fill: $fill,
            complete: $complete,
            priceWarning: $amount !== null && $values->quantity !== null && ConsumptionCheck::priceOutOfRange($unit, $amount, $values->quantity),
        );
    }

    /**
     * The choices with the movement the file was uploaded from as the target left on `auto`, when it is still there
     * in the family and of the kind the reading proposes: the document goes to that expense or income, no new one.
     *
     * @param  File  $file  the file
     * @param  string  $kind  `expense` or `income`, the kind of the movement the reading proposes
     * @param  ImportChoices  $choices  the choices of the person
     */
    private function fromMovement(File $file, string $kind, ImportChoices $choices): ImportChoices
    {
        if ($choices->targets['movement'] !== 'auto' || $file->link_type !== $kind || $file->link_id === null) {
            return $choices;
        }

        $exists = $kind === 'income' ? Income::query()->whereKey($file->link_id)->exists() : Expense::query()->whereKey($file->link_id)->exists();

        return $exists ? $choices->withMovementTarget($file->link_id) : $choices;
    }

    /**
     * The document category that decides the boxes: the one the person chose in *What kind it is*, otherwise the one
     * the reading proposes.
     *
     * @param  DocumentProposalData  $document  the document the reading proposes
     * @param  ImportChoices  $choices  the choices of the person
     */
    private function categoryId(DocumentProposalData $document, ImportChoices $choices): ?int
    {
        if (! array_key_exists('category_id', $choices->document)) {
            return $document->category_id;
        }

        $chosen = $choices->document['category_id'];

        return is_numeric($chosen) ? (int) $chosen : null;
    }

    /**
     * The health event the reading proposes; with the card *Health event* (the box is always there) one without a
     * title read takes the title of the document.
     *
     * @param  EventValuesData|null  $event  the event the reading proposes
     * @param  string|null  $cardType  the card of the document category
     * @param  DocumentProposalData  $document  the document, whose title the event takes
     */
    private function healthEvent(?EventValuesData $event, ?string $cardType, DocumentProposalData $document): ?EventValuesData
    {
        return $cardType === 'health_event' && $event instanceof EventValuesData && $this->blank($event->title)
            ? EventValuesData::from([...$event->toArray(), 'title' => $document->title])
            : $event;
    }

    /**
     * The document of a payment receipt linked to an expense already recorded, in the category of the first document
     * of that expense that has one; any other document as the reading proposes it.
     *
     * @param  Extraction  $x  what the model read
     * @param  DocumentProposalData  $document  the document the reading proposes
     * @param  MovementProposalData|null  $movement  the movement side, with the expense it is linked to
     */
    private function receiptCategory(Extraction $x, DocumentProposalData $document, ?MovementProposalData $movement): DocumentProposalData
    {
        $expenseId = $movement?->values->kind === 'expense' ? ($movement->duplicate['id'] ?? null) : null;

        if ($x->kind !== 'payment_receipt' || $expenseId === null) {
            return $document;
        }

        $category = Expense::query()->find($expenseId)?->documents()
            ->whereNotNull('documents.category_id')
            ->orderBy('documents.id')
            ->value('documents.category_id');

        return is_numeric($category) ? DocumentProposalData::from([...$document->toArray(), 'category_id' => (int) $category]) : $document;
    }

    /**
     * The health event: person, place and date are the document's, the rest the reading's with the corrections.
     * Linking to an event needs nothing; a new one needs person, type, date and title.
     *
     * @param  EventValuesData  $draft  the event the reading proposes
     * @param  bool  $suggested  whether its box starts ticked
     * @param  array{organization_id: ?int, organization_name: ?string, person_id: ?int, date: ?CarbonImmutable}  $document  the document as it will be saved
     * @param  ImportChoices  $choices  the choices of the person
     */
    private function event(EventValuesData $draft, bool $suggested, array $document, ImportChoices $choices): EventProposalData
    {
        $values = EventValuesData::from([
            ...$draft->toArray(),
            ...$choices->overrides['event'],
            'person_id' => $document['person_id'],
            'organization_id' => $document['organization_id'],
            'date' => $document['date']?->toDateString(),
        ]);
        $day = $document['date'];
        $target = $choices->targets['event'];
        $picked = is_int($target) ? HealthEvent::query()->find($target) : null;

        // One picked and gone is looked for again: the confirmation tells the person
        $existing = match (true) {
            $picked instanceof HealthEvent => $picked,
            $target === 'new', $values->person_id === null, ! $day instanceof CarbonImmutable => null,
            default => $this->search->event($values->person_id, $day, $values->organization_id, $values->type),
        };

        $complete = $existing instanceof HealthEvent ? [] : array_values(array_filter([
            $values->person_id === null ? 'person_id' : null,
            $values->date === null ? 'date' : null,
        ]));
        $fill = $existing instanceof HealthEvent ? [] : array_values(array_filter([
            $this->blank($values->type) ? 'type' : null,
            $this->blank($values->title) ? 'title' : null,
        ]));
        $lacking = $existing instanceof HealthEvent ? [] : array_values(array_filter([
            $values->person_id === null ? Translated::text('the person') : null,
            $this->blank($values->type) ? Translated::text('the type') : null,
            $values->date === null ? Translated::text('the date') : null,
            $this->blank($values->title) ? Translated::text('the title') : null,
        ]));

        return new EventProposalData(
            values: $values,
            suggested: $suggested,
            existing: $existing instanceof HealthEvent ? $this->named($existing) : null,
            blocked: $lacking === []
                ? null
                : trans_choice('{1} To create the health event :fields is missing.|[2,*] To create the health event :fields are missing.', count($lacking), ['fields' => implode(', ', $lacking)]),
            candidates: $values->person_id === null ? [] : $this->events($values->person_id, $day ?? DueSearch::today()),
            // The import fills the next checkup of an event already there only when it has none: then the reminder is born too
            reminder: $values->next_checkup_date !== null && (! $existing instanceof HealthEvent || $existing->next_checkup_date === null)
                ? Translated::text("I'll remind you to book the checkup")
                : null,
            fill: $fill,
            complete: $complete,
        );
    }

    /**
     * The policy of an insurance paper: one without a number, that neither finds nor proposes a policy, takes the one
     * of another file confirmed with it, otherwise the only registered one that fits.
     *
     * @param  Extraction  $x  what the model read
     * @param  DocumentProposalData  $document  the document the reading proposes, with the policy it found
     * @param  array{organization_id: ?int, organization_name: ?string, person_id: ?int, date: ?CarbonImmutable}  $side  the document as it will be saved
     * @param  list<RecordDraftData>  $records  the boxes of the cards
     * @param  ImportChoices  $choices  the choices of the person
     * @param  list<PolicyGiverData>  $roundGivers  the other files that give a policy
     * @return array{need: bool, giver: ?PolicyGiverData, many: bool}|null null for a paper that is not an insurance one
     */
    private function policy(Extraction $x, DocumentProposalData $document, array $side, array $records, ImportChoices $choices, array $roundGivers): ?array
    {
        if ($x->kind !== 'insurance') {
            return null;
        }

        $linked = array_filter($document->links, static fn (array $link): bool => $link['type'] === 'insurance_policy') !== [];
        $proposed = array_filter($records, static fn (RecordDraftData $draft): bool => $draft->kind === RecordKind::Policy) !== [];
        if (! $this->blank($x->policy_number) || $linked || $proposed) {
            return ['need' => false, 'giver' => null, 'many' => false];
        }

        $created = $choices->overrides['records'][RecordKind::Organization->value]['name'] ?? null;
        $need = [
            'organizationId' => $side['organization_id'],
            'organizationName' => $side['organization_name'] ?? (is_string($created) ? $created : null) ?? $x->issuer_name,
            'personId' => $side['person_id'],
            'type' => $x->insurance_type,
        ];
        $giver = $this->policies->resolve($need, $roundGivers, $choices->policy);
        $fitting = $this->policies->matching($need, $roundGivers) ?: $this->policies->matching($need, $this->policies->registered());

        return ['need' => true, 'giver' => $giver, 'many' => ! $giver instanceof PolicyGiverData && $choices->policy === 'auto' && count($fitting) > 1];
    }

    /**
     * The document as it will be saved, with the corrections of the person: its issuer, person and date are those of
     * the event and the cards too.
     *
     * @param  DocumentProposalData  $document  the document the reading proposes
     * @param  ReadingContext  $context  the lists whose label names the issuer
     * @param  ImportChoices  $choices  the choices of the person
     * @return array{organization_id: ?int, organization_name: ?string, person_id: ?int, date: ?CarbonImmutable}
     */
    private function documentSide(DocumentProposalData $document, ReadingContext $context, ImportChoices $choices): array
    {
        $fields = $choices->document;
        $id = static fn (string $field, ?int $read): ?int => array_key_exists($field, $fields)
            ? (is_numeric($fields[$field]) ? (int) $fields[$field] : null)
            : $read;
        $organization = $id('organization_id', $document->organization_id);
        $date = array_key_exists('date', $fields) ? (is_string($fields['date']) ? ValidDate::of($fields['date']) : null) : $document->date;

        return [
            'organization_id' => $organization,
            'organization_name' => Choices::label($context->organizations, $organization),
            'person_id' => $id('person_id', $document->person_id),
            'date' => ValidDate::carbon($date),
        ];
    }

    /**
     * The events of the person within a month of the date, the closest first, to pick one by hand.
     *
     * @param  int  $personId  the patient
     * @param  CarbonImmutable  $day  the date of the paper
     * @return list<array{id: int, title: string, date: string}>
     */
    private function events(int $personId, CarbonImmutable $day): array
    {
        $events = HealthEvent::query()
            ->where('person_id', $personId)
            ->whereBetween('date', [$day->subDays(self::EVENT_CANDIDATE_DAYS)->toDateString(), $day->addDays(self::EVENT_CANDIDATE_DAYS)->toDateString()])
            ->orderBy('id')
            ->get()
            ->sortBy(static fn (HealthEvent $event): int => IssuerOrder::daysApart($event->date, $day), SORT_NUMERIC)
            ->take(self::EVENT_CANDIDATE_LIMIT)
            ->map($this->named(...));

        return array_values($events->all());
    }

    /**
     * An event as the page names it.
     *
     * @param  HealthEvent  $event  the event
     * @return array{id: int, title: string, date: string}
     */
    private function named(HealthEvent $event): array
    {
        return ['id' => $event->id, 'title' => $event->title, 'date' => $event->date->toDateString()];
    }

    /**
     * Whether the paper names no card, so the person is offered to create one (a work contract, an enrolment): no
     * card to create or to link (an organization is not a card) and no health event, which is a card too.
     *
     * @param  DocumentProposalData  $document  the document, with the cards it links
     * @param  EventProposalData|null  $event  the health event
     * @param  list<RecordDraftData>  $records  the boxes of the cards
     */
    private function namesNoCard(DocumentProposalData $document, ?EventProposalData $event, array $records): bool
    {
        $cards = array_filter($records, static fn (RecordDraftData $draft): bool => $draft->kind !== RecordKind::Organization);

        return ! $event instanceof EventProposalData && $cards === [] && $document->links === [];
    }

    /**
     * Whether a text is missing or empty.
     *
     * @param  string|null  $value  the text
     */
    private function blank(?string $value): bool
    {
        return $value === null || trim($value) === '';
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Reading;

use App\Data\Reading\DocumentProposalData;
use App\Data\Reading\EventProposalData;
use App\Data\Reading\EventValuesData;
use App\Data\Reading\Extraction;
use App\Data\Reading\ImportChoices;
use App\Data\Reading\MovementProposalData;
use App\Data\Reading\PolicyGiverData;
use App\Data\Reading\ProposalData;
use App\Data\Reading\RecordDraftData;
use App\Enums\RecordKind;
use App\Models\Expense;
use App\Models\File;
use App\Models\HealthEvent;
use App\Support\CurrentFamily;
use App\Support\Reading\Choices;
use App\Support\Reading\IssuerOrder;
use App\Support\Reading\ValidDate;
use App\Support\Translated;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\App;

/**
 * What a read file would do when confirmed (`previewFile` of the old reader): the document, the expense or income
 * ({@see MovementProposal}), the health event, the missing cards ({@see RecordsProposal}) and the policy taken from
 * another file or from one registered. It works on the reading saved in `files.reading`, inside the family of the
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
     */
    public function __construct(
        private DocumentProposal $documents,
        private MovementDraft $drafts,
        private MovementProposal $movements,
        private RecordsProposal $records,
        private RecordSearch $search,
        private PolicyFromOtherFiles $policies,
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
        $context = ReadingContext::for($file);
        $document = $this->documents->of($x, $context, $locale);
        $draft = $this->drafts->of($x, $context, $locale);
        $side = $this->documentSide($document, $context, $choices);

        $movement = $draft['movement'] === null
            ? null
            : $this->movements->of($x, $context, $draft['movement'], $draft['movementSuggested'], $choices);
        $document = $this->receiptCategory($x, $document, $movement);
        $amount = $choices->movementOverrides($movement->values->kind ?? 'expense')['amount'] ?? $x->total_amount;
        ['records' => $records, 'warnings' => $warnings] = $this->records->of($x, $side, is_numeric($amount) ? (float) $amount : null, $choices);
        $event = $draft['event'] === null ? null : $this->event($draft['event'], $draft['eventSuggested'], $side, $choices);

        return new ProposalData(
            fileId: $file->id,
            document: $document,
            movement: $movement,
            event: $event,
            records: $records,
            policy: $this->policy($x, $document, $side, $records, $choices, $roundGivers),
            warnings: $warnings,
            createCard: $this->namesNoCard($document, $event, $records),
        );
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

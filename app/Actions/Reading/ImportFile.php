<?php

declare(strict_types=1);

namespace App\Actions\Reading;

use App\Actions\Budget\ChangeOccurrence;
use App\Actions\Budget\MovePlannedDocuments;
use App\Actions\Budget\SaveMovement;
use App\Actions\Budget\SavePlanned;
use App\Actions\Cards\ReplacePolicy;
use App\Actions\Consumption\ConsumptionValues;
use App\Actions\Consumption\SaveConsumption;
use App\Actions\Documents\FileDocument;
use App\Data\Reading\ConsumptionProposalData;
use App\Data\Reading\DueData;
use App\Data\Reading\EventProposalData;
use App\Data\Reading\Extraction;
use App\Data\Reading\ImportChoices;
use App\Data\Reading\MovementProposalData;
use App\Data\Reading\MovementValuesData;
use App\Data\Reading\PolicyGiverData;
use App\Data\Reading\ProposalData;
use App\Data\Reading\RecordDraftData;
use App\Data\Reading\SupplyProposalData;
use App\Data\ReplaceablePolicyData;
use App\Enums\Area;
use App\Enums\ConsumptionSource;
use App\Enums\ConsumptionUnit;
use App\Enums\DocumentStatus;
use App\Enums\DueMode;
use App\Enums\RecordKind;
use App\Models\Document;
use App\Models\Expense;
use App\Models\File;
use App\Models\HealthEvent;
use App\Models\Income;
use App\Models\InsurancePolicy;
use App\Models\PlannedExpense;
use App\Models\Utility;
use App\Models\UtilityConsumption;
use App\Services\Deadlines;
use App\Services\FamilyAreas;
use App\Services\Reading\ExpenseChanges;
use App\Services\Reading\Matcher;
use App\Services\Reading\Proposal;
use App\Services\Reading\ReadingContext;
use App\Services\ReplaceablePolicies;
use App\Support\CurrentFamily;
use App\Support\DocumentRules;
use App\Support\PaymentTitle;
use App\Support\PlannedKind;
use App\Support\Reading\ImportErrors;
use App\Support\Reading\RecordLabel;
use App\Support\Translated;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Saves one read file with what the person confirmed (`importFile` of the old reader), in one transaction: an
 * exception leaves nothing of the file. The order is the old one:
 *
 * 1. the cards the document names (organization, vehicle, utility, policy, belonging, the card of the document
 *    category), {@see SaveRecords};
 * 2. the document, linked to them and to the policy taken from another file;
 * 3. the expense or income, or the expense to pay, or the payment of a due date;
 * 4. the health event;
 * 4b. the consumption of a bill of electricity, gas or water, in the row of its utility ({@see SaveConsumption});
 * 5. the intervention, with the expense;
 * 6. the old policies the new policy replaces, ticked under its box ({@see ReplacePolicy}): after the movement, so a
 *    premium the file just planned for the new policy counts.
 *
 * A bill with more than one supply (a dual-fuel bill) saves one document for all of them: the utility of each supply
 * whose box is ticked is linked or created in step 1, the document is linked to all of them, and steps 3 and 4b are
 * done for each supply with its own choices, its expense and its consumption only on its utility linked to the
 * document (a supply whose utility box is unticked has none: its consumption is refused, as for a bill alone).
 *
 * The proposal it starts from is the one the request checked, when the caller passes it and the paper takes no
 * policy from another file (the policies are the only thing the other files change); otherwise it is made here.
 * When cards are saved in step 1 the proposal is made again, as if the reading had found them (as the old reader
 * did): the movement and the event of steps 3 and 4 come from that one, so with a new card they can link an expense
 * or a commitment the person did not see in the page. An organization created or linked fills the menus
 * *Organization* left empty. A `member` creates all this too: the import is allowed by `FilePolicy::import`, not by
 * the policies of each model.
 *
 * The file is locked and read again first: one no longer waiting is refused, so the action is safe also when called
 * alone. The proposal passed by the caller was made before that lock: two imports at the same time of two different
 * files of the same new issuer may both create the organization (a known, small window).
 */
final readonly class ImportFile
{
    /** The cards a new expense or planned expense refers to, in the order the first is taken from the document. */
    private const array MOVEMENT_CARDS = ['utility', 'insurance_policy', 'vehicle', 'subscription'];

    /** The columns of an expense its commitment fills when they are empty ({@see PlannedKind::paymentColumns()}). */
    private const array PLANNED_GIVES = ['organization_id', 'financial_account_id', 'person_id', 'category_id', 'card_type', 'card_id', 'fee_amount'];

    /**
     * @param  Proposal  $proposals  what the file does
     * @param  SaveRecords  $records  the cards
     * @param  FileDocument  $fileDocument  the document
     * @param  SaveMovement  $saveMovement  the expense or income
     * @param  SavePlanned  $savePlanned  the expense to pay
     * @param  ChangeOccurrence  $changeOccurrence  the due date of a commitment that changes
     * @param  MovePlannedDocuments  $movePlannedDocuments  the documents of the planned expense paid
     * @param  ExpenseChanges  $expenseChanges  what the expense already recorded takes from the file
     * @param  Matcher  $matcher  the policy taken from another file, by its number
     * @param  Deadlines  $deadlines  whether a due date is paid
     * @param  FamilyAreas  $areas  the areas the family hides, put back when a card is created in one
     * @param  ReplaceablePolicies  $replaceable  the old policies a new one can replace
     * @param  ReplacePolicy  $replacePolicy  cancels the old policies
     * @param  SaveConsumption  $saveConsumption  the consumption of a utility bill
     */
    public function __construct(
        private Proposal $proposals,
        private SaveRecords $records,
        private FileDocument $fileDocument,
        private SaveMovement $saveMovement,
        private SavePlanned $savePlanned,
        private ChangeOccurrence $changeOccurrence,
        private MovePlannedDocuments $movePlannedDocuments,
        private ExpenseChanges $expenseChanges,
        private Matcher $matcher,
        private Deadlines $deadlines,
        private FamilyAreas $areas,
        private ReplaceablePolicies $replaceable,
        private ReplacePolicy $replacePolicy,
        private SaveConsumption $saveConsumption,
    ) {}

    /**
     * Saves the file in its family.
     *
     * @param  File  $file  the file, waiting in *To sort*
     * @param  ImportChoices  $choices  what the person confirmed, the document fields validated
     * @param  list<PolicyGiverData>  $roundGivers  the other files confirmed with this one that give a policy
     * @param  ProposalData|null  $checked  the proposal the request made with the same choices, without the other files; only while nothing was saved since
     * @param  list<array{type: string, id: int}>  $roundCards  the cards the files saved before in this import created
     * @return array{document: Document, movement: Expense|Income|PlannedExpense|null, event: ?HealthEvent, records: array<string, Model>, warnings: list<string>}
     *
     * @throws ValidationException if the file no longer waits, a ticked box misses what it needs, or a due date is already paid
     * @throws Throwable if anything else fails: nothing of the file is saved
     */
    public function handle(File $file, ImportChoices $choices, array $roundGivers, ?ProposalData $checked = null, array $roundCards = []): array
    {
        return CurrentFamily::using($file->family_id, fn (): array => DB::transaction(function () use ($file, $choices, $roundGivers, $checked, $roundCards): array {
            $waiting = File::query()->whereKey($file->id)->lockForUpdate()->value('to_sort');
            if (! (bool) $waiting) {
                throw ValidationException::withMessages(['file_id' => __('This file was already filed.')]);
            }

            $first = $checked instanceof ProposalData && ! ($checked->policy['need'] ?? false)
                ? $checked
                : $this->proposals->for($file, $choices, $roundGivers, App::getLocale());

            return $this->save($file, $choices, $roundGivers, $first, $roundCards);
        }));
    }

    /**
     * The five steps.
     *
     * @param  File  $file  the file
     * @param  ImportChoices  $choices  what the person confirmed
     * @param  list<PolicyGiverData>  $roundGivers  the other files that give a policy
     * @param  ProposalData  $first  the proposal before anything is saved
     * @param  list<array{type: string, id: int}>  $roundCards  the cards the files saved before in this import created
     * @return array{document: Document, movement: Expense|Income|PlannedExpense|null, event: ?HealthEvent, records: array<string, Model>, warnings: list<string>}
     *
     * @throws ValidationException if a ticked box misses what it needs, or a due date is already paid
     */
    private function save(File $file, ImportChoices $choices, array $roundGivers, ProposalData $first, array $roundCards): array
    {
        $locale = App::getLocale();
        $hiddenBefore = $this->areas->refresh()->hidden();

        $before = $this->records->before($first->records, $choices->records);
        if ($before['organization'] !== null) {
            $choices = $choices->withOrganization($before['organization']);
        }

        $proposal = $before['records'] === [] ? $first : $this->proposals->for($file, $choices, $roundGivers, $locale);
        [$policy, $warnings] = $this->policy($first->policy['giver'] ?? null);

        // The form was filled before the other files of the import were saved: the cards they created, which this file
        // names too (a second paper with the number of the policy the first one created), are not in it
        $found = array_values(array_filter($proposal->document->links, static fn (array $link): bool => in_array($link, $roundCards, true)));
        $utilities = $this->supplyUtilities($first, $choices, $before['ids']);
        $supplyLinks = array_values(array_map(static fn (Utility $utility): array => ['type' => 'utility', 'id' => $utility->id], array_filter($utilities)));
        $unlinked = [...$this->unticked($first, $choices), ...$this->untickedSupplies($first, $supplyLinks)];
        $document = $this->document($file, $proposal, $choices, [...$before['links'], ...$found, ...$supplyLinks], $policy, $unlinked);
        $x = Extraction::ofFile($file);
        $movement = $choices->movement && $proposal->movement instanceof MovementProposalData
            ? $this->movement($x, $file, $proposal->movement, $choices, $document, $this->ownCard($first, $before['records']))
            : null;
        $expense = $movement instanceof Expense ? $movement : null;
        $event = $choices->event && $proposal->event instanceof EventProposalData ? $this->event($proposal->event, $document, $expense) : null;
        if ($proposal->consumption instanceof ConsumptionProposalData && ($choices->consumption ?? $proposal->consumption->suggested)) {
            $utility = $this->proposedUtility($proposal->consumption) ?? $document->utilities()->orderBy('utilities.id')->get()
                ->first(static fn (Utility $card): bool => ConsumptionUnit::forUtility($card->type) instanceof ConsumptionUnit);
            $this->consumption($x, $proposal->consumption, $document, $utility);
        }
        $this->supplies($x, $file, $proposal->supplies, $choices, $document, $utilities);
        $after = $this->records->after($first->records, $choices->records, $before['ids'], $document, $expense);
        $this->replacePolicies($first, $choices, $before['records'][RecordKind::Policy->value] ?? null);

        // Read again: SaveCard puts an area back through its own instance
        $hiddenAfter = $this->areas->refresh()->hidden();
        foreach (array_filter($hiddenBefore, static fn (Area $area): bool => ! in_array($area, $hiddenAfter, true)) as $area) {
            $warnings[] = __('I put :area back among the areas.', ['area' => $area->label()]);
        }

        return ['document' => $document, 'movement' => $movement, 'event' => $event, 'records' => [...$before['records'], ...$after], 'warnings' => $warnings];
    }

    /**
     * Step 1 for a bill with more than one supply: the utility of each supply whose box is ticked (as sent, otherwise as
     * proposed), linked or created.
     *
     * @param  ProposalData  $first  the proposal before anything is saved
     * @param  ImportChoices  $choices  what the person confirmed
     * @param  array<string, int>  $ids  the cards saved before, by kind
     * @return array<int, ?Utility> the utility of each supply by its index, null for a box not ticked
     *
     * @throws ValidationException if a new utility misses a required field
     * @throws ModelNotFoundException if a utility to link is no longer there
     */
    private function supplyUtilities(ProposalData $first, ImportChoices $choices, array $ids): array
    {
        $utilities = [];
        foreach ($first->supplies as $supply) {
            $box = $supply->record;
            $ticked = $box instanceof RecordDraftData && $choices->supplyRecordTicked($supply->index, $box->suggested);
            $card = $ticked ? $this->records->card($box, $ids) : null;
            $utilities[$supply->index] = $card instanceof Utility ? $card : null;
        }

        return $utilities;
    }

    /**
     * The utilities the proposal links the document to for its supplies whose box was unticked: the box decides.
     *
     * @param  ProposalData  $first  the proposal the person saw
     * @param  list<array{type: string, id: int}>  $saved  the utilities of the supplies saved with the file
     * @return list<array{type: string, id: int}>
     */
    private function untickedSupplies(ProposalData $first, array $saved): array
    {
        if ($first->supplies === []) {
            return [];
        }

        return array_values(array_filter($first->document->links, static fn (array $link): bool => $link['type'] === 'utility' && ! in_array($link, $saved, true)));
    }

    /**
     * Steps 3 and 4b for each supply of a bill with more than one, with its own choices: its expense (or its due date
     * to pay) referring to its utility, and its consumption in the row of its utility, the one saved with the file
     * and linked to the document. Every planned expense the supplies touch (the one that leads an expense, the one
     * whose due date is to pay, the one a receipt pays) is locked first, by ascending id, as the bank payments lock
     * them: `SaveMovement` and the exception of a due date lock them again later, which in the same transaction does
     * not wait, so no lock is ever taken in the order of the supplies.
     *
     * @param  Extraction  $x  what the model read
     * @param  File  $file  the file
     * @param  list<SupplyProposalData>  $supplies  the supplies of the proposal, made again after the cards
     * @param  ImportChoices  $choices  what the person confirmed
     * @param  Document  $document  the document of the file
     * @param  array<int, ?Utility>  $utilities  the utility of each supply saved with the file, by index
     *
     * @throws ValidationException if a ticked box misses what it needs, or a due date is already paid
     */
    private function supplies(Extraction $x, File $file, array $supplies, ImportChoices $choices, Document $document, array $utilities): void
    {
        $planned = [];
        foreach ($supplies as $supply) {
            $own = $choices->forSupply($supply->index);
            if ($own->movement && $supply->movement instanceof MovementProposalData) {
                $planned[] = $supply->movement->values->lead['id'] ?? null;
                $planned[] = $supply->movement->due->plan['id'] ?? null;
                $planned[] = $supply->movement->pays['planned'] ?? null;
            }
        }
        $planned = array_values(array_unique(array_filter($planned, is_int(...))));
        if ($planned !== []) {
            PlannedExpense::query()->whereKey($planned)->orderBy('id')->lockForUpdate()->get();
        }

        foreach ($supplies as $supply) {
            $own = $choices->forSupply($supply->index);
            $view = $x->forSupply($supply->index);
            $utility = $utilities[$supply->index] ?? null;

            if ($own->movement && $supply->movement instanceof MovementProposalData) {
                $card = $utility instanceof Utility ? ['card_type' => 'utility', 'card_id' => $utility->id] : [];
                $this->movement($view, $file, $supply->movement, $own, $document, $card, $card);
            }

            if ($supply->consumption instanceof ConsumptionProposalData && ($own->consumption ?? $supply->consumption->suggested)) {
                $this->consumption($view, $supply->consumption, $document, $utility);
            }
        }
    }

    /**
     * Step 6: the old policies ticked under the box of the new policy are cancelled, only those the box proposed and
     * that still are replaceable by the new one (read again: one changed or no longer editable since is left alone).
     * Nothing for a policy the box linked instead of creating.
     *
     * @param  ProposalData  $first  the proposal the person saw
     * @param  ImportChoices  $choices  what the person confirmed
     * @param  Model|null  $policy  the policy saved with the file
     */
    private function replacePolicies(ProposalData $first, ImportChoices $choices, ?Model $policy): void
    {
        $draft = collect($first->records)->first(static fn (RecordDraftData $draft): bool => $draft->kind === RecordKind::Policy);
        if (! $policy instanceof InsurancePolicy || ! $draft instanceof RecordDraftData || $draft->existing !== null || $choices->replacePolicies === []) {
            return;
        }

        $ids = array_intersect($choices->replacePolicies, array_map(static fn (ReplaceablePolicyData $old): int => $old->id, $draft->replaces));
        $old = $this->replaceable->matching([
            'type' => $policy->type->value,
            'vehicle_id' => $policy->vehicle_id,
            'home_id' => $policy->home_id,
            'employment_id' => $policy->employment_id,
        ], $policy->id)->filter(static fn (InsurancePolicy $candidate): bool => in_array($candidate->id, $ids, true))->values();

        if ($old->isNotEmpty()) {
            $this->replacePolicy->handle($policy, $old);
        }
    }

    /**
     * The card of the document category the file created or linked with its box, which a new expense refers to
     * before the cards the document is linked to.
     *
     * @param  ProposalData  $first  the proposal before anything is saved
     * @param  array<string, Model>  $records  the cards saved before the document, by kind
     * @return array{card_type?: string, card_id?: int}
     */
    private function ownCard(ProposalData $first, array $records): array
    {
        $type = $first->cardType;
        $card = $type === null ? null : $records[RecordKind::forCardType($type)->value] ?? null;

        return $type !== null && $card instanceof Model ? ['card_type' => $type, 'card_id' => RecordLabel::id($card)] : [];
    }

    /**
     * The card of the document category the box found but the person unticked: the box decides, so the document is
     * not linked to it through the links proposed with the form.
     *
     * @param  ProposalData  $first  the proposal the person saw
     * @param  ImportChoices  $choices  what the person confirmed
     * @return list<array{type: string, id: int}>
     */
    private function unticked(ProposalData $first, ImportChoices $choices): array
    {
        $links = [];
        foreach ($first->records as $draft) {
            if ($first->cardType !== null && $draft->cardType === $first->cardType && $draft->existing !== null && ! in_array($draft->kind, $choices->records, true)) {
                $links[] = ['type' => $first->cardType, 'id' => $draft->existing['id']];
            }
        }

        return $links;
    }

    /**
     * The policy a paper without a number takes from another file or from those registered: a registered one by its
     * id (`policy:<id>`); a new one by its number, only when the file that creates it was saved before.
     *
     * @param  PolicyGiverData|null  $giver  the giver the proposal chose, before anything was saved
     * @return array{?InsurancePolicy, list<string>} the policy and, when it is not there, why
     */
    private function policy(?PolicyGiverData $giver): array
    {
        if (! $giver instanceof PolicyGiverData) {
            return [null, []];
        }

        $policy = str_starts_with($giver->key, 'policy:')
            ? InsurancePolicy::query()->find((int) substr($giver->key, strlen('policy:')))
            : $this->matcher->policy($giver->number);

        return $policy instanceof InsurancePolicy
            ? [$policy, []]
            : [null, [Translated::text('Not linked to the policy :number: the policy was not created. Link it from the document form.', ['number' => $giver->number])]];
    }

    /**
     * Step 2: the document, with the fields the person confirmed over the proposed ones, linked to the cards the
     * proposal found, the ones saved with the file and the policy taken; that policy also gives the organization and
     * the person when they are empty.
     *
     * @param  File  $file  the file
     * @param  ProposalData  $proposal  the proposal, made again after the cards
     * @param  ImportChoices  $choices  what the person confirmed
     * @param  list<array{type: string, id: int}>  $cardLinks  the cards saved in step 1
     * @param  InsurancePolicy|null  $policy  the policy taken from another file
     * @param  list<array{type: string, id: int}>  $unlinked  the cards the document is not linked to, whatever the form says
     */
    private function document(File $file, ProposalData $proposal, ImportChoices $choices, array $cardLinks, ?InsurancePolicy $policy, array $unlinked): Document
    {
        $proposed = $proposal->document;
        $fields = [
            'title' => $proposed->title ?? Translated::text('Document'),
            'category_id' => $proposed->category_id,
            'person_id' => $proposed->person_id,
            'organization_id' => $proposed->organization_id,
            'date' => $proposed->date,
            'expiry_date' => $proposed->expiry_date,
            'status' => DocumentStatus::Active->value,
            ...array_intersect_key($choices->document, array_flip(DocumentRules::FIELDS)),
        ];
        $links = array_key_exists('links', $choices->document) ? DocumentRules::links($choices->document['links']) : $proposed->links;
        $links = [...$links, ...$cardLinks];

        if ($policy instanceof InsurancePolicy) {
            $fields['organization_id'] ??= $policy->organization_id;
            $fields['person_id'] ??= $policy->person_id;
            $links[] = ['type' => 'insurance_policy', 'id' => $policy->id];
            if ($policy->vehicle_id !== null) {
                $links[] = ['type' => 'vehicle', 'id' => $policy->vehicle_id];
            }
        }

        $links = array_values(array_filter($links, static fn (array $link): bool => ! in_array($link, $unlinked, false)));

        return $this->fileDocument->handle($file, ['fields' => $fields, 'links' => $links]);
    }

    /**
     * Step 3: the movement already recorded takes the document and what changes on it; otherwise the expense to pay,
     * the payment of a due date, or a new expense or income.
     *
     * @param  Extraction  $x  what the model read, or the reading seen from one supply
     * @param  File  $file  the file
     * @param  MovementProposalData  $proposed  the movement side of the proposal
     * @param  ImportChoices  $choices  what the person confirmed
     * @param  Document  $document  the document of the file
     * @param  array{card_type?: string, card_id?: int}  $own  the card of the document category saved with the file
     * @param  array{card_type?: string, card_id?: int}|null  $card  the card of a new movement whatever else gives one (the utility of a supply, or none); null to take it as for a bill alone
     *
     * @throws ValidationException if the movement misses its amount or date, or its due date is already paid
     */
    private function movement(Extraction $x, File $file, MovementProposalData $proposed, ImportChoices $choices, Document $document, array $own, ?array $card = null): Expense|Income|PlannedExpense
    {
        $kind = $proposed->values->kind;

        if ($proposed->duplicate !== null) {
            return $this->linked($x, $file, $proposed, $document);
        }

        $due = $proposed->due;
        $toPay = $due instanceof DueData && ($choices->due ?? $due->suggested);

        if ($proposed->blocked !== null) {
            throw ValidationException::withMessages(['movement' => ImportErrors::movement($kind, $toPay)]);
        }

        if ($toPay) {
            return $this->toPay($proposed, $document, $card ?? $this->card($document, $own));
        }

        if ($proposed->pays !== null) {
            return $this->payment($proposed->values, $proposed->pays, $document, $own);
        }

        $columns = $this->columns($proposed->values, $document, $own, $card);
        $movement = $this->saveMovement->handle($kind, $columns);
        $movement->documents()->syncWithoutDetaching([$document->id]);

        return $movement;
    }

    /**
     * The movement already recorded, or picked by hand: it takes the document and, for an expense, what the file
     * changes on it. A commitment taken pays the due date the day of the expense falls on.
     *
     * @param  Extraction  $x  what the model read, or the reading seen from one supply
     * @param  File  $file  the file
     * @param  MovementProposalData  $proposed  the movement side
     * @param  Document  $document  the document
     *
     * @throws ModelNotFoundException if it is no longer there
     * @throws ValidationException if the due date of the commitment was paid in the meantime
     */
    private function linked(Extraction $x, File $file, MovementProposalData $proposed, Document $document): Expense|Income
    {
        $id = $proposed->duplicate['id'] ?? 0;
        $movement = $proposed->values->kind === 'income' ? Income::query()->findOrFail($id) : Expense::query()->findOrFail($id);
        $movement->documents()->syncWithoutDetaching([$document->id]);

        if (! $movement instanceof Expense) {
            return $movement;
        }

        $data = $this->expenseChanges->of($movement, $proposed->values, $x, ReadingContext::for($file))['data'];
        if (! array_key_exists('planned_expense_id', $data)) {
            if ($data !== []) {
                $movement->update($data);
            }

            return $movement;
        }

        // The commitment goes through SaveMovement: it locks it, names the due date the day of the expense pays
        // (`occurrence_date`) and refuses one paid since the proposal. Its fill of the empty columns from the commitment
        // must not touch what the expense already has, so those are sent as they are.
        $columns = [...$movement->only(self::PLANNED_GIVES), ...$data, 'planned_id' => $data['planned_expense_id']];
        unset($columns['planned_expense_id']);

        return $this->saveMovement->handle('expense', $columns, $movement);
    }

    /**
     * "To pay": no expense. A new planned expense that does not repeat, dated on the deadline; or the one already
     * there; or the commitment whose due date takes the amount and, when it differs, the deadline. The document
     * waits on that planned expense.
     *
     * @param  MovementProposalData  $proposed  the movement side, with "to pay" in effect and its deadline
     * @param  Document  $document  the document
     * @param  array{card_type?: string, card_id?: int}  $card  the card a new planned expense refers to
     *
     * @throws ModelNotFoundException if the planned expense is no longer there
     */
    private function toPay(MovementProposalData $proposed, Document $document, array $card): PlannedExpense
    {
        $due = $proposed->due;
        $values = $proposed->values;

        if (! $due instanceof DueData || $due->date === null) {
            throw ValidationException::withMessages(['movement' => ImportErrors::movement('expense', true)]);
        }

        $deadline = CarbonImmutable::parse($due->date, 'Europe/Rome');
        $planned = $due->mode === DueMode::New
            ? $this->savePlanned->handle('expense', [
                'title' => $values->title ?? $document->title,
                'amount' => $values->amount,
                'is_recurring' => false,
                'start_date' => $deadline->toDateString(),
                'organization_id' => $values->organization_id,
                'category_id' => $values->category_id,
                'financial_account_id' => $values->financial_account_id,
                'person_id' => $values->person_id,
                'notes' => $values->notes,
                ...$card,
            ])
            : PlannedExpense::query()->findOrFail($due->plan['id'] ?? 0);

        if (! $planned instanceof PlannedExpense) {
            throw new ModelNotFoundException('The planned expense was not created.');
        }

        if ($due->mode === DueMode::Exception && $due->occurrence !== null) {
            $this->exception($planned, CarbonImmutable::parse($due->occurrence, 'Europe/Rome'), (float) $values->amount, $deadline);
        }

        $document->waitOn($planned);

        return $planned;
    }

    /**
     * The due date of a commitment takes the amount of the bill and, when it differs, its deadline. The other
     * exceptions of that due date are replaced, keeping their note. With the commitment locked, a due date paid
     * in the meantime fails the file, as *I paid it* does.
     *
     * @param  PlannedExpense  $planned  the commitment
     * @param  CarbonImmutable  $occurrence  the original date of the due date
     * @param  float  $amount  the amount of the bill
     * @param  CarbonImmutable  $deadline  the deadline of the bill
     *
     * @throws ValidationException if the due date is already paid
     */
    private function exception(PlannedExpense $planned, CarbonImmutable $occurrence, float $amount, CarbonImmutable $deadline): void
    {
        PlannedKind::lock($planned);

        if ($this->deadlines->find($planned, $occurrence)->isPaid()) {
            throw ValidationException::withMessages(['occurrence_date' => __('This due date is already paid: choose another one.')]);
        }

        $notes = $planned->exceptions()->whereDate('occurrence_date', $occurrence->toDateString())->whereNotNull('notes')->value('notes');

        $this->changeOccurrence->handle(
            $planned,
            $occurrence,
            false,
            $amount,
            $deadline->isSameDay($occurrence) ? null : $deadline,
            is_string($notes) ? $notes : null,
        );
    }

    /**
     * A payment receipt that pays a due date of a planned expense: the expense settles that due date, titled as
     * *I paid it* titles it, and takes the documents waiting on the planned expense.
     *
     * @param  MovementValuesData  $values  the expense, with the fee
     * @param  array{planned: int, title: string, date: string, occurrence: string, others: int}  $pays  the due date paid
     * @param  Document  $document  the document
     * @param  array{card_type?: string, card_id?: int}  $own  the card of the document category saved with the file
     *
     * @throws ModelNotFoundException if the planned expense is no longer there
     * @throws ValidationException if the due date is already paid
     */
    private function payment(MovementValuesData $values, array $pays, Document $document, array $own): Expense
    {
        $planned = PlannedExpense::query()->findOrFail($pays['planned']);
        $occurrence = CarbonImmutable::parse($pays['occurrence'], 'Europe/Rome');

        $expense = $this->saveMovement->handle('expense', [
            ...$this->columns($values, $document, $own),
            'planned_id' => $planned->id,
            'occurrence_date' => $occurrence->toDateString(),
            'title' => PaymentTitle::for($pays['title'], $occurrence),
        ]);

        if (! $expense instanceof Expense) {
            throw new ModelNotFoundException('The expense was not created.');
        }

        $expense->documents()->syncWithoutDetaching([$document->id]);
        $this->movePlannedDocuments->handle($planned, CarbonImmutable::parse($pays['date'], 'Europe/Rome'), $expense);

        return $expense;
    }

    /**
     * The columns of a new expense or income for {@see SaveMovement}: the commitment of the paper is its planned
     * item; an expense without one refers to the card of the document.
     *
     * @param  MovementValuesData  $values  the values of the proposal
     * @param  Document  $document  the document, whose title names a movement without one and whose links give the card
     * @param  array{card_type?: string, card_id?: int}  $own  the card of the document category saved with the file
     * @param  array{card_type?: string, card_id?: int}|null  $card  the card of the expense whatever its commitment gives (the utility of a supply, or none); null to take it as for a bill alone
     * @return array<string, mixed>
     */
    private function columns(MovementValuesData $values, Document $document, array $own, ?array $card = null): array
    {
        $shared = [
            'title' => $values->title ?? $document->title,
            'amount' => $values->amount,
            'date' => $values->date,
            'organization_id' => $values->organization_id,
            'financial_account_id' => $values->financial_account_id,
            'person_id' => $values->person_id,
            'notes' => $values->notes,
            'planned_id' => $values->lead['id'] ?? null,
        ];

        if ($values->kind === 'income') {
            return [...$shared, 'type' => $values->income_type, 'employment_id' => $values->employment_id];
        }

        return [
            ...$shared,
            'fee_amount' => $values->fee_amount,
            'category_id' => $values->category_id,
            ...match (true) {
                $card !== null => $card,
                $values->lead === null => $this->card($document, $own),
                default => [],
            },
        ];
    }

    /**
     * The card a new expense or planned expense refers to: the card of the document category saved with the file,
     * otherwise the first utility, policy, vehicle or subscription the document is linked to.
     *
     * @param  Document  $document  the document
     * @param  array{card_type?: string, card_id?: int}  $own  the card of the document category saved with the file
     * @return array{card_type?: string, card_id?: int}
     */
    private function card(Document $document, array $own): array
    {
        if ($own !== []) {
            return $own;
        }

        foreach (self::MOVEMENT_CARDS as $type) {
            $relation = match ($type) {
                'utility' => $document->utilities(),
                'insurance_policy' => $document->insurancePolicies(),
                'vehicle' => $document->vehicles(),
                default => $document->subscriptions(),
            };
            $key = $relation->getRelated()->getQualifiedKeyName();
            $id = $relation->orderBy($key)->value($key);

            if (is_numeric($id)) {
                return ['card_type' => $type, 'card_id' => (int) $id];
            }
        }

        return [];
    }

    /**
     * Step 4: the health event already there takes the document and only the fields it has empty (the organization,
     * the outcome, the next checkup, the expense); otherwise a new one with the expense and the document. The
     * reminder of the next checkup follows the saved event.
     *
     * @param  EventProposalData  $proposed  the event side of the proposal
     * @param  Document  $document  the document
     * @param  Expense|null  $expense  the expense of the file
     *
     * @throws ValidationException if a new event misses what it needs
     * @throws ModelNotFoundException if the event to link is no longer there
     */
    private function event(EventProposalData $proposed, Document $document, ?Expense $expense): HealthEvent
    {
        $values = $proposed->values;

        if ($proposed->existing !== null) {
            $event = HealthEvent::query()->findOrFail($proposed->existing['id']);
            $event->organization_id ??= $values->organization_id;
            $event->next_checkup_date ??= $values->next_checkup_date === null ? null : CarbonImmutable::parse($values->next_checkup_date, 'Europe/Rome');
            $event->expense_id ??= $expense?->id;
            if (($event->outcome ?? '') === '') {
                $event->outcome = $values->outcome;
            }
            $event->save();
        } elseif ($proposed->blocked !== null) {
            throw ValidationException::withMessages(['event' => ImportErrors::event()]);
        } else {
            $event = HealthEvent::query()->create([
                'title' => $values->title,
                'type' => $values->type,
                'person_id' => $values->person_id,
                'organization_id' => $values->organization_id,
                'date' => $values->date,
                'outcome' => $values->outcome,
                'next_checkup_date' => $values->next_checkup_date,
                'notes' => $values->notes,
                'expense_id' => $expense?->id,
            ]);
            $this->areas->reveal(Area::Health);
        }

        $event->documents()->syncWithoutDetaching([$document->id]);

        return $event;
    }

    /**
     * The utility the consumption box of the proposal goes to, when it found one.
     *
     * @param  ConsumptionProposalData  $proposed  the consumption side of the proposal
     */
    private function proposedUtility(ConsumptionProposalData $proposed): ?Utility
    {
        return $proposed->utility === null ? null : Utility::query()->find($proposed->utility['id']);
    }

    /**
     * Step 4b: the consumption of the bill, in the row of its utility: the one of the proposal, otherwise the first of
     * electricity, gas or water the saved document is linked to (a utility created with the file); for a supply, its
     * own utility. A reading never overwrites a row written by hand. What only the reading has (an adjustment, the
     * energy part, the bands) is saved as read.
     *
     * @param  Extraction  $x  what the model read, or the reading seen from one supply: it gives what the box does not show
     * @param  ConsumptionProposalData  $proposed  the consumption side of the proposal
     * @param  Document  $document  the document, the bill of the row
     * @param  Utility|null  $utility  the utility of the row
     *
     * @throws ValidationException if the consumption misses its utility, quantity or period
     */
    private function consumption(Extraction $x, ConsumptionProposalData $proposed, Document $document, ?Utility $utility): UtilityConsumption
    {
        $values = $proposed->values;

        // The proposal made again after the cards does not see a utility created with the file among the links the page
        // sent, so the document decides whether it has one; what is missing in the box still stops the import
        if (! $utility instanceof Utility) {
            throw ValidationException::withMessages(['consumption' => ConsumptionProposalData::chooseUtility()]);
        }

        // A value missing, out of range or a period reversed: the box says it (the message of the utility comes first
        // in the proposal, so with a utility found here the general one is said)
        if ($proposed->fill !== [] || $values->quantity === null || $values->period_start === null || $values->period_end === null) {
            throw ValidationException::withMessages(['consumption' => $proposed->complete === [] && $proposed->blocked !== null
                ? $proposed->blocked
                : Translated::text('To record the consumption write the quantity and the period in its box.')]);
        }

        return $this->saveConsumption->handle($utility, new ConsumptionValues(
            periodStart: CarbonImmutable::parse($values->period_start, 'Europe/Rome'),
            periodEnd: CarbonImmutable::parse($values->period_end, 'Europe/Rome'),
            quantity: $values->quantity,
            amount: $values->amount,
            isEstimated: $values->is_estimated,
            hasAdjustments: $x->consumption_adjusted ?? false,
            energyAmount: $x->consumption_energy_amount,
            f1: $x->consumption_f1,
            f2: $x->consumption_f2,
            f3: $x->consumption_f3,
        ), ConsumptionSource::Reading, $document);
    }
}

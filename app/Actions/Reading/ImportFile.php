<?php

declare(strict_types=1);

namespace App\Actions\Reading;

use App\Actions\Budget\ChangeOccurrence;
use App\Actions\Budget\MovePlannedDocuments;
use App\Actions\Budget\SaveMovement;
use App\Actions\Budget\SavePlanned;
use App\Actions\Documents\FileDocument;
use App\Data\Reading\DueData;
use App\Data\Reading\EventProposalData;
use App\Data\Reading\Extraction;
use App\Data\Reading\ImportChoices;
use App\Data\Reading\MovementProposalData;
use App\Data\Reading\MovementValuesData;
use App\Data\Reading\PolicyGiverData;
use App\Data\Reading\ProposalData;
use App\Enums\DocumentStatus;
use App\Enums\DueMode;
use App\Models\Document;
use App\Models\Expense;
use App\Models\File;
use App\Models\HealthEvent;
use App\Models\Income;
use App\Models\InsurancePolicy;
use App\Models\PlannedExpense;
use App\Services\Deadlines;
use App\Services\Reading\ExpenseChanges;
use App\Services\Reading\Matcher;
use App\Services\Reading\Proposal;
use App\Services\Reading\ReadingContext;
use App\Support\CurrentFamily;
use App\Support\DocumentRules;
use App\Support\PaymentTitle;
use App\Support\PlannedKind;
use App\Support\Reading\ImportErrors;
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
 * 1. the cards the document names (organization, vehicle, utility, policy, belonging), {@see SaveRecords};
 * 2. the document, linked to them and to the policy taken from another file;
 * 3. the expense or income, or the expense to pay, or the payment of a due date;
 * 4. the health event;
 * 5. the intervention, with the expense.
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
    private const array PLANNED_GIVES = ['organization_id', 'financial_account_id', 'person_id', 'category_id', 'card_type', 'card_id'];

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

        $before = $this->records->before($first->records, $choices->records);
        if ($before['organization'] !== null) {
            $choices = $choices->withOrganization($before['organization']);
        }

        $proposal = $before['records'] === [] ? $first : $this->proposals->for($file, $choices, $roundGivers, $locale);
        [$policy, $warnings] = $this->policy($first->policy['giver'] ?? null);

        // The form was filled before the other files of the import were saved: the cards they created, which this file
        // names too (a second paper with the number of the policy the first one created), are not in it
        $found = array_values(array_filter($proposal->document->links, static fn (array $link): bool => in_array($link, $roundCards, true)));
        $document = $this->document($file, $proposal, $choices, [...$before['links'], ...$found], $policy);
        $movement = $choices->movement && $proposal->movement instanceof MovementProposalData
            ? $this->movement($file, $proposal->movement, $choices, $document)
            : null;
        $expense = $movement instanceof Expense ? $movement : null;
        $event = $choices->event && $proposal->event instanceof EventProposalData ? $this->event($proposal->event, $document, $expense) : null;
        $after = $this->records->after($first->records, $choices->records, $before['ids'], $document, $expense);

        return ['document' => $document, 'movement' => $movement, 'event' => $event, 'records' => [...$before['records'], ...$after], 'warnings' => $warnings];
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
     */
    private function document(File $file, ProposalData $proposal, ImportChoices $choices, array $cardLinks, ?InsurancePolicy $policy): Document
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

        return $this->fileDocument->handle($file, ['fields' => $fields, 'links' => $links]);
    }

    /**
     * Step 3: the movement already recorded takes the document and what changes on it; otherwise the expense to pay,
     * the payment of a due date, or a new expense or income.
     *
     * @param  File  $file  the file
     * @param  MovementProposalData  $proposed  the movement side of the proposal
     * @param  ImportChoices  $choices  what the person confirmed
     * @param  Document  $document  the document of the file
     *
     * @throws ValidationException if the movement misses its amount or date, or its due date is already paid
     */
    private function movement(File $file, MovementProposalData $proposed, ImportChoices $choices, Document $document): Expense|Income|PlannedExpense
    {
        $kind = $proposed->values->kind;

        if ($proposed->duplicate !== null) {
            return $this->linked($file, $proposed, $document);
        }

        $due = $proposed->due;
        $toPay = $due instanceof DueData && ($choices->due ?? $due->suggested);

        if ($proposed->blocked !== null) {
            throw ValidationException::withMessages(['movement' => ImportErrors::movement($kind, $toPay)]);
        }

        if ($toPay) {
            return $this->toPay($proposed, $document);
        }

        if ($proposed->pays !== null) {
            return $this->payment($proposed->values, $proposed->pays, $document);
        }

        $columns = $this->columns($proposed->values, $document);
        $movement = $this->saveMovement->handle($kind, $columns);
        $movement->documents()->syncWithoutDetaching([$document->id]);

        return $movement;
    }

    /**
     * The movement already recorded, or picked by hand: it takes the document and, for an expense, what the file
     * changes on it. A commitment taken pays the due date the day of the expense falls on.
     *
     * @param  File  $file  the file
     * @param  MovementProposalData  $proposed  the movement side
     * @param  Document  $document  the document
     *
     * @throws ModelNotFoundException if it is no longer there
     * @throws ValidationException if the due date of the commitment was paid in the meantime
     */
    private function linked(File $file, MovementProposalData $proposed, Document $document): Expense|Income
    {
        $id = $proposed->duplicate['id'] ?? 0;
        $movement = $proposed->values->kind === 'income' ? Income::query()->findOrFail($id) : Expense::query()->findOrFail($id);
        $movement->documents()->syncWithoutDetaching([$document->id]);

        if (! $movement instanceof Expense) {
            return $movement;
        }

        $data = $this->expenseChanges->of($movement, $proposed->values, Extraction::ofFile($file), ReadingContext::for($file))['data'];
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
     *
     * @throws ModelNotFoundException if the planned expense is no longer there
     */
    private function toPay(MovementProposalData $proposed, Document $document): PlannedExpense
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
                ...$this->card($document),
            ])
            : PlannedExpense::query()->findOrFail($due->plan['id'] ?? 0);

        if (! $planned instanceof PlannedExpense) {
            throw new ModelNotFoundException('The planned expense was not created.');
        }

        if ($due->mode === DueMode::Exception && $due->occurrence !== null) {
            $this->exception($planned, CarbonImmutable::parse($due->occurrence, 'Europe/Rome'), (float) $values->amount, $deadline);
        }

        $document->update(['planned_expense_id' => $planned->id]);

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
     *
     * @throws ModelNotFoundException if the planned expense is no longer there
     * @throws ValidationException if the due date is already paid
     */
    private function payment(MovementValuesData $values, array $pays, Document $document): Expense
    {
        $planned = PlannedExpense::query()->findOrFail($pays['planned']);
        $occurrence = CarbonImmutable::parse($pays['occurrence'], 'Europe/Rome');

        $expense = $this->saveMovement->handle('expense', [
            ...$this->columns($values, $document),
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
     * @return array<string, mixed>
     */
    private function columns(MovementValuesData $values, Document $document): array
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
            ...($values->lead === null ? $this->card($document) : []),
        ];
    }

    /**
     * The card a new expense or planned expense refers to: the first utility, policy, vehicle or subscription the
     * document is linked to.
     *
     * @param  Document  $document  the document
     * @return array{card_type?: string, card_id?: int}
     */
    private function card(Document $document): array
    {
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
        }

        $event->documents()->syncWithoutDetaching([$document->id]);

        return $event;
    }
}

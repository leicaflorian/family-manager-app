<?php

declare(strict_types=1);

namespace App\Http\Requests\Reading;

use App\Data\Reading\ConsumptionProposalData;
use App\Data\Reading\DueData;
use App\Data\Reading\EventProposalData;
use App\Data\Reading\ImportChoices;
use App\Data\Reading\MovementProposalData;
use App\Data\Reading\ProposalData;
use App\Data\Reading\RecordDraftData;
use App\Enums\Feature;
use App\Enums\RecordKind;
use App\Models\Document;
use App\Models\Expense;
use App\Models\File;
use App\Models\HealthEvent;
use App\Models\Income;
use App\Rules\OfFamily;
use App\Services\FamilyFeatures;
use App\Services\Reading\Proposal;
use App\Support\DocumentRules;
use App\Support\Reading\ImportErrors;
use App\Support\Reading\RecordLabel;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\App;

/**
 * Many files of *To sort* imported at once: for each, the file (of the family and still waiting), the fields of its
 * document, with the same rules as the form of one document, and what it does too (`also`: the boxes, the
 * corrections and the choices of *What I also do*, cleaned by {@see ImportChoices}).
 *
 * Before saving, the proposal of each file is made with those choices: a ticked box that misses what it needs, or a
 * movement, event or card picked by hand that is no longer there (or is of another family), is refused here, so the
 * import saves all of a file or nothing of it.
 */
final class ImportRequest extends FormRequest
{
    /** Where the document of each file sits in the body. */
    private const string DOCUMENT = 'files.%d.document.';

    /** Where what each file does too sits in the body. */
    private const string ALSO = 'files.%d.also.';

    /** Where the choices of the supplies of a bill with more than one sit, under what the file does too. */
    private const string SUPPLY = 'supplies.%d.';

    /** The places of the rules of what a file does too: of the file, and of each supply of a bill with more than one. */
    private const array PLACES = ['files.*.also.', 'files.*.also.supplies.*.'];

    /** A text correction, as long as a text field of the forms. */
    private const array TEXT = ['nullable', 'string', 'max:255'];

    /** An amount correction, bound as the amount of the expense form. */
    private const array PRICE = ['nullable', 'numeric', 'min:0', 'max:99999999'];

    /** @var array<string, list<string>> the rules of the money choices when the Budget is off, of the file and of each supply: only a refusal (`false`) or nothing passes */
    private const array MONEY_OFF = [
        'movement' => ['sometimes', 'nullable', 'declined'],
        'consumption' => ['sometimes', 'nullable', 'declined'],
        'due' => ['sometimes', 'nullable', 'declined'],
        'overrides.movement' => ['prohibited'],
        'overrides.consumption' => ['prohibited'],
        'targets.movement' => ['prohibited'],
    ];

    /**
     * The proposal of each file made by the checks, by its place in the body: the import starts from it.
     *
     * @var array<int, ProposalData>
     */
    private array $proposals = [];

    /**
     * Whoever adds documents imports them; each file is authorized by the controller (`FilePolicy::import`).
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Document::class) ?? false;
    }

    /**
     * The rules of the files and, under each, those of its document and of what it does too.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            // A list, so every entry has a numeric place and the rules of its document are built for it
            'files' => ['required', 'array', 'list', 'min:1', 'max:50'],
            'files.*' => ['required', 'array:file_id,document,also'],
            'files.*.file_id' => [...DocumentRules::fileId(), 'distinct'],
            'files.*.document' => ['required', 'array'],
            'files.*.also' => ['sometimes', 'array:movement,event,records,overrides,targets,due,policy,create_card,card_type,replace_policies,consumption,supplies'],
            'files.*.also.event' => ['sometimes', 'boolean'],
            'files.*.also.create_card' => ['sometimes', 'boolean'],
            // The card of the proposal the page showed: the choices of its box are dropped for another card
            'files.*.also.card_type' => ['sometimes', 'nullable', 'string', 'max:50'],
            'files.*.also.policy' => ['sometimes', 'string', 'max:50'],
            'files.*.also.records' => ['sometimes', 'array', 'list'],
            // The old policies to cancel: only the ones proposed are taken, read again when saving
            'files.*.also.replace_policies' => ['sometimes', 'array', 'list', 'max:20'],
            'files.*.also.overrides.records' => ['sometimes', 'array'],
            'files.*.also.overrides.records.*' => ['array'],
            'files.*.also.overrides.records.*.home_id' => ['nullable', new OfFamily('homes')],
            // The fields of the box of the card of the category depend on its type: each is bound as a text field
            'files.*.also.overrides.records.card.*' => ['nullable', 'max:255'],
            'files.*.also.targets' => ['sometimes', 'array'],
            'files.*.also.overrides.event' => ['sometimes', 'array'],
            'files.*.also.overrides.event.title' => self::TEXT,
            'files.*.also.overrides.event.outcome' => self::TEXT,
            // The choices of each supply of a bill with more than one, as those of a bill alone
            'files.*.also.supplies' => ['sometimes', 'array', 'max:'.ImportChoices::MAX_SUPPLIES],
            'files.*.also.supplies.*' => ['array:movement,record,consumption,due,targets,overrides'],
            'files.*.also.supplies.*.record' => ['sometimes', 'boolean'],
            'files.*.also.supplies.*.targets' => ['sometimes', 'array'],
            'files.*.also.supplies.*.overrides.record' => ['sometimes', 'array'],
            'files.*.also.supplies.*.overrides.record.home_id' => ['nullable', new OfFamily('homes')],
        ];

        foreach (self::PLACES as $place) {
            $rules += $this->moneyRules($place);
        }

        // Without the Budget nothing about money is accepted: the page does not send it, so a body that does is wrong
        if (! resolve(FamilyFeatures::class)->on(Feature::Budget)) {
            foreach (self::PLACES as $place) {
                foreach (self::MONEY_OFF as $key => $rule) {
                    $rules[$place.$key] = $rule;
                }
            }
        }

        foreach (ImportChoices::RECORD_FIELDS as $kind => $fields) {
            foreach ($fields as $field => $check) {
                $bound = match ($check) {
                    'text' => self::TEXT,
                    'fee' => self::PRICE,
                    default => null,
                };
                if ($bound !== null) {
                    $rules[sprintf('files.*.also.overrides.records.%s.%s', $kind, $field)] = $bound;
                    if ($kind === RecordKind::Utility->value) {
                        $rules[sprintf('files.*.also.supplies.*.overrides.record.%s', $field)] = $bound;
                    }
                }
            }
        }

        foreach (array_keys($this->array('files')) as $index) {
            $also = sprintf('files.%s.also.', $index);
            $places = [$also, ...array_map(static fn (int|string $supply): string => $also.sprintf(self::SUPPLY, $supply), array_keys($this->array($also.'supplies')))];
            foreach ($places as $place) {
                // The last day of a corrected period is not before the first, when both are corrected
                if ($this->filled($place.'overrides.consumption.period_start')) {
                    $rules[$place.'overrides.consumption.period_end'] = ['nullable', 'date_format:Y-m-d', 'after_or_equal:'.$place.'overrides.consumption.period_start'];
                }
            }

            $rules += DocumentRules::rules($this->input(sprintf('files.%s.document.links', $index)), sprintf(self::DOCUMENT, $index));
        }

        return $rules;
    }

    /**
     * The rules of the boxes and corrections of the expense and of the consumption, of a file or of one supply.
     *
     * @param  string  $place  where they sit (`files.*.also.` or `files.*.also.supplies.*.`)
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    private function moneyRules(string $place): array
    {
        return [
            $place.'movement' => ['sometimes', 'boolean'],
            $place.'consumption' => ['sometimes', 'boolean'],
            $place.'due' => ['sometimes', 'nullable', 'boolean'],
            $place.'overrides' => ['sometimes', 'array'],
            $place.'overrides.movement' => ['sometimes', 'array'],
            // The ids of the corrections go into new rows: only those of the family
            $place.'overrides.movement.category_id' => ['nullable', new OfFamily('expense_categories')],
            $place.'overrides.movement.organization_id' => ['nullable', new OfFamily('organizations')],
            $place.'overrides.movement.financial_account_id' => ['nullable', new OfFamily('financial_accounts')],
            // The corrections are bound as in the forms of the expense and of the cards
            $place.'overrides.movement.title' => self::TEXT,
            $place.'overrides.movement.amount' => self::PRICE,
            $place.'overrides.movement.fee_amount' => self::PRICE,
            $place.'overrides.consumption' => ['sometimes', 'array'],
            $place.'overrides.consumption.quantity' => ['nullable', 'numeric', 'gt:0', 'max:'.ImportChoices::MAX_QUANTITY, 'decimal:0,3'],
            $place.'overrides.consumption.period_start' => ['nullable', 'date_format:Y-m-d'],
            $place.'overrides.consumption.period_end' => ['nullable', 'date_format:Y-m-d'],
            $place.'overrides.consumption.is_estimated' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * The checks that need the proposal of each file, once the rules pass.
     *
     * @return list<Closure(Validator):void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            foreach ($this->entries() as $index => $entry) {
                $file = File::query()->toSort()->find($entry['file_id']);
                if (! $file instanceof File) {
                    continue;
                }

                $proposal = resolve(Proposal::class)->for($file, $entry['choices'], [], App::getLocale());
                $this->proposals[$index] = $proposal;
                foreach ($this->problems($proposal, $entry['choices']) as $key => $message) {
                    $validator->errors()->add(sprintf(self::ALSO, $index).$key, $message);
                }
            }
        }];
    }

    /**
     * The names of the fields in the messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'files.*.file_id' => __('file'),
            ...DocumentRules::attributes('files.*.document.'),
        ];
    }

    /**
     * What to import, in the order of the request: the file, the choices of the person with the validated fields of
     * the document, and the proposal the checks made with them (without the policies of the other files).
     *
     * `also` is read as sent: its ids were checked by the rules, the rest is cleaned by {@see ImportChoices} (the
     * validated data would keep only the keys that have a rule).
     *
     * @return list<array{file_id: int, choices: ImportChoices, proposal: ?ProposalData}>
     */
    public function entries(): array
    {
        $entries = [];

        foreach ($this->safe()->array('files') as $index => $entry) {
            if (! is_array($entry) || ! is_array($entry['document'] ?? null) || ! is_numeric($entry['file_id'] ?? null)) {
                continue;
            }

            $document = array_intersect_key($entry['document'], array_flip(DocumentRules::FIELDS));
            $document['links'] = DocumentRules::links($entry['document']['links'] ?? []);
            $also = $this->input(sprintf('files.%d.also', $index));

            $entries[] = [
                'file_id' => (int) $entry['file_id'],
                'choices' => ImportChoices::fromArray([...(is_array($also) ? $also : []), 'document' => $document]),
                'proposal' => is_int($index) ? $this->proposals[$index] ?? null : null,
            ];
        }

        return $entries;
    }

    /**
     * What stops a file from being imported with these choices, by key under `also`.
     *
     * @param  ProposalData  $proposal  the proposal with the choices
     * @param  ImportChoices  $choices  the choices
     * @return array<string, string>
     */
    private function problems(ProposalData $proposal, ImportChoices $choices): array
    {
        $problems = [
            ...$this->moneyProblems($proposal->movement, $proposal->consumption, $choices),
            ...$this->recordProblems($proposal->records, $choices, $proposal->cardType),
        ];

        $target = $choices->targets['event'];
        if (is_int($target) && HealthEvent::query()->find($target) === null) {
            $problems['targets.event'] = ImportErrors::gone('event');
        } elseif ($choices->event && $proposal->event instanceof EventProposalData && $proposal->event->existing === null && $proposal->event->blocked !== null) {
            $problems['event'] = ImportErrors::event();
        }

        // Each supply of a bill with more than one, with its own choices; its utility box is `record`
        foreach ($proposal->supplies as $supply) {
            $own = $choices->forSupply($supply->index);
            $found = [
                ...$this->moneyProblems($supply->movement, $supply->consumption, $own),
                ...$this->recordProblems($supply->record instanceof RecordDraftData ? [$supply->record] : [], $own, null),
            ];
            foreach ($found as $key => $message) {
                $key = strtr($key, ['targets.records.'.RecordKind::Utility->value => 'targets.record', 'records.'.RecordKind::Utility->value => 'record']);
                $problems[sprintf(self::SUPPLY, $supply->index).$key] = $message;
            }
        }

        return $problems;
    }

    /**
     * What stops the expense or income and the consumption, of a file or of one supply.
     *
     * @param  MovementProposalData|null  $movement  the movement side
     * @param  ConsumptionProposalData|null  $consumption  the consumption side
     * @param  ImportChoices  $choices  the choices of the file, or of the supply
     * @return array<string, string>
     */
    private function moneyProblems(?MovementProposalData $movement, ?ConsumptionProposalData $consumption, ImportChoices $choices): array
    {
        $problems = [];
        $kind = $movement->values->kind ?? 'expense';

        $target = $choices->targets['movement'];
        if (is_int($target) && ($kind === 'income' ? Income::query()->find($target) : Expense::query()->find($target)) === null) {
            $problems['targets.movement'] = ImportErrors::gone($kind);
        } elseif ($choices->movement && $movement instanceof MovementProposalData && $movement->duplicate === null && $movement->blocked !== null) {
            $problems['movement'] = ImportErrors::movement($kind, $movement->due instanceof DueData && ($choices->due ?? $movement->due->suggested));
        }

        if ($consumption instanceof ConsumptionProposalData && $consumption->blocked !== null && ($choices->consumption ?? $consumption->suggested)) {
            $problems['consumption'] = $consumption->blocked;
        }

        return $problems;
    }

    /**
     * What stops the boxes of the cards: a card picked by hand no longer there, a ticked new card missing its fields.
     *
     * @param  list<RecordDraftData>  $drafts  the boxes
     * @param  ImportChoices  $choices  the choices of the file, or of the supply
     * @param  string|null  $cardType  the type of the card of the document category
     * @return array<string, string>
     */
    private function recordProblems(array $drafts, ImportChoices $choices, ?string $cardType): array
    {
        $problems = [];

        foreach ($choices->recordTargets($cardType) as $kind => $target) {
            // A card of the category picked by hand needs its type; without one there is no such box
            if ($kind === RecordKind::Card->value && $cardType === null) {
                continue;
            }

            $model = RecordLabel::model(RecordKind::from($kind), $cardType);
            if (is_int($target) && $model::query()->find($target) === null) {
                $problems['targets.records.'.$kind] = ImportErrors::gone('card');
            }
        }

        foreach ($drafts as $draft) {
            if (in_array($draft->kind, $choices->records, true) && $draft->existing === null && $draft->missing !== [] && ! isset($problems['targets.records.'.$draft->kind->value])) {
                $problems['records.'.$draft->kind->value] = ImportErrors::record($draft->title, $draft->missing);
            }
        }

        return $problems;
    }
}

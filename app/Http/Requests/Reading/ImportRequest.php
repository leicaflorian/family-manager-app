<?php

declare(strict_types=1);

namespace App\Http\Requests\Reading;

use App\Data\Reading\DueData;
use App\Data\Reading\EventProposalData;
use App\Data\Reading\ImportChoices;
use App\Data\Reading\MovementProposalData;
use App\Data\Reading\ProposalData;
use App\Enums\RecordKind;
use App\Models\Document;
use App\Models\Expense;
use App\Models\File;
use App\Models\HealthEvent;
use App\Models\Income;
use App\Rules\OfFamily;
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

    /** A text correction, as long as a text field of the forms. */
    private const array TEXT = ['nullable', 'string', 'max:255'];

    /** An amount correction, bound as the amount of the expense form. */
    private const array PRICE = ['nullable', 'numeric', 'min:0', 'max:99999999'];

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
            'files.*.also' => ['sometimes', 'array:movement,event,records,overrides,targets,due,policy,create_card'],
            'files.*.also.movement' => ['sometimes', 'boolean'],
            'files.*.also.event' => ['sometimes', 'boolean'],
            'files.*.also.due' => ['sometimes', 'nullable', 'boolean'],
            'files.*.also.create_card' => ['sometimes', 'boolean'],
            'files.*.also.policy' => ['sometimes', 'string', 'max:50'],
            'files.*.also.records' => ['sometimes', 'array', 'list'],
            'files.*.also.overrides' => ['sometimes', 'array'],
            'files.*.also.overrides.movement' => ['sometimes', 'array'],
            // The ids of the corrections go into new rows: only those of the family
            'files.*.also.overrides.movement.category_id' => ['nullable', new OfFamily('expense_categories')],
            'files.*.also.overrides.movement.organization_id' => ['nullable', new OfFamily('organizations')],
            'files.*.also.overrides.movement.financial_account_id' => ['nullable', new OfFamily('financial_accounts')],
            'files.*.also.overrides.records' => ['sometimes', 'array'],
            'files.*.also.overrides.records.*' => ['array'],
            'files.*.also.overrides.records.*.home_id' => ['nullable', new OfFamily('homes')],
            'files.*.also.targets' => ['sometimes', 'array'],
            // The corrections are bound as in the forms of the expense and of the cards
            'files.*.also.overrides.movement.title' => self::TEXT,
            'files.*.also.overrides.movement.amount' => self::PRICE,
            'files.*.also.overrides.movement.fee_amount' => self::PRICE,
            'files.*.also.overrides.event' => ['sometimes', 'array'],
            'files.*.also.overrides.event.title' => self::TEXT,
            'files.*.also.overrides.event.outcome' => self::TEXT,
        ];

        foreach (ImportChoices::RECORD_FIELDS as $kind => $fields) {
            foreach ($fields as $field => $check) {
                $bound = match ($check) {
                    'text' => self::TEXT,
                    'fee' => self::PRICE,
                    default => null,
                };
                if ($bound !== null) {
                    $rules[sprintf('files.*.also.overrides.records.%s.%s', $kind, $field)] = $bound;
                }
            }
        }

        foreach (array_keys($this->array('files')) as $index) {
            $rules += DocumentRules::rules($this->input(sprintf('files.%s.document.links', $index)), sprintf(self::DOCUMENT, $index));
        }

        return $rules;
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
        $problems = [];
        $movement = $proposal->movement;
        $kind = $movement->values->kind ?? 'expense';

        $target = $choices->targets['movement'];
        if (is_int($target) && ($kind === 'income' ? Income::query()->find($target) : Expense::query()->find($target)) === null) {
            $problems['targets.movement'] = ImportErrors::gone($kind);
        } elseif ($choices->movement && $movement instanceof MovementProposalData && $movement->duplicate === null && $movement->blocked !== null) {
            $problems['movement'] = ImportErrors::movement($kind, $movement->due instanceof DueData && ($choices->due ?? $movement->due->suggested));
        }

        $target = $choices->targets['event'];
        if (is_int($target) && HealthEvent::query()->find($target) === null) {
            $problems['targets.event'] = ImportErrors::gone('event');
        } elseif ($choices->event && $proposal->event instanceof EventProposalData && $proposal->event->existing === null && $proposal->event->blocked !== null) {
            $problems['event'] = ImportErrors::event();
        }

        foreach ($choices->targets['records'] as $kind => $target) {
            $model = RecordLabel::model(RecordKind::from($kind));
            if (is_int($target) && $model::query()->find($target) === null) {
                $problems['targets.records.'.$kind] = ImportErrors::gone('card');
            }
        }

        foreach ($proposal->records as $draft) {
            if (in_array($draft->kind, $choices->records, true) && $draft->existing === null && $draft->missing !== [] && ! isset($problems['targets.records.'.$draft->kind->value])) {
                $problems['records.'.$draft->kind->value] = ImportErrors::record($draft->title, $draft->missing);
            }
        }

        return $problems;
    }
}

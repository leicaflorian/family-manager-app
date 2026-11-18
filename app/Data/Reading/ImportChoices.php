<?php

declare(strict_types=1);

namespace App\Data\Reading;

use App\Cards\CardField;
use App\Cards\FieldKind;
use App\Enums\HealthEventType;
use App\Enums\InsurancePolicyType;
use App\Enums\MaintenanceType;
use App\Enums\OrganizationType;
use App\Enums\RecordKind;
use App\Enums\UtilityType;
use App\Services\Reading\RecordDrafts;
use App\Support\DocumentRules;
use App\Support\Reading\ValidDate;

/**
 * What the person chose for one file in *Import*, cleaned: the fields of the document, the boxes ticked in
 * *What I also do*, the corrections of the movement, the event and the cards, the ones picked by hand, "to pay",
 * the policy taken from another file and *Create the card*. An empty value clears a field, an invalid one is dropped
 * (the reading stays); an amount, a date or an event type emptied leave the read ones.
 *
 * Ids are only checked to be positive integers here: that they are of the family is the job of the request
 * (`OfFamily`), and the proposal looks them up through the family scope.
 */
final readonly class ImportChoices
{
    /** The fields of the movement the person can correct, with their check. */
    private const array MOVEMENT_FIELDS = [
        'title' => 'title', 'date' => 'date', 'category_id' => 'id', 'organization_id' => 'id', 'amount' => 'price',
        'fee_amount' => 'fee', 'financial_account_id' => 'id', 'due_date' => 'date',
    ];

    /** The longest text correction, as a text field of the forms. */
    private const int MAX_TEXT = 255;

    /** The largest amount, as in the expense form. */
    private const int MAX_AMOUNT = 99999999;

    /** The fields of the movement that only an expense has. */
    private const array EXPENSE_ONLY = ['category_id', 'fee_amount', 'due_date'];

    /** The fields of the event the person can correct: person, place and date are the document's. */
    private const array EVENT_FIELDS = ['title' => 'title', 'type' => 'health', 'outcome' => 'text', 'next_checkup_date' => 'date'];

    /** The columns of a card that are the document's: they are written in its form, not in the box. */
    public const array DOCUMENT_COLUMNS = ['date', 'person_id', 'organization_id'];

    /** The prefix of the check of a choice, followed by its enum. */
    private const string CHOICE = 'choice:';

    /**
     * The fields the person can correct of the organization and of the five cards the reading fills, by column; issuer,
     * person and date are the document's. The card of the document category takes its fields from its class
     * (`recordFields()`).
     */
    public const array RECORD_FIELDS = [
        'organization' => ['name' => 'text', 'types' => 'types'],
        'vehicle' => ['name' => 'text', 'plate_number' => 'text', 'brand' => 'text', 'model' => 'text', 'registration_year' => 'year'],
        'utility' => ['type' => 'utility', 'supply_point_code' => 'text', 'customer_code' => 'text', 'home_id' => 'id', 'activation_date' => 'date'],
        'policy' => ['title' => 'text', 'type' => 'insurance', 'policy_number' => 'text', 'start_date' => 'date', 'expiry_date' => 'date', 'home_id' => 'id'],
        'belonging' => ['name' => 'text', 'brand' => 'text', 'model' => 'text', 'purchase_price' => 'fee', 'home_id' => 'id', 'warranty_expiry_date' => 'date'],
        'maintenance' => ['title' => 'text', 'type' => 'maintenance', 'home_id' => 'id'],
    ];

    /**
     * @param  array<string, mixed>  $document  the fields of the document (`DocumentRules::FIELDS` and `links`), checked by the request
     * @param  bool  $movement  the box of the expense or income is ticked
     * @param  bool  $event  the box of the health event is ticked
     * @param  list<RecordKind>  $records  the boxes of the cards ticked
     * @param  array{movement: array<string, mixed>, event: array<string, mixed>, records: array<string, array<string, mixed>>}  $overrides  the corrections, `records` by the value of `RecordKind`
     * @param  array{movement: 'auto'|'new'|int, event: 'auto'|'new'|int, records: array<string, 'auto'|'new'|int>}  $targets  `auto` looks for the one already there, `new` creates one, an id is the one picked by hand
     * @param  bool|null  $due  "to pay" (true) or "already paid" (false); null is the automatic choice
     * @param  string  $policy  `auto`, `none` or the `key` of a policy giver
     * @param  bool  $createCard  the box *Create the card* is ticked: after the import *New card* opens with the document linked
     * @param  array<string, mixed>  $cardOverrides  what was written in the box of the card of the document category, still to clean: its fields depend on the type of the card, known only with the proposal (`recordOverrides()`)
     * @param  string|false|null  $cardFor  the card type of the proposal the page showed when the person chose (null: no card); false when the page did not say
     * @param  list<int>  $replacePolicies  the old policies ticked under the box of the new policy, to cancel
     */
    public function __construct(
        public array $document = [],
        public bool $movement = false,
        public bool $event = false,
        public array $records = [],
        public array $overrides = ['movement' => [], 'event' => [], 'records' => []],
        public array $targets = ['movement' => 'auto', 'event' => 'auto', 'records' => []],
        public ?bool $due = null,
        public string $policy = 'auto',
        public bool $createCard = false,
        public array $cardOverrides = [],
        public string|false|null $cardFor = false,
        public array $replacePolicies = [],
    ) {}

    /**
     * The choices as the page sends them, cleaned.
     *
     * @param  array<mixed>  $raw  `{document, movement, event, records, overrides, targets, due, policy, create_card, card_type, replace_policies}`
     */
    public static function fromArray(array $raw): self
    {
        $overrides = self::arrayOf($raw['overrides'] ?? null);
        $targets = self::arrayOf($raw['targets'] ?? null);
        $policy = $raw['policy'] ?? null;

        return new self(
            document: array_intersect_key(self::stringKeys($raw['document'] ?? null), array_flip([...DocumentRules::FIELDS, 'links'])),
            movement: self::bool($raw['movement'] ?? null) ?? false,
            event: self::bool($raw['event'] ?? null) ?? false,
            records: self::kinds($raw['records'] ?? null),
            overrides: [
                'movement' => self::clean(self::arrayOf($overrides['movement'] ?? null), self::MOVEMENT_FIELDS, ['amount', 'date']),
                'event' => self::clean(self::arrayOf($overrides['event'] ?? null), self::EVENT_FIELDS, ['type']),
                'records' => self::recordEdits(self::arrayOf($overrides['records'] ?? null)),
            ],
            targets: [
                'movement' => self::target($targets['movement'] ?? null),
                'event' => self::target($targets['event'] ?? null),
                'records' => self::cleanTargets(self::arrayOf($targets['records'] ?? null)),
            ],
            due: self::bool($raw['due'] ?? null),
            policy: is_string($policy) && ($policy === 'none' || preg_match('/^(file|policy):\d+$/', $policy) === 1) ? $policy : 'auto',
            createCard: self::bool($raw['create_card'] ?? null) ?? false,
            cardOverrides: self::stringKeys(self::arrayOf($overrides['records'] ?? null)[RecordKind::Card->value] ?? null),
            cardFor: array_key_exists('card_type', $raw) ? (is_string($raw['card_type']) ? $raw['card_type'] : null) : false,
            replacePolicies: self::ids($raw['replace_policies'] ?? null),
        );
    }

    /**
     * The choices with the movement picked, as if the person had chosen it in the menu.
     *
     * @param  int  $movementId  the expense or income to link
     */
    public function withMovementTarget(int $movementId): self
    {
        return new self(
            document: $this->document,
            movement: $this->movement,
            event: $this->event,
            records: $this->records,
            overrides: $this->overrides,
            targets: [...$this->targets, 'movement' => $movementId],
            due: $this->due,
            policy: $this->policy,
            createCard: $this->createCard,
            cardOverrides: $this->cardOverrides,
            cardFor: $this->cardFor,
            replacePolicies: $this->replacePolicies,
        );
    }

    /**
     * The choices with the organization created or linked with the file in the menus *Organization* of the document
     * and of the movement that are empty: the page sends them empty when the issuer was not found. One picked by hand
     * stays.
     *
     * @param  int  $organizationId  the organization
     */
    public function withOrganization(int $organizationId): self
    {
        $movement = $this->overrides['movement'];
        $movement['organization_id'] = self::id($movement['organization_id'] ?? null) ?? $organizationId;

        return new self(
            document: [...$this->document, 'organization_id' => self::id($this->document['organization_id'] ?? null) ?? $organizationId],
            movement: $this->movement,
            event: $this->event,
            records: $this->records,
            overrides: [...$this->overrides, 'movement' => $movement],
            targets: $this->targets,
            due: $this->due,
            policy: $this->policy,
            createCard: $this->createCard,
            cardOverrides: $this->cardOverrides,
            cardFor: $this->cardFor,
            replacePolicies: $this->replacePolicies,
        );
    }

    /**
     * The corrections of the cards, by the value of `RecordKind`, with the box of the card of the document category
     * cleaned by the fields of its type; without a type that box does not exist and its corrections are dropped.
     *
     * @param  string|null  $cardType  the type of the morph map of the card of the document category
     * @return array<string, array<string, mixed>>
     */
    public function recordOverrides(?string $cardType): array
    {
        if ($cardType === null || ! $this->madeFor($cardType)) {
            return $this->overrides['records'];
        }

        return [...$this->overrides['records'], RecordKind::Card->value => self::clean($this->cardOverrides, self::recordFields(RecordKind::Card, $cardType))];
    }

    /**
     * The cards picked by hand, by the value of `RecordKind`, without the card of the document category picked for
     * another card than the one of the proposal (the page showed the proposal of the kind of document before).
     *
     * @param  string|null  $cardType  the type of the morph map of the card of the document category
     * @return array<string, 'auto'|'new'|int>
     */
    public function recordTargets(?string $cardType): array
    {
        return $this->madeFor($cardType)
            ? $this->targets['records']
            : array_diff_key($this->targets['records'], [RecordKind::Card->value => true]);
    }

    /**
     * Whether the choices of the box of the card of the document category were made for this card: true also when the
     * page did not say for which.
     *
     * @param  string|null  $cardType  the type of the morph map of the card of the proposal
     */
    private function madeFor(?string $cardType): bool
    {
        return $this->cardFor === false || $this->cardFor === $cardType;
    }

    /**
     * The fields of a card the person can write in its box, by column, with their check: the fixed list of the
     * organization and of the five cards the reading fills; for the card of the document category the required fields
     * of its class that are not the document's, checked by their kind.
     *
     * @param  RecordKind  $kind  which card
     * @param  string|null  $cardType  the type of the morph map of a `Card`
     * @return array<string, string>
     */
    public static function recordFields(RecordKind $kind, ?string $cardType): array
    {
        if ($kind !== RecordKind::Card) {
            return self::RECORD_FIELDS[$kind->value];
        }

        $fields = [];
        foreach (RecordDrafts::requiredFields($kind, $cardType) as $field) {
            if (! in_array($field->column, self::DOCUMENT_COLUMNS, true)) {
                $fields[$field->column] = self::checkOf($field);
            }
        }

        return $fields;
    }

    /**
     * The check of a field of a card class, by its kind.
     *
     * @param  CardField  $field  the field
     */
    private static function checkOf(CardField $field): string
    {
        return match ($field->kind) {
            FieldKind::Text, FieldKind::Textarea => 'text',
            FieldKind::Date => 'date',
            FieldKind::Currency => 'fee',
            FieldKind::Number => 'number',
            FieldKind::Toggle => 'toggle',
            FieldKind::Choice, FieldKind::Dropdown => self::CHOICE.$field->enum,
            FieldKind::Person, FieldKind::Organization, FieldKind::Card => 'id',
        };
    }

    /**
     * The corrections of the movement that a movement of this kind has: an income has no category, fee or deadline.
     *
     * @param  string  $kind  `expense` or `income`
     * @return array<string, mixed>
     */
    public function movementOverrides(string $kind): array
    {
        return $kind === 'expense'
            ? $this->overrides['movement']
            : array_diff_key($this->overrides['movement'], array_flip(self::EXPENSE_ONLY));
    }

    /**
     * The fields of a side that pass their check. A title is taken only when it is not blank.
     *
     * @param  array<mixed>  $source  what the page sent
     * @param  array<string, string>  $fields  the field and its check
     * @param  list<string>  $keepRead  the fields that, emptied, leave the reading instead of clearing it
     * @return array<string, mixed>
     */
    private static function clean(array $source, array $fields, array $keepRead = []): array
    {
        $out = [];
        foreach ($fields as $field => $rule) {
            if (! array_key_exists($field, $source)) {
                continue;
            }

            $value = $source[$field];
            if ($value === null || $value === '') {
                if ($rule !== 'title' && ! in_array($field, $keepRead, true)) {
                    $out[$field] = null;
                }

                continue;
            }

            $checked = self::check($rule, $value);
            if ($checked !== null) {
                $out[$field] = $checked;
            }
        }

        return $out;
    }

    /**
     * The corrections of the cards, by kind in the order they are created.
     *
     * @param  array<mixed>  $input  what the page sent, by the value of `RecordKind`
     * @return array<string, array<string, mixed>>
     */
    private static function recordEdits(array $input): array
    {
        $out = [];
        foreach (RecordKind::cases() as $kind) {
            // The box of the card of the document category is cleaned once its type is known
            if ($kind !== RecordKind::Card && is_array($input[$kind->value] ?? null)) {
                $out[$kind->value] = self::clean($input[$kind->value], self::recordFields($kind, null));
            }
        }

        return $out;
    }

    /**
     * The cards picked by hand, by kind in the order they are created; unknown kinds are dropped.
     *
     * @param  array<mixed>  $input  what the page sent, by the value of `RecordKind`
     * @return array<string, 'auto'|'new'|int>
     */
    private static function cleanTargets(array $input): array
    {
        $out = [];
        foreach (RecordKind::cases() as $kind) {
            if (array_key_exists($kind->value, $input)) {
                $out[$kind->value] = self::target($input[$kind->value]);
            }
        }

        return $out;
    }

    /**
     * A target: `new`, a positive id, otherwise `auto`.
     *
     * @param  mixed  $value  what the page sent
     * @return 'auto'|'new'|int
     */
    private static function target(mixed $value): string|int
    {
        return $value === 'new' ? 'new' : self::id($value) ?? 'auto';
    }

    /**
     * The value checked by its rule, or null when it does not pass.
     *
     * @param  string  $rule  the name of the check
     * @param  mixed  $value  a value that is not empty
     */
    private static function check(string $rule, mixed $value): mixed
    {
        if (str_starts_with($rule, self::CHOICE)) {
            $enum = substr($rule, strlen(self::CHOICE));

            return is_subclass_of($enum, \BackedEnum::class) ? self::oneOf($value, $enum::cases()) : null;
        }

        return match ($rule) {
            'title', 'text' => is_string($value) && trim($value) !== '' && mb_strlen(trim($value)) <= self::MAX_TEXT ? trim($value) : null,
            'date' => is_string($value) ? ValidDate::of($value) : null,
            'id' => self::id($value),
            'price', 'fee' => is_numeric($value) && (float) $value >= 0 && (float) $value <= self::MAX_AMOUNT ? round((float) $value, 2) : null,
            'year' => self::id($value) !== null && self::id($value) > 1900 ? self::id($value) : null,
            'number' => is_int($value) || (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) ? (int) $value : null,
            'toggle' => self::bool($value),
            'health' => self::oneOf($value, HealthEventType::cases()),
            'utility' => self::oneOf($value, UtilityType::cases()),
            'insurance' => self::oneOf($value, InsurancePolicyType::cases()),
            'maintenance' => self::oneOf($value, MaintenanceType::cases()),
            'types' => self::types($value),
            default => null,
        };
    }

    /**
     * A positive whole number, also written as text.
     *
     * @param  mixed  $value  what the page sent
     */
    private static function id(mixed $value): ?int
    {
        if (is_string($value) && ctype_digit($value)) {
            $value = (int) $value;
        }

        return is_int($value) && $value > 0 ? $value : null;
    }

    /**
     * The value, if it is one of the cases of the enum.
     *
     * @param  mixed  $value  what the page sent
     * @param  list<\BackedEnum>  $cases  the cases of the enum
     */
    private static function oneOf(mixed $value, array $cases): ?string
    {
        return is_string($value) && in_array($value, array_map(static fn (\BackedEnum $case): string|int => $case->value, $cases), true) ? $value : null;
    }

    /**
     * The organization types among those sent (one or a list), or null when none is valid.
     *
     * @param  mixed  $value  what the page sent
     * @return list<string>|null
     */
    private static function types(mixed $value): ?array
    {
        $types = array_values(array_filter(
            array_map(static fn (mixed $type): ?string => self::oneOf($type, OrganizationType::cases()), is_array($value) ? $value : [$value]),
            static fn (?string $type): bool => $type !== null,
        ));

        return $types === [] ? null : $types;
    }

    /**
     * The positive ids of a list, each once, in the order they were sent; anything else is dropped.
     *
     * @param  mixed  $value  what the page sent
     * @return list<int>
     */
    private static function ids(mixed $value): array
    {
        $ids = [];
        foreach (is_array($value) ? $value : [] as $item) {
            $id = self::id($item);
            if ($id !== null && ! in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * The ticked cards, each once, in the order they were sent; unknown kinds are dropped.
     *
     * @param  mixed  $value  what the page sent
     * @return list<RecordKind>
     */
    private static function kinds(mixed $value): array
    {
        $kinds = [];
        foreach (is_array($value) ? $value : [] as $kind) {
            $case = is_string($kind) ? RecordKind::tryFrom($kind) : null;
            if ($case !== null && ! in_array($case, $kinds, true)) {
                $kinds[] = $case;
            }
        }

        return $kinds;
    }

    /**
     * A yes or no as JSON or a form sends it, or null when it is neither.
     *
     * @param  mixed  $value  what the page sent
     */
    private static function bool(mixed $value): ?bool
    {
        return is_bool($value) ? $value : (is_scalar($value) ? filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : null);
    }

    /**
     * The value if it is an array, otherwise an empty one.
     *
     * @param  mixed  $value  what the page sent
     * @return array<mixed>
     */
    private static function arrayOf(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    /**
     * The entries of an array with a text key.
     *
     * @param  mixed  $value  what the page sent
     * @return array<string, mixed>
     */
    private static function stringKeys(mixed $value): array
    {
        return array_filter(self::arrayOf($value), is_string(...), ARRAY_FILTER_USE_KEY);
    }
}

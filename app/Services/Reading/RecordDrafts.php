<?php

declare(strict_types=1);

namespace App\Services\Reading;

use App\Cards\CardField;
use App\Cards\CardType;
use App\Cards\CardTypes;
use App\Data\Reading\Extraction;
use App\Data\Reading\RecordDraftData;
use App\Enums\InsurancePolicyType;
use App\Enums\MaintenanceType;
use App\Enums\RecordKind;
use App\Enums\UtilityType;
use App\Models\Belonging;
use App\Models\Home;
use App\Models\InsurancePolicy;
use App\Models\MaintenanceRecord;
use App\Models\Organization;
use App\Models\Utility;
use App\Models\Vehicle;
use App\Support\Reading\RecordLabel;
use App\Support\Reading\ValidDate;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * The cards a file names that are missing: a code nobody has (utility, policy, vehicle), a durable good, an
 * intervention, an issuer that is not among the organizations. Nothing is written: the page shows them as boxes.
 */
final class RecordDrafts
{
    /** From this price (in euro, included) a durable good starts ticked: below, the box is there and the person ticks it. */
    public const int BELONGING_TICK_EUR = 100;

    /** The legal warranty, when the paper does not print one. */
    public const int WARRANTY_MONTHS = 24;

    /** The kinds of work that belong to a vehicle. */
    public const array VEHICLE_WORK = ['service', 'inspection', 'repair'];

    /** The papers an intervention can come from. */
    public const array MAINTENANCE_KINDS = ['receipt', 'invoice'];

    /** The papers for which a new organization starts ticked. */
    public const array ORGANIZATION_TICKED_KINDS = ['utility_bill', 'invoice', 'insurance'];

    /**
     * The end of the warranty: the printed one, otherwise the purchase date plus 24 months (the last day of a shorter month).
     *
     * @param  CarbonImmutable|null  $purchase  the day of the purchase
     * @param  CarbonImmutable|null  $printed  the end printed on the paper
     */
    public static function warrantyEnd(?CarbonImmutable $purchase, ?CarbonImmutable $printed): ?CarbonImmutable
    {
        return $printed ?? $purchase?->addMonthsNoOverflow(self::WARRANTY_MONTHS);
    }

    /**
     * The cards of the paper that are missing, in the order they are created. With the card of the document
     * category, the cards the reading would propose make room for the box of that card; the organization stays.
     *
     * @param  Extraction  $x  the reading
     * @param  array{utility: ?Utility, terminatedUtility: ?Utility, vehicle: ?Vehicle, policy: ?InsurancePolicy, organization: ?Organization, belonging: ?Belonging, maintenance: ?MaintenanceRecord, homes: Collection<int, Home>, card?: ?Model}  $found  what the searches found (`Matcher` and `RecordSearch`); `homes` are the active ones, `card` the card of the category with the issuer of the document
     * @param  array{organization_id: ?int, organization_name: ?string, person_id: ?int, date: ?CarbonImmutable}  $document  the document as the person will save it: its issuer, person and date are the card's too
     * @param  float|null  $amount  the total of the paper
     * @param  string|null  $cardType  the card of the document category; null when the reading decides
     * @param  bool  $withCard  whether the card of the category has its box (not for a health event, which has its own, nor for a job the person already has)
     * @return list<RecordDraftData>
     */
    public static function of(Extraction $x, array $found, array $document, ?float $amount, ?string $cardType = null, bool $withCard = true): array
    {
        $drafts = match (true) {
            $cardType === null => self::fromReading($x, $found, $document, $amount),
            $withCard => [self::forCategory($cardType, $x, $found, $document, $amount)],
            default => [],
        };

        $organization = self::organization($x, $found, $document, $drafts);
        if ($organization instanceof RecordDraftData) {
            $drafts[] = $organization;
        }

        $order = array_column(RecordKind::cases(), 'value');
        usort($drafts, static fn (RecordDraftData $a, RecordDraftData $b): int => array_search($a->kind->value, $order, true) <=> array_search($b->kind->value, $order, true));

        return $drafts;
    }

    /**
     * The box of the card of the document category. The five cards the reading fills are built as the reading
     * builds them, without its conditions (with the category the box is always there), and link the card the
     * searches found; any other card is a `Card` with the name or title read, the issuer and person of the
     * document, and the required fields of its class still to write.
     *
     * @param  string  $type  the type of the morph map (`utility`, `home`)
     * @param  Extraction  $x  the reading
     * @param  array{utility: ?Utility, terminatedUtility: ?Utility, vehicle: ?Vehicle, policy: ?InsurancePolicy, organization: ?Organization, belonging: ?Belonging, maintenance: ?MaintenanceRecord, homes: Collection<int, Home>, card?: ?Model}  $found  what the searches found
     * @param  array{organization_id: ?int, organization_name: ?string, person_id: ?int, date: ?CarbonImmutable}  $document  the document as the person will save it
     * @param  float|null  $amount  the total of the paper
     */
    public static function forCategory(string $type, Extraction $x, array $found, array $document, ?float $amount): RecordDraftData
    {
        return match (RecordKind::forCardType($type)) {
            RecordKind::Utility => self::utility($x, $found, $document, RecordLabel::linked($found['utility'])),
            RecordKind::Vehicle => self::vehicle($x, $document, RecordLabel::linked($found['vehicle'])),
            RecordKind::Policy => self::policy($x, $found, $document, RecordLabel::linked($found['policy'])),
            RecordKind::Belonging => self::belonging($x, $found, $document, $amount),
            RecordKind::Maintenance => self::maintenance($x, $found, $document, false),
            default => self::card($type, $x, $document, RecordLabel::linked($found['card'] ?? null)),
        };
    }

    /**
     * The cards the reading proposes, each with its conditions.
     *
     * @param  Extraction  $x  the reading
     * @param  array{utility: ?Utility, terminatedUtility: ?Utility, vehicle: ?Vehicle, policy: ?InsurancePolicy, organization: ?Organization, belonging: ?Belonging, maintenance: ?MaintenanceRecord, homes: Collection<int, Home>, card?: ?Model}  $found  what the searches found
     * @param  array{organization_id: ?int, organization_name: ?string, person_id: ?int, date: ?CarbonImmutable}  $document  the document as the person will save it
     * @param  float|null  $amount  the total of the paper
     * @return list<RecordDraftData>
     */
    private static function fromReading(Extraction $x, array $found, array $document, ?float $amount): array
    {
        $drafts = [];

        if ((self::has($x->supply_point_code) || self::has($x->customer_code)) && ! $found['utility'] instanceof Utility
            && ($x->kind === 'utility_bill' || self::has($x->utility_type))) {
            $drafts[] = self::utility($x, $found, $document);
        }

        $vehiclePaper = in_array($x->kind, ['insurance', 'road_tax'], true)
            || in_array($x->maintenance_type, self::VEHICLE_WORK, true)
            || self::has($x->vehicle_brand);
        if (self::has($x->plate_number) && ! $found['vehicle'] instanceof Vehicle && $vehiclePaper) {
            $drafts[] = self::vehicle($x, $document);
        }

        if ($x->kind === 'insurance' && self::has($x->policy_number) && ! $found['policy'] instanceof InsurancePolicy) {
            $drafts[] = self::policy($x, $found, $document);
        }

        if ($x->durable_good === true) {
            $drafts[] = self::belonging($x, $found, $document, $amount);
        }

        if (self::has($x->maintenance_type) && in_array($x->kind, self::MAINTENANCE_KINDS, true)) {
            $onVehicle = array_filter($drafts, static fn (RecordDraftData $draft): bool => $draft->kind === RecordKind::Vehicle) !== [];
            $drafts[] = self::maintenance($x, $found, $document, $onVehicle);
        }

        return $drafts;
    }

    /**
     * The box of a utility, with the home and holder of a terminated one with the same code.
     *
     * @param  Extraction  $x  the reading
     * @param  array{terminatedUtility: ?Utility, homes: Collection<int, Home>, ...}  $found  what the searches found
     * @param  array{organization_id: ?int, organization_name: ?string, person_id: ?int, date: ?CarbonImmutable}  $document  the document
     * @param  array{id: int, title: string}|null  $existing  the utility found, to link
     */
    private static function utility(Extraction $x, array $found, array $document, ?array $existing = null): RecordDraftData
    {
        $terminated = $found['terminatedUtility'];
        $code = self::has($x->supply_point_code) ? (string) $x->supply_point_code : (string) $x->customer_code;
        $name = self::has($x->utility_type)
            ? ucfirst(UtilityType::tryFrom((string) $x->utility_type)?->label() ?? (string) $x->utility_type)
            : $code;
        // Without a type or a code (a box of the category) the issuer alone does not name it
        $title = $name === '' ? __('the utility') : __('the utility :name', ['name' => self::withIssuer($name, $x, $document)]);

        return self::draft($x, RecordKind::Utility, $title, [
            'type' => $x->utility_type,
            'supply_point_code' => $x->supply_point_code,
            'customer_code' => $x->customer_code,
            'organization_id' => $document['organization_id'],
            'person_id' => $terminated->person_id ?? $document['person_id'],
            'home_id' => $terminated->home_id ?? self::onlyHome($found),
            'activation_date' => ValidDate::of($x->contract_start_date),
        ], true, $existing);
    }

    /**
     * The box of a vehicle, named by its model and plate.
     *
     * @param  Extraction  $x  the reading
     * @param  array{organization_id: ?int, organization_name: ?string, person_id: ?int, date: ?CarbonImmutable}  $document  the document
     * @param  array{id: int, title: string}|null  $existing  the vehicle found, to link
     */
    private static function vehicle(Extraction $x, array $document, ?array $existing = null): RecordDraftData
    {
        $plate = (string) $x->plate_number;
        $name = $x->vehicle_model ?? ($plate === '' ? null : $plate);
        $title = match (true) {
            $name === null => __('the vehicle'),
            $name === $plate => __('the vehicle :plate', ['plate' => $plate]),
            $plate === '' => __('the vehicle :name', ['name' => $name]),
            default => __('the vehicle :name (:plate)', ['name' => $name, 'plate' => $plate]),
        };

        return self::draft($x, RecordKind::Vehicle, $title, [
            'name' => $name,
            'plate_number' => $plate === '' ? null : $plate,
            'brand' => $x->vehicle_brand,
            'model' => $x->vehicle_model,
            'registration_year' => $x->vehicle_year,
            'person_id' => $document['person_id'],
        ], true, $existing);
    }

    /**
     * The box of a policy, titled by its type, on the vehicle found or the only home.
     *
     * @param  Extraction  $x  the reading
     * @param  array{vehicle: ?Vehicle, homes: Collection<int, Home>, ...}  $found  what the searches found
     * @param  array{organization_id: ?int, organization_name: ?string, person_id: ?int, date: ?CarbonImmutable}  $document  the document
     * @param  array{id: int, title: string}|null  $existing  the policy found, to link
     */
    private static function policy(Extraction $x, array $found, array $document, ?array $existing = null): RecordDraftData
    {
        // Without a number (a box of the category) the issuer alone does not name it
        $title = self::has($x->policy_number)
            ? __('the policy :name', ['name' => self::withIssuer((string) $x->policy_number, $x, $document)])
            : __('the policy');

        return self::draft($x, RecordKind::Policy, $title, [
            'title' => self::policyTitle($x->insurance_type),
            'policy_number' => $x->policy_number,
            'type' => $x->insurance_type,
            'organization_id' => $document['organization_id'],
            'person_id' => $document['person_id'],
            'start_date' => ValidDate::of($x->contract_start_date) ?? ValidDate::of($x->period_start),
            'expiry_date' => ValidDate::of($x->expiry_date),
            'vehicle_id' => $found['vehicle']?->id,
            'home_id' => $x->insurance_type === 'home' ? self::onlyHome($found) : null,
        ], true, $existing);
    }

    /**
     * The box of a durable good, ticked from {@see BELONGING_TICK_EUR}, linking the one already there.
     *
     * @param  Extraction  $x  the reading
     * @param  array{belonging: ?Belonging, homes: Collection<int, Home>, ...}  $found  what the searches found
     * @param  array{organization_id: ?int, organization_name: ?string, person_id: ?int, date: ?CarbonImmutable}  $document  the document
     * @param  float|null  $amount  the total of the paper
     */
    private static function belonging(Extraction $x, array $found, array $document, ?float $amount): RecordDraftData
    {
        $name = $x->item_name ?? $x->title;
        $belonging = $found['belonging'];
        $label = self::joined($name, $x->item_brand, $x->item_model);

        return self::draft(
            $x,
            RecordKind::Belonging,
            $label === '' ? __('the item') : __('the item :name', ['name' => $label]),
            [
                'name' => $name,
                'brand' => $x->item_brand,
                'model' => $x->item_model,
                'purchase_date' => $document['date']?->toDateString(),
                'purchase_price' => $amount,
                'organization_id' => $document['organization_id'],
                'home_id' => self::onlyHome($found),
                'warranty_expiry_date' => self::warrantyEnd($document['date'], ValidDate::carbon($x->warranty_expiry_date))?->toDateString(),
            ],
            $amount !== null && $amount >= self::BELONGING_TICK_EUR,
            RecordLabel::linked($belonging),
        );
    }

    /**
     * The box of an intervention, on the vehicle found or proposed, otherwise on the only home.
     *
     * @param  Extraction  $x  the reading
     * @param  array{vehicle: ?Vehicle, maintenance: ?MaintenanceRecord, homes: Collection<int, Home>, ...}  $found  what the searches found
     * @param  array{organization_id: ?int, organization_name: ?string, person_id: ?int, date: ?CarbonImmutable}  $document  the document
     * @param  bool  $vehicleProposed  a box proposes the vehicle of the paper
     */
    private static function maintenance(Extraction $x, array $found, array $document, bool $vehicleProposed): RecordDraftData
    {
        $title = $x->title ?? MaintenanceType::tryFrom((string) $x->maintenance_type)?->label();
        $onVehicle = $found['vehicle'] instanceof Vehicle || $vehicleProposed;
        $record = $found['maintenance'];

        return self::draft(
            $x,
            RecordKind::Maintenance,
            $title === null ? __('the intervention') : __('the intervention :name', ['name' => $title]),
            [
                'title' => $title,
                'date' => $document['date']?->toDateString(),
                'type' => $x->maintenance_type,
                'organization_id' => $document['organization_id'],
                'vehicle_id' => $found['vehicle']?->id,
                'home_id' => $onVehicle ? null : self::onlyHome($found),
            ],
            true,
            RecordLabel::linked($record),
        );
    }

    /**
     * The box of any other card of the category: the name or title read where its class has one, the issuer and
     * the person of the document where it has them.
     *
     * @param  string  $type  the type of the morph map
     * @param  Extraction  $x  the reading
     * @param  array{organization_id: ?int, organization_name: ?string, person_id: ?int, date: ?CarbonImmutable}  $document  the document
     * @param  array{id: int, title: string}|null  $existing  the card of that type with the issuer of the document, to link
     */
    private static function card(string $type, Extraction $x, array $document, ?array $existing): RecordDraftData
    {
        $card = CardTypes::find($type);
        $columns = $card instanceof CardType ? array_map(static fn (CardField $field): string => $field->column, $card->fields()) : [];
        $values = array_filter([
            'name' => $x->title,
            'title' => $x->title,
            'organization_id' => $document['organization_id'],
            'person_id' => $document['person_id'],
        ], static fn (string $column): bool => in_array($column, $columns, true), ARRAY_FILTER_USE_KEY);

        $missing = $existing === null ? self::missing(RecordKind::Card, $values, $type) : [];

        return new RecordDraftData(
            kind: RecordKind::Card,
            title: self::cardTitle($type, $card),
            values: $values,
            suggested: $missing === [] && $x->confidence !== 'low',
            missing: $missing,
            existing: $existing,
            cardType: $type,
        );
    }

    /**
     * The text of the box of a card without the verb, in the language of the app (`the residence`).
     *
     * @param  string  $type  the type of the morph map
     * @param  CardType|null  $card  its card type
     */
    private static function cardTitle(string $type, ?CardType $card): string
    {
        return match ($type) {
            'home' => __('the residence'),
            'financial_account' => __('the account or card'),
            'loan' => __('the loan'),
            'subscription' => __('the subscription'),
            'filing' => __('the filing'),
            'employment' => __('the job'),
            'health_event' => __('the health event'),
            'enrollment' => __('the enrollment'),
            // A card added later still has a title, from the name of its type
            default => __('the card :name', ['name' => mb_strtolower($card?->label() ?? $type)]),
        };
    }

    /**
     * The organization the paper names that is not chosen in the document: proposed, to be created or linked.
     *
     * @param  Extraction  $x  the reading
     * @param  array{organization: ?Organization, ...}  $found  what the searches found
     * @param  array{organization_id: ?int, organization_name: ?string, person_id: ?int, date: ?CarbonImmutable}  $document  the document
     * @param  list<RecordDraftData>  $drafts  the boxes of the cards, some of which need the organization
     */
    private static function organization(Extraction $x, array $found, array $document, array $drafts): ?RecordDraftData
    {
        // An issuer found by name but not chosen by the reading (created by a file just before): proposed, to be linked.
        if (! self::has($x->issuer_name) || $document['organization_id'] !== null) {
            return null;
        }

        $needed = array_filter(
            $drafts,
            static fn (RecordDraftData $draft): bool => $draft->kind === RecordKind::Utility || $draft->kind === RecordKind::Policy,
        ) !== [];
        $organization = $found['organization'];

        return self::draft(
            $x,
            RecordKind::Organization,
            __('the organization :name', ['name' => (string) $x->issuer_name]),
            ['name' => $x->issuer_name, 'types' => self::has($x->organization_type) ? [$x->organization_type] : []],
            $organization instanceof Organization || $needed || in_array($x->kind, self::ORGANIZATION_TICKED_KINDS, true),
            RecordLabel::linked($organization),
        );
    }

    /**
     * The only active home, which a new utility, item or intervention belongs to.
     *
     * @param  array{homes: Collection<int, Home>, ...}  $found  what the searches found
     */
    private static function onlyHome(array $found): ?int
    {
        return $found['homes']->count() === 1 ? $found['homes']->first()?->id : null;
    }

    /**
     * A name followed by the issuer of the document, or the one read (`Electricity · Edison`).
     *
     * @param  string  $name  the name
     * @param  Extraction  $x  the reading
     * @param  array{organization_name: ?string, ...}  $document  the document
     */
    private static function withIssuer(string $name, Extraction $x, array $document): string
    {
        $issuer = $document['organization_name'] ?? $x->issuer_name;

        return self::joined($name, self::has($issuer) ? '· '.$issuer : null);
    }

    /**
     * One box: the required fields still empty and whether it starts ticked are worked out here.
     *
     * @param  Extraction  $x  the reading (an uncertain one ticks nothing)
     * @param  RecordKind  $kind  which card
     * @param  string  $title  the text of the box without the verb
     * @param  array<string, mixed>  $values  the fields the card is created with
     * @param  bool  $ticked  whether the kind of paper asks for it to start ticked
     * @param  array{id: int, title: string}|null  $existing  the card already there, to link
     */
    private static function draft(Extraction $x, RecordKind $kind, string $title, array $values, bool $ticked, ?array $existing = null): RecordDraftData
    {
        $missing = $existing === null ? self::missing($kind, $values) : [];

        return new RecordDraftData($kind, $title, $values, $ticked && $missing === [] && $x->confidence !== 'low', $missing, $existing, cardType: $kind->cardType());
    }

    /**
     * The words of the required fields of a card that are still empty. The required fields are those of the card
     * class (`app/Cards/Types`), except the ones that depend on another choice; an organization only needs its name.
     *
     * @param  RecordKind  $kind  which card
     * @param  array<string, mixed>  $values  the fields it would be created with
     * @param  string|null  $cardType  the type of the morph map of a `Card`
     * @return list<string>
     */
    public static function missing(RecordKind $kind, array $values, ?string $cardType = null): array
    {
        $missing = [];
        foreach (self::required($kind, $cardType) as $column) {
            if (($values[$column] ?? null) === null || $values[$column] === '') {
                $missing[] = match ($column) {
                    'name' => __('the name'),
                    'type' => __('the type'),
                    'title' => __('the title'),
                    'date' => __('the date'),
                    'person_id' => __('the person'),
                    default => $column,
                };
            }
        }

        return $missing;
    }

    /**
     * The columns a card cannot be created without.
     *
     * @param  RecordKind  $kind  which card
     * @param  string|null  $cardType  the type of the morph map of a `Card`
     * @return list<string>
     */
    private static function required(RecordKind $kind, ?string $cardType = null): array
    {
        return $kind === RecordKind::Organization
            ? ['name']
            : array_map(static fn (CardField $field): string => $field->column, self::requiredFields($kind, $cardType));
    }

    /**
     * The fields of the card class a card cannot be created without, except the ones that depend on another choice;
     * none for an organization, which has no card class and only needs its name.
     *
     * @param  RecordKind  $kind  which card
     * @param  string|null  $cardType  the type of the morph map of a `Card`, whose class is read from `CardTypes` too
     * @return list<CardField>
     */
    public static function requiredFields(RecordKind $kind, ?string $cardType = null): array
    {
        $type = $kind === RecordKind::Card ? $cardType : $kind->cardType();
        $card = $type === null ? null : CardTypes::find($type);
        if (! $card instanceof CardType) {
            return [];
        }

        return array_values(array_filter(
            $card->fields(),
            static fn (CardField $field): bool => $field->required && ! $field->virtual && $field->showWhen === [],
        ));
    }

    /**
     * The title proposed for a new policy, from its type.
     *
     * @param  string|null  $type  a value of `InsurancePolicyType`
     */
    private static function policyTitle(?string $type): string
    {
        return match (InsurancePolicyType::tryFrom((string) $type)) {
            InsurancePolicyType::Car => __('Car insurance'),
            InsurancePolicyType::Life => __('Life insurance'),
            InsurancePolicyType::Home => __('Home insurance'),
            InsurancePolicyType::Health => __('Health insurance'),
            InsurancePolicyType::Accident => __('Accident policy'),
            InsurancePolicyType::Fund => __('Fund'),
            default => __('Policy'),
        };
    }

    /**
     * The non-blank parts joined by a space.
     *
     * @param  string|null  ...$parts  the parts, some of them maybe empty
     */
    private static function joined(?string ...$parts): string
    {
        return implode(' ', array_filter($parts, static fn (?string $part): bool => $part !== null && trim($part) !== ''));
    }

    /**
     * Whether a read text is there: not null and not empty.
     *
     * @param  string|null  $value  the text
     */
    private static function has(?string $value): bool
    {
        return $value !== null && $value !== '';
    }
}

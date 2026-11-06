<?php

declare(strict_types=1);

namespace App\Services\Reading;

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
     * The cards of the paper that are missing, in the order they are created.
     *
     * @param  Extraction  $x  the reading
     * @param  array{utility: ?Utility, terminatedUtility: ?Utility, vehicle: ?Vehicle, policy: ?InsurancePolicy, organization: ?Organization, belonging: ?Belonging, maintenance: ?MaintenanceRecord, homes: Collection<int, Home>}  $found  what the searches found (`Matcher` and `RecordSearch`); `homes` are the active ones
     * @param  array{organization_id: ?int, organization_name: ?string, person_id: ?int, date: ?CarbonImmutable}  $document  the document as the person will save it: its issuer, person and date are the card's too
     * @param  float|null  $amount  the total of the paper
     * @return list<RecordDraftData>
     */
    public static function of(Extraction $x, array $found, array $document, ?float $amount): array
    {
        /** @var list<RecordDraftData> $drafts */
        $drafts = [];

        $onlyHome = $found['homes']->count() === 1 ? $found['homes']->first()?->id : null;
        $issuer = $document['organization_name'] ?? $x->issuer_name;
        $withIssuer = static fn (string $name): string => self::joined($name, self::has($issuer) ? '· '.$issuer : null);

        if ((self::has($x->supply_point_code) || self::has($x->customer_code)) && ! $found['utility'] instanceof Utility
            && ($x->kind === 'utility_bill' || self::has($x->utility_type))) {
            $terminated = $found['terminatedUtility'];
            $name = self::has($x->utility_type)
                ? ucfirst(UtilityType::tryFrom((string) $x->utility_type)?->label() ?? (string) $x->utility_type)
                : (self::has($x->supply_point_code) ? (string) $x->supply_point_code : (string) $x->customer_code);
            $drafts[] = self::draft($x, RecordKind::Utility, __('the utility :name', ['name' => $withIssuer($name)]), [
                'type' => $x->utility_type,
                'supply_point_code' => $x->supply_point_code,
                'customer_code' => $x->customer_code,
                'organization_id' => $document['organization_id'],
                'person_id' => $terminated->person_id ?? $document['person_id'],
                'home_id' => $terminated->home_id ?? $onlyHome,
                'activation_date' => ValidDate::of($x->contract_start_date),
            ], true);
        }

        $vehiclePaper = in_array($x->kind, ['insurance', 'road_tax'], true)
            || in_array($x->maintenance_type, self::VEHICLE_WORK, true)
            || self::has($x->vehicle_brand);
        if (self::has($x->plate_number) && ! $found['vehicle'] instanceof Vehicle && $vehiclePaper) {
            $plate = (string) $x->plate_number;
            $name = $x->vehicle_model ?? $plate;
            $drafts[] = self::draft(
                $x,
                RecordKind::Vehicle,
                $name === $plate ? __('the vehicle :plate', ['plate' => $plate]) : __('the vehicle :name (:plate)', ['name' => $name, 'plate' => $plate]),
                [
                    'name' => $name,
                    'plate_number' => $plate,
                    'brand' => $x->vehicle_brand,
                    'model' => $x->vehicle_model,
                    'registration_year' => $x->vehicle_year,
                    'person_id' => $document['person_id'],
                ],
                true,
            );
        }

        if ($x->kind === 'insurance' && self::has($x->policy_number) && ! $found['policy'] instanceof InsurancePolicy) {
            $drafts[] = self::draft($x, RecordKind::Policy, __('the policy :name', ['name' => $withIssuer((string) $x->policy_number)]), [
                'title' => self::policyTitle($x->insurance_type),
                'policy_number' => $x->policy_number,
                'type' => $x->insurance_type,
                'organization_id' => $document['organization_id'],
                'person_id' => $document['person_id'],
                'start_date' => ValidDate::of($x->contract_start_date) ?? ValidDate::of($x->period_start),
                'expiry_date' => ValidDate::of($x->expiry_date),
                'vehicle_id' => $found['vehicle']?->id,
                'home_id' => $x->insurance_type === 'home' ? $onlyHome : null,
            ], true);
        }

        if ($x->durable_good === true) {
            $name = $x->item_name ?? $x->title;
            $belonging = $found['belonging'];
            $drafts[] = self::draft(
                $x,
                RecordKind::Belonging,
                __('the item :name', ['name' => self::joined($name, $x->item_brand, $x->item_model) ?: '?']),
                [
                    'name' => $name,
                    'brand' => $x->item_brand,
                    'model' => $x->item_model,
                    'purchase_date' => $document['date']?->toDateString(),
                    'purchase_price' => $amount,
                    'organization_id' => $document['organization_id'],
                    'home_id' => $onlyHome,
                    'warranty_expiry_date' => self::warrantyEnd($document['date'], ValidDate::carbon($x->warranty_expiry_date))?->toDateString(),
                ],
                $amount !== null && $amount >= self::BELONGING_TICK_EUR,
                $belonging instanceof Belonging ? ['id' => $belonging->id, 'title' => RecordLabel::of($belonging)] : null,
            );
        }

        if (self::has($x->maintenance_type) && in_array($x->kind, self::MAINTENANCE_KINDS, true)) {
            $title = $x->title ?? MaintenanceType::tryFrom((string) $x->maintenance_type)?->label();
            $onVehicle = $found['vehicle'] instanceof Vehicle
                || array_filter($drafts, static fn (RecordDraftData $draft): bool => $draft->kind === RecordKind::Vehicle) !== [];
            $record = $found['maintenance'];
            $drafts[] = self::draft(
                $x,
                RecordKind::Maintenance,
                __('the intervention :name', ['name' => $title ?? '?']),
                [
                    'title' => $title,
                    'date' => $document['date']?->toDateString(),
                    'type' => $x->maintenance_type,
                    'organization_id' => $document['organization_id'],
                    'vehicle_id' => $found['vehicle']?->id,
                    'home_id' => $onVehicle ? null : $onlyHome,
                ],
                true,
                $record instanceof MaintenanceRecord ? ['id' => $record->id, 'title' => RecordLabel::of($record)] : null,
            );
        }

        // An issuer found by name but not chosen by the reading (created by a file just before): proposed, to be linked.
        if (self::has($x->issuer_name) && $document['organization_id'] === null) {
            $needed = array_filter(
                $drafts,
                static fn (RecordDraftData $draft): bool => $draft->kind === RecordKind::Utility || $draft->kind === RecordKind::Policy,
            ) !== [];
            $organization = $found['organization'];
            $drafts[] = self::draft(
                $x,
                RecordKind::Organization,
                __('the organization :name', ['name' => (string) $x->issuer_name]),
                ['name' => $x->issuer_name, 'types' => self::has($x->organization_type) ? [$x->organization_type] : []],
                $organization instanceof Organization || $needed || in_array($x->kind, self::ORGANIZATION_TICKED_KINDS, true),
                $organization instanceof Organization ? ['id' => $organization->id, 'title' => RecordLabel::of($organization)] : null,
            );
        }

        $order = array_column(RecordKind::cases(), 'value');
        usort($drafts, static fn (RecordDraftData $a, RecordDraftData $b): int => array_search($a->kind->value, $order, true) <=> array_search($b->kind->value, $order, true));

        return $drafts;
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

        return new RecordDraftData($kind, $title, $values, $ticked && $missing === [] && $x->confidence !== 'low', $missing, $existing);
    }

    /**
     * The words of the required fields of a card that are still empty. The required fields are those of the card
     * class (`app/Cards/Types`), except the ones that depend on another choice; an organization only needs its name.
     *
     * @param  RecordKind  $kind  which card
     * @param  array<string, mixed>  $values  the fields it would be created with
     * @return list<string>
     */
    public static function missing(RecordKind $kind, array $values): array
    {
        $missing = [];
        foreach (self::required($kind) as $column) {
            if (($values[$column] ?? null) === null || $values[$column] === '') {
                $missing[] = match ($column) {
                    'name' => __('the name'),
                    'type' => __('the type'),
                    'title' => __('the title'),
                    'date' => __('the date'),
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
     * @return list<string>
     */
    private static function required(RecordKind $kind): array
    {
        $type = $kind->cardType();
        $card = $type === null ? null : CardTypes::find($type);
        if (! $card instanceof CardType) {
            return ['name'];
        }

        $columns = [];
        foreach ($card->fields() as $field) {
            if ($field->required && ! $field->virtual && $field->showWhen === []) {
                $columns[] = $field->column;
            }
        }

        return $columns;
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

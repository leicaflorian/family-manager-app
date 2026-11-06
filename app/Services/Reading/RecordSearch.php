<?php

declare(strict_types=1);

namespace App\Services\Reading;

use App\Enums\HomeStatus;
use App\Enums\UtilityStatus;
use App\Models\Belonging;
use App\Models\HealthEvent;
use App\Models\Home;
use App\Models\MaintenanceRecord;
use App\Models\Utility;
use App\Support\Money;
use App\Support\Reading\Codes;
use App\Support\Reading\IssuerOrder;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * The searches of what a reading may already have registered besides the movements: health events,
 * terminated utilities, homes, belongings and maintenance records. Every query goes through the family
 * scope of the models, and everyone in the family reads everything, so there is no permission case.
 */
final readonly class RecordSearch
{
    /** Days around the date of a paper within which a health event of the same place and type is the same visit. */
    public const int EVENT_WINDOW_DAYS = 7;

    /** Days around the date of a paper within which a maintenance record of the same type is the same intervention. */
    public const int MAINTENANCE_WINDOW_DAYS = 7;

    /**
     * @param  Matcher  $matcher  the exact searches by code, reused for the terminated utility
     */
    public function __construct(private Matcher $matcher = new Matcher) {}

    /**
     * The health event a paper belongs to, among the person's: one of the same day (in the same place, or
     * with the place missing on one side), otherwise one of the same place and type within a few days,
     * the closest first and, at the same distance, the older.
     *
     * @param  int  $personId  the person of the paper
     * @param  CarbonImmutable  $date  the date of the paper
     * @param  int|null  $organizationId  the place, if known
     * @param  string|null  $type  the value of the health event type, if known
     */
    public function event(int $personId, CarbonImmutable $date, ?int $organizationId, ?string $type): ?HealthEvent
    {
        $events = HealthEvent::query()
            ->where('person_id', $personId)
            ->whereBetween('date', [$date->subDays(self::EVENT_WINDOW_DAYS)->toDateString(), $date->addDays(self::EVENT_WINDOW_DAYS)->toDateString()])
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        $day = $date->toDateString();
        $sameDay = $events->first(
            static fn (HealthEvent $event): bool => $event->date->toDateString() === $day && IssuerOrder::allows($event->organization_id, $organizationId),
        );

        if ($sameDay !== null || $organizationId === null || $type === null) {
            return $sameDay;
        }

        // The collection is already sorted by date then id, and sortBy is stable, so a tie keeps the older one.
        return $events
            ->filter(static fn (HealthEvent $event): bool => $event->organization_id === $organizationId && $event->type->value === $type)
            ->sortBy(static fn (HealthEvent $event): int => IssuerOrder::daysApart($event->date, $date))
            ->first();
    }

    /**
     * A terminated utility with the code on the paper: the new one takes its home and holder.
     *
     * @param  string|null  $pod  the POD or PDR as printed
     * @param  string|null  $customer  the customer code as printed
     */
    public function terminatedUtility(?string $pod, ?string $customer): ?Utility
    {
        return $this->matcher->utility($pod, $customer, UtilityStatus::Terminated);
    }

    /**
     * The active homes, to pick the one a paper is about.
     *
     * @return Collection<int, Home>
     */
    public function activeHomes(): Collection
    {
        return Home::query()->where('status', HomeStatus::Active)->orderBy('id')->get();
    }

    /**
     * The belonging already recorded: same brand and model, or bought in the same shop on the same day
     * for the same price to the cent.
     *
     * @param  string|null  $brand  the brand as printed
     * @param  string|null  $model  the model as printed
     * @param  int|null  $organizationId  the shop
     * @param  CarbonImmutable|null  $date  the day of the purchase
     * @param  float|null  $price  the price paid
     */
    public function belonging(?string $brand, ?string $model, ?int $organizationId, ?CarbonImmutable $date, ?float $price): ?Belonging
    {
        $wantedBrand = Codes::normalize($brand);
        $wantedModel = Codes::normalize($model);
        $byModel = $wantedBrand !== '' && $wantedModel !== '';
        $byPurchase = $organizationId !== null && $date instanceof CarbonImmutable && $price !== null;

        if (! $byModel && ! $byPurchase) {
            return null;
        }

        $belongings = Belonging::query()->orderBy('id')->get();

        return $belongings->first(static fn (Belonging $belonging): bool => $byModel
            && Codes::normalize($belonging->brand) === $wantedBrand
            && Codes::normalize($belonging->model) === $wantedModel)
            ?? $belongings->first(static fn (Belonging $belonging): bool => $byPurchase
                && $belonging->organization_id === $organizationId
                && $belonging->purchase_date?->toDateString() === $date->toDateString()
                && $belonging->purchase_price !== null
                && Money::cents($belonging->purchase_price) === Money::cents($price));
    }

    /**
     * The intervention already recorded: same vehicle (or home, without one), same type, within a week,
     * the closest first.
     *
     * @param  int|null  $vehicleId  the vehicle, which wins over the home
     * @param  int|null  $homeId  the home
     * @param  string|null  $type  the value of the maintenance type
     * @param  CarbonImmutable|null  $date  the date of the paper
     */
    public function maintenance(?int $vehicleId, ?int $homeId, ?string $type, ?CarbonImmutable $date): ?MaintenanceRecord
    {
        if ($type === null || $type === '' || ! $date instanceof CarbonImmutable || ($vehicleId === null && $homeId === null)) {
            return null;
        }

        return MaintenanceRecord::query()
            ->when(
                $vehicleId !== null,
                static fn ($query) => $query->where('vehicle_id', $vehicleId),
                static fn ($query) => $query->where('home_id', $homeId),
            )
            ->where('type', $type)
            ->whereBetween('date', [$date->subDays(self::MAINTENANCE_WINDOW_DAYS)->toDateString(), $date->addDays(self::MAINTENANCE_WINDOW_DAYS)->toDateString()])
            ->orderBy('date')
            ->orderBy('id')
            ->get()
            ->sortBy(static fn (MaintenanceRecord $record): int => IssuerOrder::daysApart($record->date, $date))
            ->first();
    }
}

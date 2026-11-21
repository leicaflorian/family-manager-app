<?php

declare(strict_types=1);

namespace App\Services;

use App\Cards\CardTypes;
use App\Data\Consumption\ConsumptionPageData;
use App\Enums\ConsumptionOutcome;
use App\Enums\ConsumptionUnit;
use App\Enums\ReadingPurpose;
use App\Enums\UtilityStatus;
use App\Enums\UtilityType;
use App\Models\AiReading;
use App\Models\Document;
use App\Models\User;
use App\Models\Utility;
use App\Models\UtilityConsumption;
use App\Services\Reading\ReadingAllowance;
use App\Services\Reading\ReadingAvailability;
use App\Support\Reading\ReadingCost;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection as SupportCollection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Puts together the Consumption page of a utility: which utility is shown, which 12 months, and what the page needs
 * of them. The numbers come from `Consumption`; this class reads the database and the person's permissions.
 */
final readonly class ConsumptionPage
{
    /**
     * @param  Consumption  $consumption  the months, figures, bills and sentence
     * @param  ReadingAllowance  $allowance  the readings left this month
     * @param  ReadingAvailability  $availability  whether a reading can start now
     */
    public function __construct(
        private Consumption $consumption,
        private ReadingAllowance $allowance,
        private ReadingAvailability $availability,
    ) {}

    /**
     * Whether the current family has a utility measured in a unit (electricity, gas or water): the page exists only
     * then. Static, so the shared props do not build the service on every request.
     */
    public static function available(): bool
    {
        return Utility::query()->whereIn('type', UtilityType::measured())->exists();
    }

    /**
     * The utility the page shows: the one asked, if it is a measured utility of the family; without one, the first
     * active one of electricity, then gas, then water, else the first of those.
     *
     * @param  Collection<int, Utility>  $utilities  the measured utilities of the family, from `utilities()`
     * @param  int|null  $id  the id of the address, null when none is asked
     *
     * @throws NotFoundHttpException if the utility asked is not a measured utility of the family, or the family has none
     */
    public function choose(Collection $utilities, ?int $id): Utility
    {
        $utility = $id === null ? $utilities->first() : $utilities->firstWhere('id', $id);

        return $utility ?? throw new NotFoundHttpException;
    }

    /**
     * The page of a utility.
     *
     * @param  User  $user  who opens it, for what they may do
     * @param  Collection<int, Utility>  $utilities  the measured utilities of the family, from `utilities()`
     * @param  Utility  $utility  the utility, from `choose()`
     * @param  CarbonImmutable|null  $to  the last month asked, null for the latest month with a bill
     * @param  CarbonImmutable|null  $month  the month asked for the detail, null for none
     * @param  CarbonImmutable  $today  today
     * @param  string  $locale  the language of the texts
     *
     * @throws NotFoundHttpException if the months asked are not ones the page shows
     */
    public function build(User $user, Collection $utilities, Utility $utility, ?CarbonImmutable $to, ?CarbonImmutable $month, CarbonImmutable $today, string $locale): ConsumptionPageData
    {
        $rows = UtilityConsumption::query()->with(Consumption::relations())->where('utility_id', $utility->id)->get();

        [$from, $to, $previous, $next] = $this->window($rows, $to, $today);

        if ($month instanceof CarbonImmutable && ($month->lt($from) || $month->gt($to))) {
            throw new NotFoundHttpException;
        }

        $months = $this->consumption->months($rows, $from, $to);
        $unit = ConsumptionUnit::forUtility($utility->type) ?? throw new NotFoundHttpException;

        return new ConsumptionPageData(
            types: array_values($utilities->map(static fn (Utility $item): UtilityType => $item->type)->unique()->sortBy(static fn (UtilityType $type): int => (int) array_search($type, UtilityType::measured(), true))
                ->map(static fn (UtilityType $type): array => ['type' => $type->value, 'label' => $type->label()])->all()),
            utilities: array_values($utilities->where('type', $utility->type)
                ->map(fn (Utility $item): array => [
                    'id' => $item->id,
                    'title' => $this->title($item),
                    'start' => $item->activation_date?->toDateString(),
                    // `utilities` has no end date yet: a terminated utility cannot tell when it ended
                    'end' => null,
                ])->all()),
            utility: [
                'id' => $utility->id,
                'title' => $this->title($utility),
                'type' => $utility->type->value,
                'unit' => $unit->value,
                'href' => route('cards.show', ['type' => CardTypes::for($utility)->type(), 'id' => $utility->id]),
            ],
            from: $from->format('Y-m'),
            to: $to->format('Y-m'),
            previous: $previous?->format('Y-m'),
            next: $next?->format('Y-m'),
            months: $months,
            figures: $this->consumption->figures($rows, $from, $to),
            sentence: $this->consumption->sentence($rows, $months, $utility->type, $today, $locale),
            bills: $this->consumption->bills(
                $rows,
                $from,
                $to,
                static fn (UtilityConsumption $row): bool => $user->can('update', $row),
                static fn (UtilityConsumption $row): bool => $user->can('delete', $row),
            ),
            month: $month?->format('Y-m'),
            archive: $this->archive($utility, $locale),
            toCheck: $this->toCheck($utility),
            canCreate: $user->can('create', UtilityConsumption::class),
        );
    }

    /**
     * The measured utilities of the family, the active first, then by type (electricity, gas, water), then by id,
     * with what their titles need.
     *
     * @return Collection<int, Utility>
     */
    public function utilities(): Collection
    {
        return Utility::query()
            ->whereIn('type', UtilityType::measured())
            ->with(Utility::labelRelations())
            ->get()
            ->sortBy([
                static fn (Utility $a, Utility $b): int => ($a->status !== UtilityStatus::Active) <=> ($b->status !== UtilityStatus::Active),
                static fn (Utility $a, Utility $b): int => array_search($a->type, UtilityType::measured(), true) <=> array_search($b->type, UtilityType::measured(), true),
                static fn (Utility $a, Utility $b): int => $a->id <=> $b->id,
            ])
            ->values();
    }

    /**
     * The title of a utility in the page, with its home: «Luce - Edison · Casa di via Roma»; just the label without
     * a home.
     *
     * @param  Utility  $utility  the utility, with `labelRelations()`
     */
    private function title(Utility $utility): string
    {
        $home = $utility->home?->name;

        return $home === null || $home === '' ? $utility->label() : $utility->label().' · '.$home;
    }

    /**
     * The 12 months shown and the ends of the years before and after. The latest `to` is the month of the latest
     * bill (never beyond the current month), the earliest the eleventh month after the first bill (never beyond the
     * latest); without bills, the current month. A `to` beyond the current month or before the earliest is a page
     * that does not exist.
     *
     * @param  SupportCollection<int, UtilityConsumption>  $rows  the rows of the utility
     * @param  CarbonImmutable|null  $to  the month asked, null for the latest
     * @param  CarbonImmutable  $today  today
     * @return array{CarbonImmutable, CarbonImmutable, CarbonImmutable|null, CarbonImmutable|null} first and last month shown, `to` before and after
     *
     * @throws NotFoundHttpException if the month asked is out of those
     */
    private function window(SupportCollection $rows, ?CarbonImmutable $to, CarbonImmutable $today): array
    {
        $current = $today->startOfMonth();
        $last = $this->consumption->lastMonthWithData($rows);
        $latest = $last instanceof CarbonImmutable && $last->lt($current) ? $last : $current;
        $first = $this->consumption->firstMonthWithData($rows);
        $earliest = $first instanceof CarbonImmutable ? $first->addMonthsNoOverflow(11)->min($latest) : $latest;

        $to = $to?->startOfMonth() ?? $latest;

        if ($to->gt($current) || $to->lt($earliest)) {
            throw new NotFoundHttpException;
        }

        return [
            $to->subMonthsNoOverflow(11),
            $to,
            $to->gt($earliest) ? $to->subMonthsNoOverflow(12)->max($earliest) : null,
            $to->lt($latest) ? $to->addMonthsNoOverflow(12)->min($latest) : null,
        ];
    }

    /**
     * The bills of the utility still without consumption and never read for it: how many, the probable cost of
     * reading them, the readings left, whether they are being read and whether they can be.
     *
     * @param  Utility  $utility  the utility
     * @param  string  $locale  the language of the texts
     * @return array{count: int, cost: float, left: int, running: bool, can_read: bool}|null null when there is nothing to read
     */
    private function archive(Utility $utility, string $locale): ?array
    {
        $count = $this->bills($utility)
            ->whereDoesntHave('file.aiReadings', static fn (Builder $query): Builder => $query->where('purpose', ReadingPurpose::Consumption))
            ->count();
        $running = $this->archiveRunning();

        if ($count === 0 && ! $running) {
            return null;
        }

        $now = CarbonImmutable::now('Europe/Rome');
        // The consumption readings are short: their own average, once there is one, says more than the documents'
        $purpose = AiReading::query()->where('purpose', ReadingPurpose::Consumption)->whereNotNull('cost_usd')->exists()
            ? ReadingPurpose::Consumption
            : ReadingPurpose::Document;

        return [
            'count' => $count,
            'cost' => round(ReadingCost::averageFor($purpose) * $count, 4),
            'left' => $this->allowance->left($now),
            'running' => $running,
            'can_read' => $this->availability->unavailable($locale) === null,
        ];
    }

    /**
     * Whether the bills of the archive of the utility are being read. *Read the bills* does not exist yet, so nothing
     * is ever being read; the job that reads them fills this in, taking the utility.
     */
    private function archiveRunning(): bool
    {
        return false;
    }

    /**
     * The bills read for their consumption that could not be saved: the latest reading of each says why, unless it
     * was saved or the document is not a bill. A bill drops out once it has its consumption.
     *
     * @param  Utility  $utility  the utility
     * @return list<array{document_id: int, title: string, date: string|null, reason: string}>
     */
    private function toCheck(Utility $utility): array
    {
        $documents = $this->bills($utility)
            ->whereHas('file.aiReadings', static fn (Builder $query): Builder => $query->where('purpose', ReadingPurpose::Consumption))
            ->with(['file.aiReadings' => static fn (HasMany $query): HasMany => $query->where('purpose', ReadingPurpose::Consumption)->latest('created_at')->latest('id')])
            ->orderByDesc('date')
            ->orderByDesc('documents.id')
            ->get();

        $list = [];

        foreach ($documents as $document) {
            $outcome = $document->file->aiReadings->first()?->outcome;

            if ($outcome instanceof ConsumptionOutcome && ! in_array($outcome, [ConsumptionOutcome::Saved, ConsumptionOutcome::NotABill], true)) {
                $list[] = ['document_id' => $document->id, 'title' => $document->title, 'date' => $document->date?->toDateString(), 'reason' => $outcome->label()];
            }
        }

        return $list;
    }

    /**
     * The documents of the utility a reading can take (a PDF or a picture) that have no consumption yet.
     *
     * @param  Utility  $utility  the utility
     * @return MorphToMany<Document, Utility>
     */
    private function bills(Utility $utility): MorphToMany
    {
        return $utility->documents()
            ->whereDoesntHave('consumption')
            ->whereHas('file', static fn (Builder $query): Builder => $query->where(
                static fn (Builder $query): Builder => $query->where('mime_type', 'application/pdf')->orWhere('mime_type', 'like', 'image/%'),
            ));
    }
}

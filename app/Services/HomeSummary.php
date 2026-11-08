<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\AgendaEntryData;
use App\Data\ChecklistStepData;
use App\Data\HomeData;
use App\Data\HomeMailData;
use App\Data\MissingDocumentData;
use App\Data\PendingFileData;
use App\Enums\PersonRole;
use App\Models\Document;
use App\Models\File;
use App\Models\InsurancePolicy;
use App\Models\Invitation;
use App\Models\Person;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Utility;
use App\Models\Vehicle;
use App\Services\Mail\ReceivedMails;
use App\Services\Reminders\ExpectedDocuments;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;

/**
 * Puts together what the Home shows (analysis, points 3 and 4): the payments and
 * deadlines of the next 30 days with the late ones of the last 30, the incomes
 * on their way, the files waiting, the mail that arrived and the steps of *To begin*.
 */
final readonly class HomeSummary
{
    /** How many days ahead the Home looks, and how many back for the late payments. */
    private const int DAYS = 30;

    /** How many days back the deadlines of cards and documents go. */
    private const int DEADLINES_BACK = 90;

    /** How many entries *The deadlines* shows. */
    private const int ENTRIES = 10;

    /** How many mails *Arrived by mail* shows. */
    private const int MAILS = 3;

    /** How many files *Waiting* shows. */
    private const int PENDING = 5;

    public function __construct(private Agenda $agenda, private ExpectedDocuments $expected, private ReceivedMails $mails) {}

    /**
     * The data of the Home of an account.
     *
     * @param  User  $user  whoever signed in
     * @param  CarbonImmutable  $today  today in Rome
     */
    public function for(User $user, CarbonImmutable $today): HomeData
    {
        $today = $today->startOfDay();
        $entries = $this->agenda->entries($today, $today->addDays(self::DAYS), self::DEADLINES_BACK, self::DAYS);
        $todayDate = $today->toDateString();

        $payments = array_values(array_filter($entries, static fn (AgendaEntryData $entry): bool => $entry->kind === 'payment'));
        $listed = array_values(array_filter($entries, static fn (AgendaEntryData $entry): bool => $entry->kind !== 'income'));
        $incoming = array_values(array_filter($entries, static fn (AgendaEntryData $entry): bool => $entry->kind === 'income' && $entry->date >= $todayDate));

        // The entries come by date, so a stable partition puts the late ones first without reordering the rest
        $late = array_values(array_filter($listed, static fn (AgendaEntryData $entry): bool => $entry->date < $todayDate));
        $next = array_values(array_filter($listed, static fn (AgendaEntryData $entry): bool => $entry->date >= $todayDate));
        $ordered = [...$late, ...$next];

        return new HomeData(
            first_name: $user->person->first_name ?? $user->name,
            today: $todayDate,
            total: round(array_sum(array_map(static fn (AgendaEntryData $entry): float => $entry->amount ?? 0.0, $payments)), 2),
            payments: count($payments),
            late: count(array_filter($payments, static fn (AgendaEntryData $entry): bool => $entry->date < $todayDate)),
            entries: array_slice($ordered, 0, self::ENTRIES),
            more: count($ordered) > self::ENTRIES,
            incoming: $incoming,
            pending: array_values(PendingFileData::waiting(self::PENDING)),
            pending_count: File::query()->toSort()->count(),
            checklist: $this->checklist($user),
            missing: $this->missing($today),
            mails: array_map(
                static fn (array $row): HomeMailData => HomeMailData::fromMail($row['mail'], $row['documents']),
                $this->mails->recent(self::MAILS),
            ),
        );
    }

    /**
     * What the cards miss, each with a key made of the page that uploads it and its place among those of the same card.
     *
     * @param  CarbonImmutable  $today  today in Rome
     * @return list<MissingDocumentData>
     */
    private function missing(CarbonImmutable $today): array
    {
        $places = [];
        $rows = [];

        foreach ($this->expected->missingFor($today) as $row) {
            $places[$row['href']] = ($places[$row['href']] ?? 0) + 1;
            $rows[] = new MissingDocumentData($row['href'].'#'.$places[$row['href']], $row['card'], $row['href'], $row['document']);
        }

        return $rows;
    }

    /**
     * The steps of *To begin*, or none when every one is done. Whoever cannot create a card has no `card` step, and whoever cannot invite has no `invite` step.
     *
     * @param  User  $user  whoever signed in
     * @return list<ChecklistStepData>
     */
    private function checklist(User $user): array
    {
        $steps = [
            new ChecklistStepData('utilities', Utility::query()->exists(), null),
            new ChecklistStepData('first_bill', Document::query()->exists(), null),
        ];

        // Whoever cannot create a card would be sent to a page the policy forbids
        if (Gate::forUser($user)->allows('create', Vehicle::class)) {
            $steps[] = new ChecklistStepData('card', Vehicle::query()->exists() || InsurancePolicy::query()->exists() || Subscription::query()->exists(), null);
        }

        if (Gate::forUser($user)->allows('create', Invitation::class)) {
            $steps[] = $this->inviteStep();
        }

        return array_all($steps, static fn (ChecklistStepData $step): bool => $step->done) ? [] : $steps;
    }

    /**
     * The `invite` step: done when every parent has an account or an invitation waiting, else it names the first who has neither.
     */
    private function inviteStep(): ChecklistStepData
    {
        $waiting = Invitation::query()->whereNull('accepted_at')->where('expires_at', '>', now())->whereNotNull('person_id')->pluck('person_id');

        $parent = Person::query()
            ->where('role', PersonRole::Parent)
            ->whereNull('user_id')
            ->whereNotIn('id', $waiting)
            ->orderBy('id')
            ->first();

        return new ChecklistStepData('invite', ! $parent instanceof Person, $parent?->first_name);
    }
}

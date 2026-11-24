<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\ChecklistStepData;
use App\Data\HomeData;
use App\Data\HomeMailData;
use App\Data\MissingDocumentData;
use App\Enums\Feature;
use App\Enums\PersonRole;
use App\Models\Document;
use App\Models\FamilySetting;
use App\Models\File;
use App\Models\InsurancePolicy;
use App\Models\Invitation;
use App\Models\Person;
use App\Models\ReceivedMail;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Utility;
use App\Models\Vehicle;
use App\Services\Mail\ReceivedMails;
use App\Services\Reminders\ExpectedDocuments;
use App\Support\AgendaEntries;
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

    public function __construct(private Agenda $agenda, private ExpectedDocuments $expected, private ReceivedMails $mails, private FamilyFeatures $features) {}

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

        $payments = AgendaEntries::payments($entries);
        $listed = AgendaEntries::withoutIncomes($entries);

        // The entries come by date, so a stable partition puts the late ones first without reordering the rest
        $ordered = [...AgendaEntries::before($listed, $todayDate), ...AgendaEntries::from($listed, $todayDate)];

        return new HomeData(
            first_name: $user->person->first_name ?? $user->name,
            today: $todayDate,
            total: $this->features->on(Feature::Budget) ? AgendaEntries::total($payments) : null,
            payments: count($payments),
            late: count(AgendaEntries::before($payments, $todayDate)),
            entries: array_slice($ordered, 0, self::ENTRIES),
            more: count($ordered) > self::ENTRIES,
            incoming: AgendaEntries::from(AgendaEntries::incomes($entries), $todayDate),
            pending_count: File::query()->toSort()->notCopies()->count(),
            checklist: $this->checklist($user),
            missing: $this->missing($today),
            mails: $this->features->on(Feature::Mail) ? array_map(
                static fn (array $row): HomeMailData => HomeMailData::fromMail($row['mail'], $row['documents']),
                $this->mails->recent(self::MAILS),
            ) : [],
            can_change_features: Gate::forUser($user)->allows('manageFeatures', $user->family),
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
     * The steps of *To begin*, in the order of the board, or none when every one is done or the account hid the list.
     * Each step is only for whoever can do it: `people` for whoever can create people, `utilities` for whoever can
     * create a utility, `card` for whoever can create a card, `invite` for whoever can invite; `mail` only with the
     * mail for the bills on. The steps the family took away (*Not needed*) are not built and do not count. A step
     * found done is remembered for the family and stays ticked, even when the data change later.
     *
     * @param  User  $user  whoever signed in
     * @return list<ChecklistStepData>
     */
    private function checklist(User $user): array
    {
        if ($user->checklist_hidden_at !== null) {
            return [];
        }

        $settings = FamilySetting::current();
        $skipped = $settings->skippedChecklistSteps();
        $remembered = $settings->doneChecklistSteps();
        $gate = Gate::forUser($user);

        $offered = [
            'people' => $gate->allows('create', Person::class),
            'utilities' => $gate->allows('create', Utility::class),
            'first_bill' => true,
            // Whoever cannot create a card would be sent to a page the policy forbids
            'card' => $gate->allows('create', Vehicle::class),
            'mail' => $this->features->on(Feature::Mail),
            'invite' => $gate->allows('create', Invitation::class),
        ];

        $steps = [];

        foreach ($offered as $key => $on) {
            if ($on && ! in_array($key, $skipped, true)) {
                $steps[] = $this->step($key, in_array($key, $remembered, true));
            }
        }

        $settings->rememberDoneChecklistSteps(array_values(array_map(
            static fn (ChecklistStepData $step): string => $step->key,
            array_filter($steps, static fn (ChecklistStepData $step): bool => $step->done),
        )));

        return array_all($steps, static fn (ChecklistStepData $step): bool => $step->done) ? [] : $steps;
    }

    /**
     * One step of *To begin*. A step remembered as done skips the queries that would tell it.
     *
     * @param  string  $key  the key of the step
     * @param  bool  $remembered  whether the family already did it once
     */
    private function step(string $key, bool $remembered): ChecklistStepData
    {
        return match ($key) {
            'people' => $this->peopleStep($remembered),
            'utilities' => new ChecklistStepData($key, $remembered || Utility::query()->exists()),
            'first_bill' => new ChecklistStepData($key, $remembered || Document::query()->exists()),
            'card' => new ChecklistStepData($key, $remembered || Vehicle::query()->exists() || InsurancePolicy::query()->exists() || Subscription::query()->exists()),
            'mail' => new ChecklistStepData($key, $remembered || ReceivedMail::query()->exists()),
            default => $remembered ? new ChecklistStepData($key, true) : $this->inviteStep(),
        };
    }

    /**
     * The `people` step: done when the family has someone besides whoever made it (or did once), with the first names of all.
     *
     * @param  bool  $remembered  whether the family already did it once
     */
    private function peopleStep(bool $remembered): ChecklistStepData
    {
        if (! $remembered && Person::query()->count() < 2) {
            return new ChecklistStepData('people', false);
        }

        /** @var list<string> $names */
        $names = Person::query()->orderBy('id')->pluck('first_name')->all();

        return new ChecklistStepData('people', true, names: $names);
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

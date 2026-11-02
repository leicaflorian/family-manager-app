<?php

declare(strict_types=1);

namespace App\Services\Reminders;

use App\Cards\CardTypes;
use App\Cards\DeadlineSource;
use App\Cards\DeadlineSources;
use App\Data\AgendaData;
use App\Data\ReminderData;
use App\Data\ReminderPersonData;
use App\Data\ReminderSubjectData;
use App\Enums\ReminderStatus;
use App\Models\Person;
use App\Models\Remindable;
use App\Models\Reminder;
use App\Models\ReminderGroup;
use App\Support\Linkables;
use App\Support\ModelId;
use App\Support\Reminders\Notices;
use App\Support\Reminders\SubjectLink;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;

/**
 * The reminders of the family as the page shows them, with the data of every row read with a fixed number of queries.
 */
final readonly class ReminderList
{
    public function __construct(private MissingExpenses $missing) {}

    /**
     * The open reminders (overdue and coming, by date) and the done ones (the most recent first).
     *
     * @param  CarbonImmutable  $today  today in Rome
     * @param  int|null  $personId  only the reminders of this person; null for everybody
     * @return array{open: list<ReminderData>, done: list<ReminderData>}
     */
    public function for(CarbonImmutable $today, ?int $personId): array
    {
        $reminders = Reminder::query()
            ->when($personId !== null, static fn ($query) => $query->where('person_id', $personId))
            ->with(['link', 'person.photo'])
            ->oldest('due_date')
            ->orderBy('id')
            ->get();

        $this->loadSubjects($reminders);
        $groups = ReminderGroup::query()->get();
        $missing = $this->missing->of($reminders->where('status', ReminderStatus::Open), $today);

        $rows = $reminders->map(fn (Reminder $reminder): ReminderData => $this->row($reminder, $groups, $missing, $today));

        return [
            'open' => array_values($rows->filter(static fn (ReminderData $row): bool => $row->status === ReminderStatus::Open)->all()),
            'done' => array_values($rows->filter(static fn (ReminderData $row): bool => $row->status === ReminderStatus::Done)->reverse()->all()),
        ];
    }

    /**
     * One reminder as its row, for the list and for the form.
     *
     * @param  Reminder  $reminder  the reminder, with `link.subject` and `person` loaded
     * @param  EloquentCollection<int, ReminderGroup>  $groups  the groups of the family
     * @param  array<int, true>  $missing  the ids of the reminders that miss their planned expense
     * @param  CarbonImmutable  $today  today in Rome
     */
    public function row(Reminder $reminder, EloquentCollection $groups, array $missing, CarbonImmutable $today): ReminderData
    {
        $subject = $this->subject($reminder->link);
        $open = $reminder->status === ReminderStatus::Open;
        $next = $open ? Notices::next($reminder, Notices::rulesFor($reminder, $groups), $today) : null;
        $prefix = $reminder->due_date->toDateString().':';

        return new ReminderData(
            id: $reminder->id,
            title: $this->titleOf($reminder),
            due_date: $reminder->due_date->toDateString(),
            status: $reminder->status,
            is_automatic: $reminder->is_automatic,
            subject: $subject,
            person: $reminder->person instanceof Person ? new ReminderPersonData($reminder->person->id, $reminder->person->first_name, $reminder->person->initials, $reminder->person->photoUrl()) : null,
            group_id: $reminder->reminder_group_id,
            next_notice: $next?->toDateString(),
            notice_sent: array_any($reminder->notifications_sent ?? [], static fn (mixed $key): bool => is_string($key) && str_starts_with($key, $prefix)),
            missing_expense: isset($missing[$reminder->id]),
            href: $this->hrefOf($reminder, $subject),
        );
    }

    /**
     * What to do, in the language of whoever looks: the title of the kind of deadline for an automatic reminder, the
     * written title otherwise.
     *
     * @param  Reminder  $reminder  the reminder
     */
    public function titleOf(Reminder $reminder): string
    {
        $source = $reminder->is_automatic && $reminder->source_field !== null ? DeadlineSources::find($reminder->source_field) : null;

        return $source instanceof DeadlineSource ? __($source->title) : $reminder->title;
    }

    /**
     * What to do with the thing it is about: «Renew the policy · RC auto Panda». The one place that joins them, for
     * the pages, the bell, the calendar and the mail.
     *
     * @param  string  $title  what to do
     * @param  string|null  $subject  the name of the thing, null when there is none
     */
    public static function joinTitle(string $title, ?string $subject): string
    {
        return $subject === null ? $title : "{$title} · {$subject}";
    }

    /**
     * Where a reminder leads: the page of its thing for an automatic one, the edit page for one written by hand. An
     * automatic reminder whose thing is gone has no edit page (the policy refuses it), so it falls back to the list.
     * The one place for the pages and the bell.
     *
     * @param  Reminder  $reminder  the reminder
     * @param  ReminderSubjectData|null  $subject  its thing, null when it has none
     */
    public function hrefOf(Reminder $reminder, ?ReminderSubjectData $subject): string
    {
        return match (true) {
            ! $reminder->is_automatic => route('reminders.edit', $reminder, absolute: false),
            $subject?->href !== null => $subject->href,
            default => route('reminders.index', absolute: false),
        };
    }

    /**
     * Loads the thing of every link with the relations its name reads, one query for each type.
     *
     * @param  EloquentCollection<int, Reminder>  $reminders  the reminders, with `link` loaded
     */
    public function loadSubjects(EloquentCollection $reminders): void
    {
        /** @var EloquentCollection<int, Remindable> $links */
        $links = new EloquentCollection;

        foreach ($reminders as $reminder) {
            if ($reminder->link instanceof Remindable) {
                $links->push($reminder->link);
            }
        }

        $relations = [];

        foreach (Linkables::TYPES as $class) {
            if (method_exists($class, 'labelRelations')) {
                $relations[$class] = $class::labelRelations();
            }
        }

        $links->loadMorph('subject', $relations);
    }

    /**
     * The thing a reminder refers to, null when it has none or it no longer exists.
     *
     * @param  Remindable|null  $link  the link, with `subject` loaded
     */
    public function subject(?Remindable $link): ?ReminderSubjectData
    {
        $subject = $link?->subject;

        if (! $link instanceof Remindable || ! $subject instanceof Model) {
            return null;
        }

        return new ReminderSubjectData(
            $link->remindable_type,
            ModelId::of($subject),
            SubjectLink::name($subject),
            SubjectLink::href($link->remindable_type, $subject),
            CardTypes::find($link->remindable_type)?->area()->value ?? AgendaData::GENERAL_AREA,
        );
    }
}

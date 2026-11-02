<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Reminders\SaveReminder;
use App\Data\LinkGroupData;
use App\Data\OptionData;
use App\Data\ReminderGroupChoiceData;
use App\Data\RemindersPageData;
use App\Http\Requests\Reminders\SaveReminderRequest;
use App\Models\Document;
use App\Models\Person;
use App\Models\Reminder;
use App\Models\ReminderGroup;
use App\Services\BudgetFormOptions;
use App\Services\DocumentLinks;
use App\Services\Reminders\GroupRecipients;
use App\Services\Reminders\ReminderList;
use App\Support\ModelId;
use App\Support\Reminders\SubjectLink;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * *Reminders* and the form of a reminder written by hand: everyone sees them and a `member` writes them too, only an
 * `admin` deletes. A reminder made from a date of a card or a document is not edited or deleted.
 */
final class ReminderController extends Controller
{
    public function __construct(private readonly ReminderList $list) {}

    /**
     * *Reminders*: the open ones and the done ones, of everybody or of the person chosen in *Who*.
     */
    public function index(Request $request): Response
    {
        $asked = $request->query('person');
        $personId = is_string($asked) && ctype_digit($asked) && Person::query()->whereKey((int) $asked)->exists() ? (int) $asked : null;
        $lists = $this->list->for(CarbonImmutable::today('Europe/Rome'), $personId);

        return Inertia::render('reminders/Reminders', [
            'reminders' => new RemindersPageData($lists['open'], $lists['done'], BudgetFormOptions::people(), $personId),
        ]);
    }

    /**
     * *New reminder*: the empty form; `?remindable=vehicle:1` fills *Refers to* when it is a record of the family.
     */
    public function create(Request $request): Response
    {
        Gate::authorize('create', Reminder::class);

        $subject = SubjectLink::find($request->query('remindable'));

        return $this->form(null, $subject === null ? null : $subject['type'].':'.$subject['id']);
    }

    /**
     * Adds the reminder.
     */
    public function store(SaveReminderRequest $request, SaveReminder $save): RedirectResponse
    {
        $save->handle(null, $request->fields(), $request->subject());

        return to_route('reminders.index');
    }

    /**
     * *Edit a reminder*: a written one only.
     */
    public function edit(Reminder $reminder): Response
    {
        Gate::authorize('update', $reminder);

        $reminder->load('link');

        return $this->form($reminder, $reminder->link === null ? null : $reminder->link->remindable_type.':'.$reminder->link->remindable_id);
    }

    /**
     * Saves the change.
     */
    public function update(SaveReminderRequest $request, Reminder $reminder, SaveReminder $save): RedirectResponse
    {
        $save->handle($reminder, $request->fields(), $request->subject());

        return to_route('reminders.index');
    }

    /**
     * Deletes the reminder (only an `admin`, and not an automatic one).
     */
    public function destroy(Reminder $reminder): RedirectResponse
    {
        Gate::authorize('delete', $reminder);

        $reminder->delete();

        return to_route('reminders.index');
    }

    /**
     * The form, empty or with a reminder.
     *
     * @param  Reminder|null  $reminder  the reminder to change, null for a new one
     * @param  string|null  $remindable  the chosen thing as `type:id`
     */
    private function form(?Reminder $reminder, ?string $remindable): Response
    {
        $today = CarbonImmutable::today('Europe/Rome');
        $row = null;

        if ($reminder instanceof Reminder) {
            $reminder->load('person.photo');
            $this->list->loadSubjects(new Collection([$reminder]));
            $row = $this->list->row($reminder, ReminderGroup::query()->get(), [], $today);
        }

        $groups = ReminderGroup::query()->orderBy('name')->get();
        $recipients = new GroupRecipients;

        return Inertia::render('reminders/EditReminder', [
            'reminder' => $row,
            'remindable' => $remindable,
            'people' => BudgetFormOptions::people(),
            'groups' => $groups->map(static fn (ReminderGroup $group): ReminderGroupChoiceData => new ReminderGroupChoiceData((string) ModelId::of($group), $group->name, $recipients->ofGroup($group)))->values()->all(),
            'default_recipients' => $recipients->ofDefault($groups),
            'links' => $this->links(),
        ]);
    }

    /**
     * The things a reminder can refer to, grouped by type: the cards, then the documents and the people.
     *
     * @return list<LinkGroupData>
     */
    private function links(): array
    {
        $cards = array_values(array_filter(
            resolve(DocumentLinks::class)->groups(),
            static fn (LinkGroupData $group): bool => in_array($group->type, SubjectLink::cardTypes(), true),
        ));
        $documents = [];

        foreach (Document::query()->orderBy('title')->get() as $document) {
            $documents[] = new OptionData((string) ModelId::of($document), $document->title);
        }

        return [
            ...$cards,
            ...($documents === [] ? [] : [new LinkGroupData('document', __('Documents'), $documents)]),
            new LinkGroupData('person', __('People'), BudgetFormOptions::people()),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Files\ReplaceImage;
use App\Actions\People\DeletePerson;
use App\Data\FamilySummaryData;
use App\Data\OptionData;
use App\Data\PersonData;
use App\Data\PersonDetailData;
use App\Enums\PersonRole;
use App\Http\Controllers\Concerns\PagesDocuments;
use App\Http\Requests\Family\SavePersonRequest;
use App\Models\Document;
use App\Models\Person;
use App\Models\User;
use App\Queries\DocumentRows;
use App\Queries\GalleryTeaser;
use App\Services\LinkedRecords;
use App\Services\Reminders\ReminderList;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * *People*, *A person* and their form: everyone sees them, only an `admin`
 * adds, changes and deletes them.
 */
final class PersonController extends Controller
{
    use PagesDocuments;

    /** How many personal documents a page of *A person* holds. */
    public const int PERSONAL_PER_PAGE = 5;

    /** How many other documents a page of *A person* holds. */
    public const int DOCUMENTS_PER_PAGE = 10;

    public function __construct(private readonly LinkedRecords $records, private readonly ReminderList $reminders, private readonly GalleryTeaser $gallery) {}

    /**
     * *People*: the people of the family, in the order they were added.
     */
    public function index(#[CurrentUser] User $user): Response
    {
        return Inertia::render('family/People', [
            'people' => PersonData::ofFamily($user),
            'family' => FamilySummaryData::of($user),
            'canEdit' => $user->can('create', Person::class),
        ]);
    }

    /**
     * *New person*: the empty form.
     */
    public function create(): Response
    {
        Gate::authorize('create', Person::class);

        return $this->form(null);
    }

    /**
     * Adds the person, with their photo, without an account: that comes with an invitation.
     */
    public function store(SavePersonRequest $request, ReplaceImage $replace): RedirectResponse
    {
        $person = Person::query()->create($request->fields());
        $replace->handle($person, 'photo_id', $request->uploadedImage('photo'));

        return to_route('people.show', $person);
    }

    /**
     * *A person*: their open reminders, their personal documents (linked to no
     * record, the pinned ones apart) and the records that name them; on the side the other
     * documents. The two lists of documents come a page at a time: a partial
     * reload with `personal_page` or `documents_page` adds the next page below.
     */
    public function show(Request $request, Person $person, DocumentRows $rows, #[CurrentUser] User $user): Response
    {
        $theirs = ['person' => $person->id];
        // Each list runs its queries the first time one of its props is asked, and never on a reload that does not ask it
        $reminderList = null;
        $reminders = function () use (&$reminderList, $person): array {
            return $reminderList ??= $this->reminders->for(CarbonImmutable::today('Europe/Rome'), $person->id)['open'];
        };

        return Inertia::render('family/Person', [
            'person' => fn (): PersonDetailData => PersonDetailData::fromPerson($person, $user, $this->gallery->personHasDrawings($person->id)),
            'pinned' => fn (): array => $rows->pinned($person->id),
            'canPin' => $user->can('create', Document::class),
            ...$this->documentPages($request, $rows, 'personal', [...$theirs, 'linked' => false, 'pinned' => false, 'gallery' => false], self::PERSONAL_PER_PAGE),
            ...$this->documentPages($request, $rows, 'documents', [...$theirs, 'linked' => true], self::DOCUMENTS_PER_PAGE),
            'pageSizes' => ['personal' => self::PERSONAL_PER_PAGE, 'documents' => self::DOCUMENTS_PER_PAGE],
            'links' => fn (): array => $this->records->ofPerson($person),
            'reminders' => $reminders,
            'remindersCount' => fn (): int => count($reminders()),
        ]);
    }

    /**
     * *Edit a person*.
     */
    public function edit(Person $person, #[CurrentUser] User $user): Response
    {
        Gate::authorize('update', $person);

        return $this->form(PersonDetailData::fromPerson($person, $user));
    }

    /**
     * Saves the change, with the new photo or without the old one.
     */
    public function update(SavePersonRequest $request, Person $person, ReplaceImage $replace): RedirectResponse
    {
        $person->update($request->fields());
        $replace->handle($person, 'photo_id', $request->uploadedImage('photo'), $request->boolean('remove_photo'));

        return to_route('people.show', $person);
    }

    /**
     * Deletes the person (only an `admin`, and only without an account).
     */
    public function destroy(Person $person, DeletePerson $delete): RedirectResponse
    {
        Gate::authorize('delete', $person);

        $delete->handle($person);

        return to_route('people.index');
    }

    /**
     * The form of a person, empty or with their data.
     *
     * @param  PersonDetailData|null  $person  the person to change, or null for a new one
     */
    private function form(?PersonDetailData $person): Response
    {
        return Inertia::render('family/EditPerson', [
            'person' => $person,
            'roles' => OptionData::list(PersonRole::options()),
        ]);
    }
}

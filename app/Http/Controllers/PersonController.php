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
use App\Http\Requests\Family\SavePersonRequest;
use App\Models\Person;
use App\Models\User;
use App\Queries\DocumentRows;
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
    /** How many personal documents a page of *A person* holds. */
    public const int PERSONAL_PER_PAGE = 5;

    /** How many other documents a page of *A person* holds. */
    public const int DOCUMENTS_PER_PAGE = 10;

    public function __construct(private readonly LinkedRecords $records, private readonly ReminderList $reminders) {}

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
     * record) and the records that name them; on the side the other
     * documents. The two lists of documents come a page at a time: a partial
     * reload with `personal_page` or `documents_page` adds the next page below.
     */
    public function show(Request $request, Person $person, DocumentRows $rows, #[CurrentUser] User $user): Response
    {
        $theirs = ['person' => $person->id];
        $reminders = $this->reminders->for(CarbonImmutable::today('Europe/Rome'), $person->id)['open'];
        // One query per list for the documents, their next page and their total, run the first time one is asked
        $personalPage = null;
        $personal = function () use (&$personalPage, $rows, $theirs, $request): array {
            return $personalPage ??= $rows->page([...$theirs, 'linked' => false], max(1, $request->integer('personal_page')), self::PERSONAL_PER_PAGE);
        };
        $documentsPage = null;
        $documents = function () use (&$documentsPage, $rows, $theirs, $request): array {
            return $documentsPage ??= $rows->page([...$theirs, 'linked' => true], max(1, $request->integer('documents_page')), self::DOCUMENTS_PER_PAGE);
        };

        return Inertia::render('family/Person', [
            'person' => PersonDetailData::fromPerson($person, $user),
            'personal' => Inertia::merge(fn (): array => $personal()['documents'])->matchOn('id'),
            'personalNext' => fn (): ?int => $personal()['next'],
            'personalTotal' => fn (): int => $personal()['total'],
            'documents' => Inertia::merge(fn (): array => $documents()['documents'])->matchOn('id'),
            'documentsNext' => fn (): ?int => $documents()['next'],
            'documentsTotal' => fn (): int => $documents()['total'],
            'links' => $this->records->ofPerson($person),
            'reminders' => $reminders,
            'remindersCount' => count($reminders),
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

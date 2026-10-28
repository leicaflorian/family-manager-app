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
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * *People*, *A person* and their form: everyone sees them, only an `admin`
 * adds, changes and deletes them.
 */
final class PersonController extends Controller
{
    public function __construct(private readonly LinkedRecords $records) {}

    /**
     * *People*: the people of the family, in the order they were added.
     */
    public function index(#[CurrentUser] User $user): Response
    {
        $counts = $this->records->countByPerson();

        return Inertia::render('family/People', [
            'people' => Person::query()->with(['user', 'photo'])->withCount('documents')->orderBy('id')->get()
                ->map(static fn (Person $person): PersonData => PersonData::fromPerson($person, $user, $counts[$person->id] ?? 0))
                ->values()->all(),
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
     * *A person*: at the top the documents expiring, then the personal
     * documents (linked to no record), the records that name them and the
     * other documents.
     */
    public function show(Person $person, DocumentRows $rows, #[CurrentUser] User $user): Response
    {
        $theirs = ['person' => $person->id];

        return Inertia::render('family/Person', [
            'person' => PersonDetailData::fromPerson($person, $user),
            'expiring' => $rows->of([...$theirs, 'expiring' => true], byExpiry: true),
            'personal' => $rows->of([...$theirs, 'linked' => false]),
            'documents' => $rows->of([...$theirs, 'linked' => true]),
            'links' => $this->records->ofPerson($person),
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

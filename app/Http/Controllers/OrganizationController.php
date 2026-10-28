<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Files\ReplaceImage;
use App\Data\FamilySummaryData;
use App\Data\OptionData;
use App\Data\OrganizationData;
use App\Data\OrganizationDetailData;
use App\Enums\OrganizationType;
use App\Http\Requests\Family\SaveOrganizationRequest;
use App\Models\Organization;
use App\Models\User;
use App\Queries\DocumentRows;
use App\Services\LinkedRecords;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * *Organizations*, *An organization* and their form: everyone sees them, only
 * an `admin` adds, changes and deletes them.
 */
final class OrganizationController extends Controller
{
    public function __construct(private readonly LinkedRecords $records) {}

    /**
     * *Organizations*: all of them, by name, with the types for the filter.
     */
    public function index(#[CurrentUser] User $user): Response
    {
        $labels = $this->records->labelsByOrganization();

        return Inertia::render('family/Organizations', [
            'organizations' => Organization::query()->with('logo')->orderBy('name')->get()
                ->map(static fn (Organization $organization): OrganizationData => OrganizationData::fromOrganization($organization, $labels[$organization->id] ?? []))
                ->values()->all(),
            'types' => OptionData::list(OrganizationType::options()),
            'family' => FamilySummaryData::of($user),
            'canEdit' => $user->can('create', Organization::class),
        ]);
    }

    /**
     * *New organization*: the empty form.
     */
    public function create(): Response
    {
        Gate::authorize('create', Organization::class);

        return $this->form(null);
    }

    /**
     * Adds the organization, with its logo.
     */
    public function store(SaveOrganizationRequest $request, ReplaceImage $replace): RedirectResponse
    {
        $organization = Organization::query()->create($request->fields());
        $replace->handle($organization, 'logo_id', $request->uploadedImage('logo'));

        return to_route('organizations.show', $organization);
    }

    /**
     * *An organization*: the records that name it, its documents and its contacts.
     */
    public function show(Organization $organization, DocumentRows $rows, #[CurrentUser] User $user): Response
    {
        return Inertia::render('family/Organization', [
            'organization' => OrganizationDetailData::fromOrganization($organization, $user),
            'links' => $this->records->ofOrganization($organization),
            'documents' => $rows->of(['organization' => $organization->id]),
        ]);
    }

    /**
     * *Edit an organization*.
     */
    public function edit(Organization $organization, #[CurrentUser] User $user): Response
    {
        Gate::authorize('update', $organization);

        return $this->form(OrganizationDetailData::fromOrganization($organization, $user));
    }

    /**
     * Saves the change, with the new logo or without the old one.
     */
    public function update(SaveOrganizationRequest $request, Organization $organization, ReplaceImage $replace): RedirectResponse
    {
        $organization->update($request->fields());
        $replace->handle($organization, 'logo_id', $request->uploadedImage('logo'), $request->boolean('remove_logo'));

        return to_route('organizations.show', $organization);
    }

    /**
     * Deletes the organization (only an `admin`): its documents and records
     * stay, without the organization.
     */
    public function destroy(Organization $organization): RedirectResponse
    {
        Gate::authorize('delete', $organization);

        $organization->delete();

        return to_route('organizations.index');
    }

    /**
     * The form of an organization, empty or with its data.
     *
     * @param  OrganizationDetailData|null  $organization  the organization to change, or null for a new one
     */
    private function form(?OrganizationDetailData $organization): Response
    {
        return Inertia::render('family/EditOrganization', [
            'organization' => $organization,
            'types' => OptionData::list(OrganizationType::options()),
        ]);
    }
}

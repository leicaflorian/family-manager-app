<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Files\ReplaceImage;
use App\Actions\Reminders\ForgetRemindable;
use App\Data\FamilySummaryData;
use App\Data\OptionData;
use App\Data\OrganizationData;
use App\Data\OrganizationDetailData;
use App\Enums\OrganizationType;
use App\Http\Controllers\Concerns\PagesDocuments;
use App\Http\Requests\Family\SaveOrganizationRequest;
use App\Models\Organization;
use App\Models\User;
use App\Queries\DocumentRows;
use App\Services\LinkedRecords;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * *Organizations*, *An organization* and their form: everyone sees them, only
 * an `admin` adds, changes and deletes them.
 */
final class OrganizationController extends Controller
{
    use PagesDocuments;

    /** How many documents a page of *An organization* holds. */
    public const int DOCUMENTS_PER_PAGE = 10;

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
     * The documents come a page at a time: a partial reload with `documents_page`
     * adds the next page below.
     */
    public function show(Request $request, Organization $organization, DocumentRows $rows, #[CurrentUser] User $user): Response
    {
        $documentsPage = null;
        $documents = function () use (&$documentsPage, $rows, $organization, $request): array {
            return $documentsPage ??= $rows->page(['organization' => $organization->id], $this->pageAsked($request, 'documents'), self::DOCUMENTS_PER_PAGE);
        };

        return Inertia::render('family/Organization', [
            'organization' => fn (): OrganizationDetailData => OrganizationDetailData::fromOrganization($organization, $user),
            'links' => fn (): array => $this->records->ofOrganization($organization),
            'documents' => Inertia::merge(fn (): array => $documents()['documents'])->matchOn('id'),
            'documentsNext' => fn (): ?int => $documents()['next'],
            'documentsTotal' => fn (): int => $documents()['total'],
            'pageSizes' => ['documents' => self::DOCUMENTS_PER_PAGE],
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
    public function destroy(Organization $organization, ForgetRemindable $forget): RedirectResponse
    {
        Gate::authorize('delete', $organization);

        DB::transaction(function () use ($organization, $forget): void {
            $organization->delete();
            $forget->handle($organization);
        });

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

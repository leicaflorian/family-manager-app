<?php

declare(strict_types=1);

namespace App\Services\Reminders;

use App\Enums\DocumentCategoryRole;
use App\Enums\DocumentStatus;
use App\Enums\EmploymentStatus;
use App\Enums\FilingStatus;
use App\Enums\FilingType;
use App\Enums\FinancialAccountStatus;
use App\Enums\FinancialAccountType;
use App\Enums\VehicleStatus;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\Employment;
use App\Models\Filing;
use App\Models\FinancialAccount;
use App\Models\Vehicle;
use App\Services\CategoryTree;
use App\Support\ModelId;
use App\Support\Translated;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * The documents a card is expected to have, by rules in the code (no table): the registration document of an active
 * vehicle and the file of an ISEE in draft. A document counts when it is linked to the card and recognised by its
 * category (the registration document of a vehicle: the category with the role, or one under it), title, person and date, as the board *Pratica* shows ("Documents 3 of 4 collected").
 */
final class ExpectedDocuments
{
    /**
     * What a card expects and what of it is still missing.
     *
     * @param  Model  $card  a card of any type; only vehicles and filings expect documents
     * @param  CarbonImmutable|null  $today  today in Rome, the clock's if missing
     * @return array{expected: int, missing: list<string>} how many documents, and the names (in the language of the app) of those not linked yet
     */
    public function for(Model $card, ?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today('Europe/Rome');

        if (! $card instanceof Vehicle && ! $card instanceof Filing) {
            return ['expected' => 0, 'missing' => []];
        }

        $items = $card instanceof Vehicle ? $this->ofVehicle($card) : $this->ofFiling($card, $today);

        if ($items === []) {
            return ['expected' => 0, 'missing' => []];
        }

        $linked = $card->documents()->with('category')->get()->all();
        $missing = [];

        foreach ($items as $item) {
            if (! $item['met']($linked)) {
                $missing[] = $item['name'];
            }
        }

        return ['expected' => count($items), 'missing' => $missing];
    }

    /**
     * What is missing in the vehicles and the ISEE files of the family, for *Something is missing* of the Home.
     *
     * @param  CarbonImmutable  $today  today in Rome
     * @return list<array{card: string, href: string, document: string}> the card, where to upload the document and its name
     */
    public function missingFor(CarbonImmutable $today): array
    {
        $cards = [
            ...Vehicle::query()->where('status', VehicleStatus::Active)->orderBy('id')->get()->all(),
            ...Filing::query()->where('type', FilingType::Isee)->where('status', FilingStatus::Draft)->orderBy('id')->get()->all(),
        ];
        $rows = [];

        foreach ($cards as $card) {
            foreach ($this->for($card, $today)['missing'] as $document) {
                $rows[] = ['card' => $card->label(), 'href' => self::uploadHref($card, $this->categoryFor($card)), 'document' => $document];
            }
        }

        return $rows;
    }

    /**
     * The category the missing documents of a card go in: the registration document category of a vehicle.
     *
     * @param  Model  $card  a card
     * @return int|null null when the card expects no category, or the family has deleted the category of the role
     */
    public function categoryFor(Model $card): ?int
    {
        return $card instanceof Vehicle ? DocumentCategory::withRole(DocumentCategoryRole::VehicleRegistration)?->id : null;
    }

    /**
     * The page that uploads a document with the card already among its links, and the category already chosen.
     *
     * @param  Model  $card  a card
     * @param  int|null  $category  the document category the import form starts with
     */
    public static function uploadHref(Model $card, ?int $category = null): string
    {
        return route('upload', array_filter(['link' => $card->getMorphClass().':'.ModelId::of($card), 'category' => $category]), false);
    }

    /**
     * The registration document of an active vehicle: a document in the category with the role, or under it. A
     * family that deleted that category expects nothing.
     *
     * @return array<string, array{name: string, met: Closure(array<int, Document>): bool}> each expected document by a stable id (`account:12`, `job:5`), with its name and its test
     */
    private function ofVehicle(Vehicle $vehicle): array
    {
        $category = DocumentCategory::withRole(DocumentCategoryRole::VehicleRegistration);

        if ($vehicle->status !== VehicleStatus::Active || ! $category instanceof DocumentCategory) {
            return [];
        }

        $ids = (new CategoryTree)->withDescendants($category->id);

        return ['registration' => [
            'name' => Translated::text('Vehicle registration document'),
            'met' => static fn ($documents): bool => array_any($documents,
                static fn (Document $document): bool => in_array($document->category_id, $ids, true),
            ),
        ]];
    }

    /**
     * The file of an ISEE in draft with a reference year A: the average balance of A-2 for each active account but the cards (dated in A-1
     * or later), the CU of A-1 for each active job, and the identity card of the applicant.
     *
     * @return array<string, array{name: string, met: Closure(array<int, Document>): bool}> each expected document by a stable id (`account:12`, `job:5`), with its name and its test
     */
    private function ofFiling(Filing $filing, CarbonImmutable $today): array
    {
        $year = $filing->reference_year;

        if ($filing->type !== FilingType::Isee || $filing->status !== FilingStatus::Draft || $year === null) {
            return [];
        }

        $items = [];

        foreach (FinancialAccount::query()->where('status', FinancialAccountStatus::Active)->where('type', '!=', FinancialAccountType::Card)->orderBy('id')->get() as $account) {
            $items["account:{$account->id}"] = [
                'name' => Translated::text('Average balance :year of :account', ['year' => $year - 2, 'account' => $account->name]),
                'met' => static fn ($documents): bool => array_any($documents,
                    static fn (Document $document): bool => self::titled($document, ['giacenza']) && $document->date instanceof CarbonImmutable && $document->date->year >= $year - 1,
                ),
            ];
        }

        foreach (Employment::query()->with('person')->where('status', EmploymentStatus::Active)->orderBy('id')->get() as $job) {
            $items["job:{$job->id}"] = [
                'name' => Translated::text('Annual income statement :year of :person', ['year' => $year - 1, 'person' => $job->person->first_name]),
                'met' => static fn ($documents): bool => array_any($documents,
                    static fn (Document $document): bool => $document->person_id === $job->person_id
                        && $document->date?->year === $year - 1
                        && preg_match('/\bCU\b/i', $document->category->name ?? '') === 1,
                ),
            ];
        }

        if ($filing->person !== null) {
            $items["identity:{$filing->person_id}"] = [
                'name' => Translated::text('Identity card of :person', ['person' => $filing->person->first_name]),
                'met' => static fn ($documents): bool => array_any($documents,
                    static fn (Document $document): bool => $document->person_id === $filing->person_id
                        && self::titled($document, ['identità', 'identity'])
                        && $document->status !== DocumentStatus::Expired
                        && ($document->expiry_date === null || $document->expiry_date->greaterThanOrEqualTo($today)),
                ),
            ];
        }

        return $items;
    }

    /**
     * Whether the title of a document has one of the words, in any case.
     *
     * @param  Document  $document  the document
     * @param  list<string>  $words  the words to look for
     */
    private static function titled(Document $document, array $words): bool
    {
        return array_any($words, static fn (string $word): bool => mb_stripos($document->title, $word) !== false);
    }
}

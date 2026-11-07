<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Reading\ImportFiles;
use App\Cards\CardTypes;
use App\Data\OptionData;
use App\Data\PendingFileData;
use App\Data\Reading\ImportChoices;
use App\Data\Reading\ProposalData;
use App\Enums\HealthEventType;
use App\Http\Requests\Reading\ImportRequest;
use App\Models\Document;
use App\Models\File;
use App\Services\BudgetFormOptions;
use App\Services\DocumentFormOptions;
use App\Services\Reading\ImportProposals;
use App\Support\ImportPage;
use App\Support\PresetLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * *Import more files*: several files of *To sort*, read, checked one by one on one page and imported together, each
 * with what it does too (the expense, the health event, the cards).
 */
final class ImportController extends Controller
{
    /**
     * The page with the files of `?files=1,2,3`, in that order, the proposal of each, and the expense categories
     * and accounts that name the expense a file proposes, and the types of a health event. A file that is not waiting
     * in the family is a 404 before anything else; with no files the person goes back to *To sort*.
     *
     * The page asks the proposal of the file it shows again, with the choices of the person, by reloading only
     * `proposals` with `?check[file_id]=…&check[document]=…&check[also]=…`: a file that is not one of the page is a
     * 404, and the proposal that comes back is merged with the others.
     */
    public function index(Request $request, DocumentFormOptions $options, ImportProposals $proposals): Response|RedirectResponse
    {
        $ids = $this->ids($request->query('files'));

        if ($ids === []) {
            return to_route('to-sort');
        }

        $files = File::query()->toSort()->with('document')->whereKey($ids)->get();
        abort_unless($files->count() === count($ids), 404);
        Gate::authorize('create', Document::class);

        $ordered = $files->sortBy(static fn (File $file): int|false => array_search($file->id, $ids, true))->values();
        $failures = $request->session()->get('import_failures', []);
        $notices = $request->session()->get('import_notices', []);
        $check = $this->check($request->query('check'), $ordered);
        $locale = App::getLocale();
        $offersCard = $this->offersCard($request);
        $cards = $request->session()->get('import_cards');

        return Inertia::render('documents/Import', [
            'files' => PendingFileData::fromFiles($ordered),
            'proposals' => Inertia::merge(fn (): array => array_map(
                static fn (ProposalData $proposal): ProposalData => $offersCard ? $proposal : $proposal->withoutCreateCard(),
                $proposals->forPage($ordered, $check, $locale),
            )),
            'failures' => is_array($failures) ? $failures : [],
            'notices' => is_array($notices) ? $notices : [],
            // The documents imported with *Create the card* while other files failed: the page offers their card
            'cardDocuments' => is_string($cards) ? $cards : null,
            'presetLinks' => array_filter([PresetLink::from($request->query('link'))]),
            // The names the boxes of *What I do too* show for the expense: its category and the account it was paid with
            'expenseCategories' => BudgetFormOptions::categories(...),
            'accounts' => BudgetFormOptions::accounts(...),
            // The types the box of a new health event offers when the reading found none
            'healthTypes' => fn (): array => OptionData::list(HealthEventType::options()),
            ...$options->all(),
        ]);
    }

    /**
     * Imports the files, each with what it does too, once each file is allowed (`FilePolicy::import`: a `member`
     * creates through the import what the policies of each model do not let it create elsewhere). What fails stays
     * on the page, each file with its reason; with nothing left to check the person goes to *New card* with the
     * documents of the files imported with *Create the card* (`?document=1,2`), otherwise to the card the files come
     * from (`?link=`), or to *Documents*. When some files fail, the documents saved for *Create the card* come back to
     * the page as `import_cards`, which offers their card. What the imported files say (a policy not linked) comes along as
     * `import_notices`.
     */
    public function store(ImportRequest $request, ImportFiles $import): RedirectResponse
    {
        $entries = $request->entries();
        foreach (File::query()->whereKey(array_column($entries, 'file_id'))->get() as $file) {
            Gate::authorize('import', $file);
        }

        ['failures' => $failures, 'notices' => $notices, 'cards' => $cards] = $import->handle($entries);
        $waiting = File::query()->toSort()->whereKey(array_keys($failures))->get()->map(fn (File $file): int => $file->id)->all();
        $card = PresetLink::from($request->query('link'));
        $cards = $this->offersCard($request) && $cards !== [] ? implode(',', $cards) : null;

        $response = match (true) {
            $waiting !== [] => ImportPage::open($waiting, $request->query('link'))->with('import_failures', $failures),
            $cards !== null => to_route('cards.create', ['document' => $cards]),
            $card !== null => to_route('cards.show', $card),
            default => to_route('documents.index'),
        };

        // Back on the page with files that failed, the documents saved for *Create the card* are not lost
        if ($waiting !== [] && $cards !== null) {
            $response->with('import_cards', $cards);
        }

        return $notices === [] ? $response : $response->with('import_notices', $notices);
    }

    /**
     * Whether the import offers *Create the card*: only to whoever may create cards (an `admin`), and not when the
     * files come from a card (`?link=`), whose documents are already linked to it.
     *
     * @param  Request  $request  the request, with the card the files come from
     */
    private function offersCard(Request $request): bool
    {
        return PresetLink::from($request->query('link')) === null && Gate::allows('create', CardTypes::all()[0]->model());
    }

    /**
     * The file the page asks the proposal of again, with the choices of the person.
     *
     * @param  mixed  $value  `?check=` as it came: `{file_id, document, also}`
     * @param  Collection<int, File>  $files  the files of the page
     * @return array{file: File, choices: ImportChoices}|null null without a check
     *
     * @throws NotFoundHttpException if the file is not one of the page
     */
    private function check(mixed $value, Collection $files): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $id = $value['file_id'] ?? null;
        $file = is_numeric($id) ? $files->first(static fn (File $file): bool => $file->id === (int) $id) : null;
        abort_unless($file instanceof File, 404);

        $also = is_array($value['also'] ?? null) ? $value['also'] : [];
        $document = is_array($value['document'] ?? null) ? $value['document'] : [];

        return ['file' => $file, 'choices' => ImportChoices::fromArray([...$also, 'document' => $document])];
    }

    /**
     * The ids of the query, without duplicates.
     *
     * @param  mixed  $value  `?files=` as it came
     * @return list<int>
     */
    private function ids(mixed $value): array
    {
        if (! is_string($value)) {
            return [];
        }

        return array_values(array_unique(array_map(intval(...), array_filter(explode(',', $value), is_numeric(...)))));
    }
}

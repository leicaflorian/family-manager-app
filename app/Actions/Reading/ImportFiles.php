<?php

declare(strict_types=1);

namespace App\Actions\Reading;

use App\Data\Reading\ImportChoices;
use App\Data\Reading\PolicyGiverData;
use App\Data\Reading\ProposalData;
use App\Models\Document;
use App\Models\File;
use App\Services\Reading\ImportProposals;
use App\Services\Reading\PolicyFromOtherFiles;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Imports many waiting files, one after the other, each with what it does too ({@see ImportFile}) and in its own
 * transaction: a file that does not save leaves nothing of itself and the others saved, and the reason is kept for
 * its row. The files that give a policy go first, so that the papers without a number find it.
 *
 * Known, small window: the proposal the request made is reused for the first file, and it was made outside the lock
 * of the file. Two imports running at the same time, of two different files of the same new issuer, can therefore
 * both create that organization.
 */
final readonly class ImportFiles
{
    /**
     * @param  ImportFile  $importFile  imports one file
     * @param  ImportProposals  $proposals  the policies the files give
     * @param  PolicyFromOtherFiles  $policies  the order of the files
     */
    public function __construct(private ImportFile $importFile, private ImportProposals $proposals, private PolicyFromOtherFiles $policies) {}

    /**
     * Imports each file.
     *
     * @param  list<array{file_id: int, choices: ImportChoices, proposal?: ?ProposalData}>  $entries  the files with what the person confirmed, and the proposal the request checked, if any
     * @return array{failures: array<int, string>, notices: array<int, list<string>>, cards: list<int>} why each file that was not imported failed, and what the imported ones say (a policy not linked), by file id, in the language of the request; and the documents of the files imported with *Create the card*, in the order of the entries
     */
    public function handle(array $entries): array
    {
        $choices = [];
        $checked = [];
        foreach ($entries as $entry) {
            $choices[$entry['file_id']] = $entry['choices'];
            $checked[$entry['file_id']] = $entry['proposal'] ?? null;
        }

        $givers = $this->givers($choices, $checked);
        $order = $this->policies->confirmOrder(array_keys($choices), static fn (int $id): bool => isset($givers[$id]));
        $failures = [];
        $notices = [];
        $documents = [];
        $saved = false;

        foreach ($order as $id) {
            $round = array_values(array_filter($givers, static fn (int $giver): bool => $giver !== $id, ARRAY_FILTER_USE_KEY));

            try {
                // The request proposed every file before anything was saved: once a file is saved, the next ones
                // propose again, so they find the organization, the cards and the expense it created
                $imported = $this->file($id, $choices[$id], $round, $saved ? null : $checked[$id]);
                if ($imported === null) {
                    $failures[$id] = __('This file was already filed.');
                } else {
                    $saved = true;
                    $documents[$id] = $imported['document']->id;
                    if ($imported['warnings'] !== []) {
                        $notices[$id] = $imported['warnings'];
                    }
                }
            } catch (ValidationException $exception) {
                $message = collect($exception->errors())->flatten()->first();
                $failures[$id] = is_string($message) ? $message : __('This file could not be saved.');
            } catch (Throwable $exception) {
                report($exception);
                $failures[$id] = __('This file could not be saved.');
            }
        }

        $cards = [];
        foreach ($choices as $id => $choice) {
            if ($choice->createCard && isset($documents[$id])) {
                $cards[] = $documents[$id];
            }
        }

        return ['failures' => $failures, 'notices' => $notices, 'cards' => $cards];
    }

    /**
     * The policy each file gives, decided before any is saved, as the person saw it.
     *
     * @param  array<int, ImportChoices>  $choices  the choices, by file
     * @param  array<int, ?ProposalData>  $checked  the proposals the request made, by file
     * @return array<int, PolicyGiverData>
     */
    private function givers(array $choices, array $checked): array
    {
        $entries = [];
        foreach (File::query()->toSort()->whereKey(array_keys($choices))->get() as $file) {
            $entries[$file->id] = ['file' => $file, 'choices' => $choices[$file->id], 'proposal' => $checked[$file->id] ?? null];
        }

        return $this->proposals->givers($entries, App::getLocale());
    }

    /**
     * Imports one file, if it still waits: the row is locked first, so two imports of the same file make one
     * document, one expense and one of each card.
     *
     * @param  int  $id  the file
     * @param  ImportChoices  $choices  what the person confirmed
     * @param  list<PolicyGiverData>  $round  the policies the other files give
     * @param  ProposalData|null  $checked  the proposal the request made
     * @return array{document: Document, warnings: list<string>}|null the document and what the file says, or null when it no longer waits
     *
     * @throws Throwable
     */
    private function file(int $id, ImportChoices $choices, array $round, ?ProposalData $checked): ?array
    {
        return DB::transaction(function () use ($id, $choices, $round, $checked): ?array {
            $file = File::query()->toSort()->lockForUpdate()->find($id);
            if (! $file instanceof File) {
                return null;
            }

            $imported = $this->importFile->handle($file, $choices, $round, $checked);

            return ['document' => $imported['document'], 'warnings' => $imported['warnings']];
        });
    }
}

<?php

declare(strict_types=1);

namespace App\Actions\Reading;

use App\Data\Reading\ImportChoices;
use App\Data\Reading\PolicyGiverData;
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
     * @param  list<array{file_id: int, choices: ImportChoices}>  $entries  the files with what the person confirmed
     * @return array{failures: array<int, string>, notices: array<int, list<string>>} why each file that was not imported failed, and what the imported ones say (a policy not linked), by file id, in the language of the request
     */
    public function handle(array $entries): array
    {
        $choices = [];
        foreach ($entries as $entry) {
            $choices[$entry['file_id']] = $entry['choices'];
        }

        $givers = $this->givers($choices);
        $order = $this->policies->confirmOrder(array_keys($choices), static fn (int $id): bool => isset($givers[$id]));
        $failures = [];
        $notices = [];

        foreach ($order as $id) {
            $round = array_values(array_filter($givers, static fn (int $giver): bool => $giver !== $id, ARRAY_FILTER_USE_KEY));

            try {
                $warnings = $this->file($id, $choices[$id], $round);
                if ($warnings === null) {
                    $failures[$id] = __('This file was already filed.');
                } elseif ($warnings !== []) {
                    $notices[$id] = $warnings;
                }
            } catch (ValidationException $exception) {
                $failures[$id] = $exception->getMessage();
            } catch (Throwable $exception) {
                report($exception);
                $failures[$id] = __('This file could not be saved.');
            }
        }

        return ['failures' => $failures, 'notices' => $notices];
    }

    /**
     * The policy each file gives, decided before any is saved, as the person saw it.
     *
     * @param  array<int, ImportChoices>  $choices  the choices, by file
     * @return array<int, PolicyGiverData>
     */
    private function givers(array $choices): array
    {
        $entries = [];
        foreach (File::query()->toSort()->whereKey(array_keys($choices))->get() as $file) {
            $entries[$file->id] = ['file' => $file, 'choices' => $choices[$file->id]];
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
     * @return list<string>|null what the file says, or null when it no longer waits
     *
     * @throws Throwable
     */
    private function file(int $id, ImportChoices $choices, array $round): ?array
    {
        return DB::transaction(function () use ($id, $choices, $round): ?array {
            $file = File::query()->toSort()->lockForUpdate()->find($id);

            return $file instanceof File ? $this->importFile->handle($file, $choices, $round)['warnings'] : null;
        });
    }
}

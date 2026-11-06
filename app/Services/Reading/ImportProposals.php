<?php

declare(strict_types=1);

namespace App\Services\Reading;

use App\Data\Reading\Extraction;
use App\Data\Reading\ImportChoices;
use App\Data\Reading\PolicyGiverData;
use App\Data\Reading\ProposalData;
use App\Data\Reading\RecordDraftData;
use App\Enums\RecordKind;
use App\Models\File;
use Illuminate\Support\Collection;

/**
 * The proposals of the files of one import, and the policies they give to each other: an insurance paper without
 * a number takes the policy of another file confirmed with it, so its proposal needs theirs.
 */
final readonly class ImportProposals
{
    /**
     * @param  Proposal  $proposals  the proposal of one file
     * @param  PolicyFromOtherFiles  $policies  the policy a file gives
     */
    public function __construct(private Proposal $proposals, private PolicyFromOtherFiles $policies) {}

    /**
     * The proposals of *Import*, by file. Every file proposes with the boxes it suggests; with `check` only the file
     * it names is proposed again, with the choices the person made (the page merges it with the others).
     *
     * @param  Collection<int, File>  $files  the files of the page
     * @param  array{file: File, choices: ImportChoices}|null  $check  the file the person is looking at, with its choices
     * @param  string  $locale  the language of the texts
     * @return array<int, ProposalData>
     */
    public function forPage(Collection $files, ?array $check, string $locale): array
    {
        if ($check !== null) {
            $others = $files->reject(static fn (File $file): bool => $file->id === $check['file']->id);

            return [$check['file']->id => $this->proposeWithGivers($check['file'], $check['choices'], $others, $locale)];
        }

        $proposals = [];
        foreach ($files as $file) {
            $proposals[$file->id] = $this->proposeWithGivers($file, new ImportChoices, $files->reject(static fn (File $other): bool => $other->id === $file->id), $locale);
        }

        return $proposals;
    }

    /**
     * The policy each file gives with the choices of the person, by file; the files that give none are left out.
     *
     * @param  array<int, array{file: File, choices: ImportChoices}>  $entries  the files with their choices, by file
     * @param  string  $locale  the language of the texts
     * @return array<int, PolicyGiverData>
     */
    public function givers(array $entries, string $locale): array
    {
        $givers = [];
        foreach ($entries as $id => ['file' => $file, 'choices' => $choices]) {
            if (! $this->mayGive($file, $choices)) {
                continue;
            }

            $giver = $this->policies->giver($file, $this->proposals->for($file, $choices, [], $locale), $choices);
            if ($giver instanceof PolicyGiverData) {
                $givers[$id] = $giver;
            }
        }

        return $givers;
    }

    /**
     * The proposal of a file; one that needs a policy is proposed again with those the other files give, each with
     * the boxes it suggests.
     *
     * @param  File  $file  the file
     * @param  ImportChoices  $choices  its choices
     * @param  Collection<int, File>  $others  the other files of the import
     * @param  string  $locale  the language of the texts
     */
    private function proposeWithGivers(File $file, ImportChoices $choices, Collection $others, string $locale): ProposalData
    {
        $proposal = $this->proposals->for($file, $choices, [], $locale);
        if (! ($proposal->policy['need'] ?? false) || $others->isEmpty()) {
            return $proposal;
        }

        $givers = [];
        foreach ($others as $other) {
            $giver = $this->untouchedGiver($other, $locale);
            if ($giver instanceof PolicyGiverData) {
                $givers[] = $giver;
            }
        }

        return $this->proposals->for($file, $choices, $givers, $locale);
    }

    /**
     * The policy a file the person has not touched gives: with the boxes of the cards it suggests ticked.
     *
     * @param  File  $file  the file
     * @param  string  $locale  the language of the texts
     */
    private function untouchedGiver(File $file, string $locale): ?PolicyGiverData
    {
        if (! $this->mayGive($file, new ImportChoices)) {
            return null;
        }

        $proposal = $this->proposals->for($file, new ImportChoices, [], $locale);
        $ticked = array_values(array_map(
            static fn (RecordDraftData $draft): RecordKind => $draft->kind,
            array_filter($proposal->records, static fn (RecordDraftData $draft): bool => $draft->suggested),
        ));

        return $this->policies->giver($file, $proposal, new ImportChoices(records: $ticked));
    }

    /**
     * Whether a file can give a policy at all: it needs a policy number, read or written by the person. Saves
     * proposing every file of the import.
     *
     * @param  File  $file  the file
     * @param  ImportChoices  $choices  its choices
     */
    private function mayGive(File $file, ImportChoices $choices): bool
    {
        $number = $choices->overrides['records'][RecordKind::Policy->value]['policy_number'] ?? null;

        return (is_string($number) && $number !== '') || (string) Extraction::ofFile($file)->policy_number !== '';
    }
}

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
     * it names is proposed again, with the choices the person made (the page merges it with the others). The
     * policies the untouched files give are worked out once, and only when a paper needs one.
     *
     * @param  Collection<int, File>  $files  the files of the page
     * @param  array{file: File, choices: ImportChoices}|null  $check  the file the person is looking at, with its choices
     * @param  string  $locale  the language of the texts
     * @return array<int, ProposalData>
     */
    public function forPage(Collection $files, ?array $check, string $locale): array
    {
        if ($check !== null) {
            $file = $check['file'];
            $proposal = $this->proposals->for($file, $check['choices'], [], $locale);
            if (! ($proposal->policy['need'] ?? false)) {
                return [$file->id => $proposal];
            }

            $others = $files->reject(static fn (File $other): bool => $other->id === $file->id);

            return [$file->id => $this->withGivers($file, $check['choices'], $proposal, $this->untouchedGivers($others, [], $locale), $locale)];
        }

        $proposals = [];
        foreach ($files as $file) {
            $proposals[$file->id] = $this->proposals->for($file, new ImportChoices, [], $locale);
        }

        $needing = array_filter($proposals, static fn (ProposalData $proposal): bool => (bool) ($proposal->policy['need'] ?? false));
        if ($needing === []) {
            return $proposals;
        }

        $givers = $this->untouchedGivers($files, $proposals, $locale);
        foreach (array_keys($needing) as $id) {
            $file = $files->first(static fn (File $file): bool => $file->id === $id);
            if ($file instanceof File) {
                $proposals[$id] = $this->withGivers($file, new ImportChoices, $proposals[$id], $givers, $locale);
            }
        }

        return $proposals;
    }

    /**
     * The policy each file gives with the choices of the person, by file; the files that give none are left out.
     * The proposal the request already made for a file is used instead of making it again.
     *
     * @param  array<int, array{file: File, choices: ImportChoices, proposal?: ?ProposalData}>  $entries  the files with their choices, by file
     * @param  string  $locale  the language of the texts
     * @return array<int, PolicyGiverData>
     */
    public function givers(array $entries, string $locale): array
    {
        $givers = [];
        foreach ($entries as $id => $entry) {
            ['file' => $file, 'choices' => $choices] = $entry;
            if (! $this->mayGive($file, $choices)) {
                continue;
            }

            $proposal = ($entry['proposal'] ?? null) ?? $this->proposals->for($file, $choices, [], $locale);
            $giver = $this->policies->giver($file, $proposal, $choices);
            if ($giver instanceof PolicyGiverData) {
                $givers[$id] = $giver;
            }
        }

        return $givers;
    }

    /**
     * The proposal of a file again with the policies the other files give, when it needs one.
     *
     * @param  File  $file  the file
     * @param  ImportChoices  $choices  its choices
     * @param  ProposalData  $proposal  its proposal without the other files
     * @param  array<int, PolicyGiverData>  $givers  the policies the files give, by file
     * @param  string  $locale  the language of the texts
     */
    private function withGivers(File $file, ImportChoices $choices, ProposalData $proposal, array $givers, string $locale): ProposalData
    {
        unset($givers[$file->id]);

        return ($proposal->policy['need'] ?? false) && $givers !== []
            ? $this->proposals->for($file, $choices, array_values($givers), $locale)
            : $proposal;
    }

    /**
     * The policy each file the person has not touched gives, with the boxes of the cards it suggests ticked, by file.
     *
     * @param  Collection<int, File>  $files  the files
     * @param  array<int, ProposalData>  $proposals  the proposals with no choices already made, by file
     * @param  string  $locale  the language of the texts
     * @return array<int, PolicyGiverData>
     */
    private function untouchedGivers(Collection $files, array $proposals, string $locale): array
    {
        $givers = [];
        foreach ($files as $file) {
            if (! $this->mayGive($file, new ImportChoices)) {
                continue;
            }

            $proposal = $proposals[$file->id] ?? $this->proposals->for($file, new ImportChoices, [], $locale);
            $ticked = array_values(array_map(
                static fn (RecordDraftData $draft): RecordKind => $draft->kind,
                array_filter($proposal->records, static fn (RecordDraftData $draft): bool => $draft->suggested),
            ));
            $giver = $this->policies->giver($file, $proposal, new ImportChoices(records: $ticked));
            if ($giver instanceof PolicyGiverData) {
                $givers[$file->id] = $giver;
            }
        }

        return $givers;
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

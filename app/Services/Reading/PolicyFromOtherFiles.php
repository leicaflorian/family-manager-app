<?php

declare(strict_types=1);

namespace App\Services\Reading;

use App\Data\Reading\Extraction;
use App\Data\Reading\ImportChoices;
use App\Data\Reading\PolicyGiverData;
use App\Data\Reading\ProposalData;
use App\Data\Reading\RecordDraftData;
use App\Enums\InsurancePolicyStatus;
use App\Enums\RecordKind;
use App\Models\File;
use App\Models\InsurancePolicy;
use App\Models\Organization;
use App\Support\Reading\OrganizationKey;

/**
 * The policy an insurance paper without a policy number takes: first from a file confirmed with it, then, when no
 * file fits, from the single matching policy already registered. With more than one it takes none.
 *
 * Needs are arrays `{organizationId, organizationName, personId, type}` of the paper.
 */
final class PolicyFromOtherFiles
{
    /**
     * The givers whose policy fits the paper: same company, and person and type when both have them.
     *
     * @param  array{organizationId: ?int, organizationName: ?string, personId: ?int, type: ?string}  $need
     * @param  list<PolicyGiverData>  $givers
     * @return list<PolicyGiverData>
     */
    public function matching(array $need, array $givers): array
    {
        return array_values(array_filter(
            $givers,
            fn (PolicyGiverData $giver): bool => $this->sameCompany($need, $giver)
                && $this->compatible($need['personId'], $giver->personId)
                && $this->compatible($need['type'], $giver->type),
        ));
    }

    /**
     * The giver the paper takes: the one picked by hand, or the only one that fits.
     *
     * @param  array{organizationId: ?int, organizationName: ?string, personId: ?int, type: ?string}  $need
     * @param  list<PolicyGiverData>  $givers
     * @param  string  $choice  `auto`, `none` or the `key` of a giver
     */
    public function chosen(array $need, array $givers, string $choice): ?PolicyGiverData
    {
        if ($choice === 'none') {
            return null;
        }

        $fitting = $this->matching($need, $givers);

        if ($choice !== 'auto') {
            return array_find($fitting, fn (PolicyGiverData $giver): bool => $giver->key === $choice);
        }

        return count($fitting) === 1 ? $fitting[0] : null;
    }

    /**
     * The giver of a paper looking at the files of the same import first and, only when none of them fits, at the
     * registered policies; a `policy:` key picked by hand is looked for among both (a file may give a registered
     * policy too).
     *
     * @param  array{organizationId: ?int, organizationName: ?string, personId: ?int, type: ?string}  $need
     * @param  list<PolicyGiverData>  $fileGivers
     * @param  string  $choice  `auto`, `none` or the `key` of a giver
     */
    public function resolve(array $need, array $fileGivers, string $choice): ?PolicyGiverData
    {
        if ($choice === 'none') {
            return null;
        }

        if (str_starts_with($choice, 'policy:')) {
            return $this->chosen($need, [...$fileGivers, ...$this->registered()], $choice);
        }

        if ($this->matching($need, $fileGivers) !== []) {
            return $this->chosen($need, $fileGivers, $choice);
        }

        return $this->chosen($need, $this->registered(), $choice);
    }

    /**
     * The policy a file of the import gives to the papers without a number (`giverOf` of the old page): the new
     * policy it creates, ticked and with its number (key `file:<id>`); the one picked by hand, or the one it found by
     * its number (key `policy:<id>`, so the import links it by id). The company and the person are those of its
     * document.
     *
     * @param  File  $file  the file
     * @param  ProposalData  $proposal  its proposal, with the choices of the person
     * @param  ImportChoices  $choices  the choices of the person
     */
    public function giver(File $file, ProposalData $proposal, ImportChoices $choices): ?PolicyGiverData
    {
        $x = Extraction::ofFile($file);
        $draft = array_find($proposal->records, static fn (RecordDraftData $record): bool => $record->kind === RecordKind::Policy);

        // A policy already registered is carried by its id: one picked by hand may have no number to find it by
        $policyId = null;
        if ($draft instanceof RecordDraftData && $draft->existing !== null) {
            $policyId = $draft->existing['id'];
            $registered = InsurancePolicy::query()->find($policyId)?->policy_number;
            $number = is_string($registered) && $registered !== '' ? $registered : $draft->existing['title'];
            [$creates, $type] = [false, $x->insurance_type];
        } elseif ($draft instanceof RecordDraftData) {
            $number = $draft->values['policy_number'] ?? null;
            if (! in_array(RecordKind::Policy, $choices->records, true) || ! is_string($number) || $number === '') {
                return null;
            }
            $type = $draft->values['type'] ?? null;
            [$creates, $type] = [true, is_string($type) ? $type : null];
        } else {
            $found = array_find($proposal->document->links, static fn (array $link): bool => $link['type'] === 'insurance_policy');
            if ($found === null || $x->policy_number === null || $x->policy_number === '') {
                return null;
            }
            [$policyId, $number, $creates, $type] = [$found['id'], $x->policy_number, false, $x->insurance_type];
        }

        $organizationId = $this->documentId($choices, 'organization_id', $proposal->document->organization_id);

        return new PolicyGiverData(
            key: $policyId === null ? 'file:'.$file->id : 'policy:'.$policyId,
            fileName: $file->original_name,
            number: $number,
            creates: $creates,
            organizationId: $organizationId,
            organizationName: $this->companyName($organizationId, $proposal, $choices, $x),
            personId: $this->documentId($choices, 'person_id', $proposal->document->person_id),
            type: $type,
        );
    }

    /**
     * The files giving a policy first, so that it is there for the others; each group keeps its order.
     *
     * @param  list<int>  $fileIds
     * @param  callable(int): bool  $gives
     * @return list<int>
     */
    public function confirmOrder(array $fileIds, callable $gives): array
    {
        $giving = array_values(array_filter($fileIds, $gives(...)));
        $others = array_values(array_filter($fileIds, fn (int $id): bool => ! $gives($id)));

        return [...$giving, ...$others];
    }

    /**
     * The active policies of the family that have a number, as givers that create nothing.
     *
     * @return list<PolicyGiverData>
     */
    public function registered(): array
    {
        $givers = InsurancePolicy::query()
            ->with('organization')
            ->where('status', InsurancePolicyStatus::Active)
            ->whereNotNull('policy_number')
            ->where('policy_number', '!=', '')
            ->orderBy('id')
            ->get()
            ->map(fn (InsurancePolicy $policy): PolicyGiverData => new PolicyGiverData(
                key: 'policy:'.$policy->id,
                fileName: null,
                number: (string) $policy->policy_number,
                creates: false,
                organizationId: $policy->organization_id,
                organizationName: $policy->organization?->name,
                personId: $policy->person_id,
                type: $policy->type->value,
            ))
            ->all();

        return array_values($givers);
    }

    /**
     * An id of the document: the one the person sent, otherwise the proposed one.
     *
     * @param  ImportChoices  $choices  the choices of the person
     * @param  string  $field  `organization_id` or `person_id`
     * @param  int|null  $proposed  the id of the proposal
     */
    private function documentId(ImportChoices $choices, string $field, ?int $proposed): ?int
    {
        if (! array_key_exists($field, $choices->document)) {
            return $proposed;
        }

        return is_numeric($choices->document[$field]) ? (int) $choices->document[$field] : null;
    }

    /**
     * The name of the company of a giver: the organization of the document; without one, the organization the file
     * creates, when it is ticked; otherwise the name read.
     *
     * @param  int|null  $organizationId  the organization of the document
     * @param  ProposalData  $proposal  the proposal
     * @param  ImportChoices  $choices  the choices of the person
     * @param  Extraction  $x  what the model read
     */
    private function companyName(?int $organizationId, ProposalData $proposal, ImportChoices $choices, Extraction $x): ?string
    {
        if ($organizationId !== null) {
            return Organization::query()->find($organizationId)?->name;
        }

        $created = array_find($proposal->records, static fn (RecordDraftData $record): bool => $record->kind === RecordKind::Organization && $record->existing === null);
        if ($created instanceof RecordDraftData && in_array(RecordKind::Organization, $choices->records, true)) {
            $name = $created->values['name'] ?? null;

            return is_string($name) ? $name : null;
        }

        return $x->issuer_name;
    }

    /**
     * Whether the giver is of the same company: the same id when both have one, otherwise the same name written in
     * any way (a company created in the same import has no id yet).
     *
     * @param  array{organizationId: ?int, organizationName: ?string, personId: ?int, type: ?string}  $need
     */
    private function sameCompany(array $need, PolicyGiverData $giver): bool
    {
        if ($need['organizationId'] !== null && $giver->organizationId !== null) {
            return $need['organizationId'] === $giver->organizationId;
        }

        $key = OrganizationKey::of($need['organizationName']);

        return $key !== '' && $key === OrganizationKey::of($giver->organizationName);
    }

    /**
     * The same value, or one of the two missing: a person or a type that is not there does not count.
     */
    private function compatible(int|string|null $a, int|string|null $b): bool
    {
        return $a === null || $b === null || $a === $b;
    }
}

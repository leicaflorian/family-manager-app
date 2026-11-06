<?php

declare(strict_types=1);

namespace App\Services\Reading;

use App\Data\Reading\PolicyGiverData;
use App\Enums\InsurancePolicyStatus;
use App\Models\InsurancePolicy;
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
     * registered policies; a `policy:` key picked by hand goes straight to the registered ones.
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

        if (! str_starts_with($choice, 'policy:') && $this->matching($need, $fileGivers) !== []) {
            return $this->chosen($need, $fileGivers, $choice);
        }

        return $this->chosen($need, $this->registered(), $choice);
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

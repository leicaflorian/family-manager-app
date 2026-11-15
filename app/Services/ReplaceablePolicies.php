<?php

declare(strict_types=1);

namespace App\Services;

use App\Cards\Types\InsurancePolicyCard;
use App\Enums\InsurancePolicyStatus;
use App\Models\InsurancePolicy;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * The rule of which old policies a new one can replace: those of the family
 * still active or expired, of the same type and covering the same card.
 */
final class ReplaceablePolicies
{
    /**
     * The policies of the family that can be replaced, whoever signed in can
     * edit, by expiry. Empty for an account that cannot edit policies.
     *
     * @return Collection<int, InsurancePolicy>
     */
    public function candidates(): Collection
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return new Collection;
        }

        return InsurancePolicy::query()
            ->with('organization')
            ->whereIn('status', [InsurancePolicyStatus::Active, InsurancePolicyStatus::Expired])
            ->oldest('expiry_date')
            ->orderBy('id')
            ->get()
            ->filter(fn (InsurancePolicy $policy): bool => $user->can('update', $policy))
            ->values();
    }

    /**
     * The candidates of the same type that cover the same card.
     *
     * @param  array{type: ?string, vehicle_id: ?int, home_id: ?int, employment_id: ?int}  $values  the type and the covered cards of the form
     * @param  int|null  $except  the policy being saved, left out
     * @return Collection<int, InsurancePolicy>
     */
    public function matching(array $values, ?int $except = null): Collection
    {
        $type = $values['type'];
        $column = $type === null ? null : $this->coveredColumn($type);
        $covered = $column === null ? null : $values[$column];

        if ($column === null || $covered === null) {
            return new Collection;
        }

        return $this->candidates()
            ->filter(fn (InsurancePolicy $policy): bool => $policy->type->value === $type
                && $policy->getAttribute($column) === $covered
                && $policy->id !== $except)
            ->values();
    }

    /**
     * The column of the card a type covers, read from the `showWhen` of the
     * fields of the policy card (the only place that says it).
     *
     * @param  string  $type  the policy type
     * @return 'vehicle_id'|'home_id'|'employment_id'|null
     */
    private function coveredColumn(string $type): ?string
    {
        foreach ((new InsurancePolicyCard)->fields() as $field) {
            if (in_array($field->column, ['vehicle_id', 'home_id', 'employment_id'], true)
                && in_array($type, $field->showWhen['type'] ?? [], true)) {
                return $field->column;
            }
        }

        return null;
    }
}

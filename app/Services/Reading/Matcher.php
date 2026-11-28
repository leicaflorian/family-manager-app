<?php

declare(strict_types=1);

namespace App\Services\Reading;

use App\Data\Reading\SupplyData;
use App\Enums\EmploymentStatus;
use App\Enums\FinancialAccountStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\UtilityStatus;
use App\Models\Employment;
use App\Models\FinancialAccount;
use App\Models\InsurancePolicy;
use App\Models\Organization;
use App\Models\Person;
use App\Models\Subscription;
use App\Models\Utility;
use App\Models\Vehicle;
use App\Support\Reading\Codes;
use App\Support\Reading\OrganizationKey;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * The exact searches that link a reading to a card of the family: by code,
 * plate, number, name. Every query goes through the family scope of the models.
 *
 * Codes are compared normalised on both sides, in PHP over the family's
 * candidate rows: a family has few of them, and the stored value can carry the
 * spaces and dashes it was typed with, which SQL cannot strip portably.
 */
final class Matcher
{
    /**
     * The utility with the given status (the active one by default) and the supply point code (POD/PDR),
     * otherwise the customer code.
     *
     * @param  string|null  $pod  the POD or PDR as printed
     * @param  string|null  $customer  the customer code as printed
     * @param  UtilityStatus  $status  the status the utility must have
     */
    public function utility(?string $pod, ?string $customer, UtilityStatus $status = UtilityStatus::Active): ?Utility
    {
        $where = ['status' => $status];

        return $this->byCode(Utility::class, 'supply_point_code', $pod, $where)
            ?? $this->byCode(Utility::class, 'customer_code', $customer, $where);
    }

    /**
     * The active utility of each supply of a bill with more than one: by its supply code, otherwise by the customer
     * code of the bill, but only among the active utilities of the same type that have no supply code, and only
     * when there is one of them. A utility already found for a supply before is not found again.
     *
     * @param  list<SupplyData>  $supplies  the supplies as read
     * @param  string|null  $customer  the customer code of the bill as printed
     * @return array<int, Utility|null> the utility by index of the supply, null when none fits
     */
    public function supplyUtilities(array $supplies, ?string $customer): array
    {
        $active = Utility::query()->where('status', UtilityStatus::Active)->orderBy('id')->get();
        $found = [];

        foreach ($supplies as $index => $supply) {
            $utility = $this->firstWithCode($active, 'supply_point_code', $supply->supply_point_code)
                ?? $this->onlyByCustomer($active, $supply->utility_type, $customer);
            $taken = array_filter($found, static fn (?Utility $before): bool => $before?->id === $utility?->id);

            $found[$index] = $taken === [] ? $utility : null;
        }

        return $found;
    }

    /**
     * The vehicle with the plate.
     *
     * @param  string|null  $plate  the plate as printed
     */
    public function vehicle(?string $plate): ?Vehicle
    {
        return $this->byCode(Vehicle::class, 'plate_number', $plate);
    }

    /**
     * The insurance policy with the number.
     *
     * @param  string|null  $number  the policy number as printed
     */
    public function policy(?string $number): ?InsurancePolicy
    {
        return $this->byCode(InsurancePolicy::class, 'policy_number', $number);
    }

    /**
     * The person with the tax code.
     *
     * @param  string|null  $taxCode  the tax code as printed
     */
    public function personByTaxCode(?string $taxCode): ?Person
    {
        return $this->byCode(Person::class, 'tax_code', $taxCode);
    }

    /**
     * The organization with the same name, written in any way.
     *
     * @param  string|null  $name  the name as printed
     */
    public function organizationByName(?string $name): ?Organization
    {
        $wanted = OrganizationKey::of($name);

        if ($wanted === '') {
            return null;
        }

        return Organization::query()->orderBy('id')->get()
            ->first(static fn (Organization $organization): bool => OrganizationKey::of($organization->name) === $wanted);
    }

    /**
     * The only active account whose last four digits are the given ones.
     *
     * @param  string|null  $lastFour  the digits as printed; of a longer string the last four count
     */
    public function account(?string $lastFour): ?FinancialAccount
    {
        $digits = substr(Codes::normalize($lastFour), -4);

        if (strlen($digits) !== 4) {
            return null;
        }

        $accounts = FinancialAccount::query()
            ->where('last_four_digits', $digits)
            ->where('status', FinancialAccountStatus::Active)
            ->limit(2)
            ->get();

        return $accounts->count() === 1 ? $accounts->first() : null;
    }

    /**
     * The active account marked as default, used when neither the IBAN nor the commitment tells the account.
     */
    public function defaultAccount(): ?FinancialAccount
    {
        return FinancialAccount::query()
            ->where('is_default', true)
            ->where('status', FinancialAccountStatus::Active)
            ->orderBy('id')
            ->first();
    }

    /**
     * The employment of the person with the employer, ended ones too (an old payslip belongs to a job left
     * since): among several, the one in progress on the date, then the active one; null when it stays ambiguous.
     *
     * @param  int|null  $personId  the employee, if known
     * @param  int|null  $organizationId  the employer, if known
     * @param  CarbonImmutable|null  $date  the date of the paper
     */
    public function employment(?int $personId, ?int $organizationId, ?CarbonImmutable $date): ?Employment
    {
        if ($personId === null && $organizationId === null) {
            return null;
        }

        $rows = Employment::query()
            ->when($personId !== null, static fn (Builder $query) => $query->where('person_id', $personId))
            ->when($organizationId !== null, static fn (Builder $query) => $query->where('organization_id', $organizationId))
            ->orderBy('id')
            ->get();

        $day = $date?->toDateString();
        $inProgress = $day === null ? $rows : $rows->filter(
            static fn (Employment $job): bool => ($job->start_date === null || $job->start_date->toDateString() <= $day)
                && ($job->end_date === null || $job->end_date->toDateString() >= $day),
        );
        $pool = $inProgress->isNotEmpty() ? $inProgress : $rows;

        if ($pool->count() === 1) {
            return $pool->first();
        }

        $active = $pool->filter(static fn (Employment $job): bool => $job->status === EmploymentStatus::Active);

        return $active->count() === 1 ? $active->first() : null;
    }

    /**
     * The only active subscription of the organization: the paper names the issuer, not the subscription.
     *
     * @param  int|null  $organizationId  the issuer
     */
    public function subscription(?int $organizationId): ?Subscription
    {
        if ($organizationId === null) {
            return null;
        }

        $subscriptions = Subscription::query()
            ->where('organization_id', $organizationId)
            ->where('status', SubscriptionStatus::Active)
            ->limit(2)
            ->get();

        return $subscriptions->count() === 1 ? $subscriptions->first() : null;
    }

    /**
     * The first row of the query whose code column equals the wanted code once both are normalised.
     *
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $model  the model whose family rows to look among
     * @param  string  $column  the column holding the code
     * @param  string|null  $code  the code as printed
     * @param  array<string, UtilityStatus>  $where  columns the candidates must equal (the active ones)
     * @return TModel|null
     */
    private function byCode(string $model, string $column, ?string $code, array $where = []): ?Model
    {
        if (Codes::normalize($code) === '') {
            return null;
        }

        return $this->firstWithCode($model::query()->where($where)->whereNotNull($column)->orderBy('id')->get(), $column, $code);
    }

    /**
     * The first of the rows whose code column equals the wanted code once both are normalised.
     *
     * @template TModel of Model
     *
     * @param  Collection<int, TModel>  $rows  the candidates
     * @param  string  $column  the column holding the code
     * @param  string|null  $code  the code as printed
     * @return TModel|null
     */
    private function firstWithCode(Collection $rows, string $column, ?string $code): ?Model
    {
        $wanted = Codes::normalize($code);

        if ($wanted === '') {
            return null;
        }

        return $rows->first(static function (Model $row) use ($column, $wanted): bool {
            $stored = $row->getAttribute($column);

            return is_string($stored) && Codes::normalize($stored) === $wanted;
        });
    }

    /**
     * The only utility of the type, without a supply code, with the customer code.
     *
     * @param  Collection<int, Utility>  $utilities  the active utilities
     * @param  string|null  $type  the type of the supply, a value of `UtilityType`
     * @param  string|null  $customer  the customer code as printed
     */
    private function onlyByCustomer(Collection $utilities, ?string $type, ?string $customer): ?Utility
    {
        $wanted = Codes::normalize($customer);

        if ($type === null || $wanted === '') {
            return null;
        }

        $matches = $utilities->filter(static fn (Utility $utility): bool => $utility->type->value === $type
            && Codes::normalize($utility->supply_point_code) === ''
            && Codes::normalize($utility->customer_code) === $wanted);

        return $matches->count() === 1 ? $matches->first() : null;
    }
}

<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Data\Reading\Extraction;
use App\Models\Family;
use App\Models\Organization;
use App\Models\Utility;
use App\Support\CurrentFamily;

/**
 * The dual-fuel bill of the Bianchi test data (Enel Energia, electricity and gas on one paper), as the model answers
 * it. Made-up values only.
 */
final class EnelBill
{
    /** The POD of the electricity supply. */
    public const string POD = 'IT 001 E 87654321';

    /** The PDR of the gas supply. */
    public const string PDR = '00881234567890';

    /** The customer code of both supplies. */
    public const string CUSTOMER = '310045678';

    /**
     * The Bianchi of the seeder made into the variant of the bill: the electricity of Edison becomes the one of
     * Enel Energia, with the POD and the customer code, and the gas of Enel gets the PDR. The planned expenses
     * *Luce* and *Gas* of the seeder stay linked to them.
     *
     * @return array{electricity: Utility, gas: Utility}
     */
    public static function bianchi(): array
    {
        $family = Family::query()->where('name', 'Bianchi')->sole();

        return CurrentFamily::using($family->id, static function (): array {
            $enel = Organization::query()->where('name', 'Enel Energia')->sole();
            $electricity = Utility::query()->whereRelation('organization', 'name', 'Edison')->sole();
            $electricity->update(['organization_id' => $enel->id, 'supply_point_code' => self::POD, 'customer_code' => self::CUSTOMER]);
            $gas = Utility::query()->where('organization_id', $enel->id)->where('type', 'gas')->sole();
            $gas->update(['supply_point_code' => self::PDR]);

            return ['electricity' => $electricity->refresh(), 'gas' => $gas->refresh()];
        });
    }

    /**
     * The reading of the bill, as `ReadingPrompt::parse()` leaves it.
     *
     * @param  array<string, mixed>  $changes  fields to change
     */
    public static function extraction(array $changes = []): Extraction
    {
        return Extraction::from(self::answer($changes));
    }

    /**
     * The answer of the model for the bill, with both supplies.
     *
     * @param  array<string, mixed>  $changes  fields to change
     * @return array<string, mixed>
     */
    public static function answer(array $changes = []): array
    {
        return [
            'kind' => 'utility_bill',
            'title' => 'Bolletta luce e gas',
            'issuer_name' => 'Enel Energia',
            'total_amount' => 165.65,
            'issue_date' => '2026-11-05',
            'due_date' => '2026-11-21',
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-30',
            'consumption_quantity' => 201.0,
            'consumption_unit' => 'kwh',
            'consumption_estimated' => false,
            'consumption_adjusted' => false,
            'supply_point_code' => self::POD,
            'customer_code' => self::CUSTOMER,
            'iban_last_four' => '4821',
            'utility_type' => 'electricity',
            'supplies' => [self::electricity(), self::gas()],
            'summary' => 'Bolletta luce e gas di settembre e ottobre 2026',
            'confidence' => 'high',
            ...$changes,
        ];
    }

    /**
     * The electricity supply of the bill.
     *
     * @param  array<string, mixed>  $changes  fields to change
     * @return array<string, mixed>
     */
    public static function electricity(array $changes = []): array
    {
        return [
            'utility_type' => 'electricity',
            'supply_point_code' => self::POD,
            'amount' => 96.25,
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-30',
            'consumption_quantity' => 201.0,
            'consumption_unit' => 'kwh',
            'consumption_estimated' => false,
            'consumption_adjusted' => false,
            'consumption_energy_amount' => null,
            'consumption_f1' => null,
            'consumption_f2' => null,
            'consumption_f3' => null,
            ...$changes,
        ];
    }

    /**
     * The gas supply of the bill.
     *
     * @param  array<string, mixed>  $changes  fields to change
     * @return array<string, mixed>
     */
    public static function gas(array $changes = []): array
    {
        return [
            'utility_type' => 'gas',
            'supply_point_code' => self::PDR,
            'amount' => 69.40,
            'period_start' => '2026-10-01',
            'period_end' => '2026-10-31',
            'consumption_quantity' => 48.0,
            'consumption_unit' => 'smc',
            'consumption_estimated' => false,
            'consumption_adjusted' => false,
            'consumption_energy_amount' => null,
            'consumption_f1' => null,
            'consumption_f2' => null,
            'consumption_f3' => null,
            ...$changes,
        ];
    }
}

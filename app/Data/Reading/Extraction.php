<?php

declare(strict_types=1);

namespace App\Data\Reading;

use App\Enums\HealthEventType;
use App\Enums\IncomeType;
use App\Enums\InsurancePolicyType;
use App\Enums\MaintenanceType;
use App\Enums\OrganizationType;
use App\Enums\UtilityType;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What the model reads from the paper. Every field can be empty: the model answers null for what is not on the
 * document. Ids are chosen from the lists of the prompt and checked afterwards (`ReadingPrompt::parse()`).
 */
#[TypeScript]
final class Extraction extends Data
{
    /** The kinds of paper the model tells apart. */
    public const array KINDS = ['utility_bill', 'receipt', 'invoice', 'payment_receipt', 'payslip', 'insurance', 'road_tax', 'medical', 'other'];

    /** The levels of confidence. */
    public const array CONFIDENCES = ['high', 'medium', 'low'];

    /** The fields added for the missing cards: a reading made before has none of them. */
    public const array RECORD_FIELDS = [
        'utility_type', 'insurance_type', 'organization_type', 'durable_good', 'item_name', 'item_brand', 'item_model',
        'warranty_expiry_date', 'vehicle_brand', 'vehicle_model', 'vehicle_year', 'contract_start_date',
    ];

    /** Every field of the answer, in the order of the schema. */
    public const array FIELDS = [
        'kind', 'title', 'issuer_name', 'organization_id', 'total_amount', 'fee_amount', 'issue_date', 'due_date', 'expiry_date',
        'period_start', 'period_end', 'supply_point_code', 'customer_code', 'iban_last_four', 'policy_number', 'plate_number',
        'tax_code', 'expense_category_id', 'document_category_id', 'person_id', 'income_type', 'maintenance_type', 'health_type',
        'outcome', 'next_checkup_date', 'utility_type', 'insurance_type', 'organization_type', 'durable_good', 'item_name',
        'item_brand', 'item_model', 'warranty_expiry_date', 'vehicle_brand', 'vehicle_model', 'vehicle_year', 'contract_start_date',
        'summary', 'confidence',
    ];

    /**
     * @param  string|null  $kind  one of `KINDS`
     * @param  string|null  $title  a short title of what the paper is about, in the language of who uploaded
     * @param  string|null  $issuer_name  who made the document
     * @param  int|null  $organization_id  the organization of the list that fits the issuer
     * @param  float|null  $total_amount  in euro; for a payment receipt the sum without the fee
     * @param  float|null  $fee_amount  the fee of a payment receipt
     * @param  string|null  $issue_date  `YYYY-MM-DD`
     * @param  string|null  $due_date  `YYYY-MM-DD`, the payment deadline
     * @param  string|null  $expiry_date  `YYYY-MM-DD`, when the document stops being valid
     * @param  string|null  $period_start  `YYYY-MM-DD`
     * @param  string|null  $period_end  `YYYY-MM-DD`
     * @param  string|null  $supply_point_code  POD or PDR
     * @param  string|null  $customer_code  the customer code of a supply
     * @param  string|null  $iban_last_four  last four characters of the IBAN
     * @param  string|null  $policy_number  the number of an insurance policy
     * @param  string|null  $plate_number  the plate of a vehicle
     * @param  string|null  $tax_code  the tax code of the person the paper is about
     * @param  int|null  $expense_category_id  a category of the list
     * @param  int|null  $document_category_id  a category of the list
     * @param  int|null  $person_id  the person of the list the paper is about
     * @param  string|null  $income_type  a value of `IncomeType`
     * @param  string|null  $maintenance_type  a value of `MaintenanceType`
     * @param  string|null  $health_type  a value of `HealthEventType`
     * @param  string|null  $outcome  the diagnosis or result of a medical paper
     * @param  string|null  $next_checkup_date  `YYYY-MM-DD`
     * @param  string|null  $utility_type  a value of `UtilityType`
     * @param  string|null  $insurance_type  a value of `InsurancePolicyType`
     * @param  string|null  $organization_type  a value of `OrganizationType`, for the issuer when it is missing
     * @param  bool|null  $durable_good  whether the receipt is for a durable item
     * @param  string|null  $item_name  the main item bought
     * @param  string|null  $item_brand  its brand
     * @param  string|null  $item_model  its model
     * @param  string|null  $warranty_expiry_date  `YYYY-MM-DD`
     * @param  string|null  $vehicle_brand  the brand of the vehicle the paper is about
     * @param  string|null  $vehicle_model  its model
     * @param  int|null  $vehicle_year  the year of first registration
     * @param  string|null  $contract_start_date  `YYYY-MM-DD`
     * @param  string|null  $summary  one short sentence about the paper
     * @param  string|null  $confidence  one of `CONFIDENCES`
     */
    public function __construct(
        public ?string $kind = null,
        public ?string $title = null,
        public ?string $issuer_name = null,
        public ?int $organization_id = null,
        public ?float $total_amount = null,
        public ?float $fee_amount = null,
        public ?string $issue_date = null,
        public ?string $due_date = null,
        public ?string $expiry_date = null,
        public ?string $period_start = null,
        public ?string $period_end = null,
        public ?string $supply_point_code = null,
        public ?string $customer_code = null,
        public ?string $iban_last_four = null,
        public ?string $policy_number = null,
        public ?string $plate_number = null,
        public ?string $tax_code = null,
        public ?int $expense_category_id = null,
        public ?int $document_category_id = null,
        public ?int $person_id = null,
        public ?string $income_type = null,
        public ?string $maintenance_type = null,
        public ?string $health_type = null,
        public ?string $outcome = null,
        public ?string $next_checkup_date = null,
        public ?string $utility_type = null,
        public ?string $insurance_type = null,
        public ?string $organization_type = null,
        public ?bool $durable_good = null,
        public ?string $item_name = null,
        public ?string $item_brand = null,
        public ?string $item_model = null,
        public ?string $warranty_expiry_date = null,
        public ?string $vehicle_brand = null,
        public ?string $vehicle_model = null,
        public ?int $vehicle_year = null,
        public ?string $contract_start_date = null,
        public ?string $summary = null,
        public ?string $confidence = null,
    ) {}

    /**
     * The values of the enum-like fields, by field: what the schema allows and what `parse()` keeps.
     *
     * @return array<string, list<string>>
     */
    public static function choices(): array
    {
        return [
            'kind' => self::KINDS,
            'income_type' => array_column(IncomeType::cases(), 'value'),
            'maintenance_type' => array_column(MaintenanceType::cases(), 'value'),
            'health_type' => array_column(HealthEventType::cases(), 'value'),
            'utility_type' => array_column(UtilityType::cases(), 'value'),
            'insurance_type' => array_column(InsurancePolicyType::cases(), 'value'),
            'organization_type' => array_column(OrganizationType::cases(), 'value'),
            'confidence' => self::CONFIDENCES,
        ];
    }
}

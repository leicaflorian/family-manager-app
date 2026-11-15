<?php

declare(strict_types=1);

namespace App\Support\Reminders;

use App\Enums\DocumentStatus;
use App\Enums\InsurancePolicyStatus;
use App\Enums\LoanStatus;
use App\Models\Document;
use App\Models\InsurancePolicy;
use App\Models\Loan;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;

/**
 * The cards whose status follows their date: the day after it, `from` becomes `to`. `renews` says whether `to`
 * still leaves something to do (a policy or a document *Expired* waits for its renewal) or closes the card for
 * good (a loan *Paid off*).
 */
final class Expiries
{
    /**
     * Every expiry.
     *
     * @return list<array{model: class-string<Model>, column: string, from: BackedEnum, to: BackedEnum, renews: bool}>
     */
    public static function all(): array
    {
        return [
            ['model' => Document::class, 'column' => 'expiry_date', 'from' => DocumentStatus::Active, 'to' => DocumentStatus::Expired, 'renews' => true],
            ['model' => InsurancePolicy::class, 'column' => 'expiry_date', 'from' => InsurancePolicyStatus::Active, 'to' => InsurancePolicyStatus::Expired, 'renews' => true],
            ['model' => Loan::class, 'column' => 'end_date', 'from' => LoanStatus::Active, 'to' => LoanStatus::PaidOff, 'renews' => false],
        ];
    }

    /**
     * The expiry of a model class, if it has one.
     *
     * @param  class-string<Model>  $model  the model class
     * @return array{model: class-string<Model>, column: string, from: BackedEnum, to: BackedEnum, renews: bool}|null
     */
    public static function for(string $model): ?array
    {
        foreach (self::all() as $expiry) {
            if ($expiry['model'] === $model) {
                return $expiry;
            }
        }

        return null;
    }
}

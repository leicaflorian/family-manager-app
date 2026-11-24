<?php

declare(strict_types=1);

namespace App\Actions\Families;

use App\Actions\Loans\CreateLoanPlans;
use App\Actions\Mail\AssignMailAddress;
use App\Models\Family;
use App\Models\FamilySetting;
use App\Services\FamilyFeatures;
use App\Support\CurrentFamily;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Turns the Budget and the mail for the bills of a family on or off. What was saved stays: it comes back when the
 * feature is turned on again, and the mail keeps its address. Turning the Budget on gives the loans saved while it
 * was off their planned expense, from that day on.
 */
final readonly class ChangeFeatures
{
    /**
     * @param  AssignMailAddress  $assignMailAddress  gives the family its address when it has none
     * @param  FamilyFeatures  $features  forgets what it read of the family
     * @param  CreateLoanPlans  $loanPlans  creates the planned expense of the loans that have none
     */
    public function __construct(private AssignMailAddress $assignMailAddress, private FamilyFeatures $features, private CreateLoanPlans $loanPlans) {}

    /**
     * Writes the switches. Turning the mail on for a family without an address gives it one; turning the Budget on
     * creates the planned expense of the loans without one.
     *
     * @param  Family  $family  the family
     * @param  bool  $budget  whether the family uses the Budget
     * @param  bool  $mail  whether the family uses the mail for the bills
     */
    public function handle(Family $family, bool $budget, bool $mail): void
    {
        DB::transaction(function () use ($family, $budget, $mail): void {
            $turnsBudgetOn = $budget && ! $family->budget_enabled;
            $family->forceFill(['budget_enabled' => $budget, 'mail_enabled' => $mail])->save();
            $this->features->forget($family);

            if ($turnsBudgetOn) {
                CurrentFamily::using($family->id, fn (): int => $this->loanPlans->handle(CarbonImmutable::today('Europe/Rome')));
            }

            if ($mail && ! $this->hasAddress($family)) {
                $this->assignMailAddress->handle($family);
            }
        });
    }

    /**
     * Whether the family already has its address for the bills.
     *
     * @param  Family  $family  the family
     */
    private function hasAddress(Family $family): bool
    {
        return CurrentFamily::using($family->id, static fn (): bool => FamilySetting::query()->whereNotNull('mail_inbox_recipient')->exists());
    }
}

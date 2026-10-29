<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Loans\RefreshLoan;
use App\Enums\LoanStatus;
use App\Models\Family;
use App\Models\Loan;
use App\Support\CurrentFamily;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Works out again the active loans of every family, for the instalments that the calendar makes paid: it runs
 * every night after midnight (`routes/console.php`).
 */
#[Description('Work out again the instalments paid of the active loans')]
#[Signature('loans:refresh')]
final class RefreshLoans extends Command
{
    /**
     * Refreshes the active loans of each family, inside that family, and prints how many changed.
     *
     * @param  RefreshLoan  $refresh  Works out a loan.
     */
    public function handle(RefreshLoan $refresh): int
    {
        $today = CarbonImmutable::today();
        $changed = 0;

        foreach (Family::query()->orderBy('id')->get(['id']) as $family) {
            $changed += CurrentFamily::using($family->id, fn (): int => Loan::query()
                ->where('status', LoanStatus::Active)
                ->get()
                ->filter(fn (Loan $loan): bool => $refresh->handle($loan, $today))
                ->count());
        }

        $this->info("Loans changed: {$changed}");

        return self::SUCCESS;
    }
}

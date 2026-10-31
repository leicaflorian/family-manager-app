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
use Throwable;

/**
 * Works out again the active loans of every family, for the instalments that the calendar makes paid: it runs
 * every night after midnight (`routes/console.php`).
 */
#[Description('Work out again the instalments paid of the active loans')]
#[Signature('loans:refresh')]
final class RefreshLoans extends Command
{
    /**
     * Refreshes the active loans of each family, inside that family, and prints how many changed. A loan that cannot
     * be worked out is reported and skipped, so it does not stop the others; the command then ends with a failure.
     *
     * @param  RefreshLoan  $refresh  Works out a loan.
     */
    public function handle(RefreshLoan $refresh): int
    {
        $today = CarbonImmutable::today();
        $changed = 0;
        $failed = 0;

        foreach (Family::query()->orderBy('id')->get(['id']) as $family) {
            CurrentFamily::using($family->id, function () use ($refresh, $today, &$changed, &$failed): void {
                foreach (Loan::query()->where('status', LoanStatus::Active)->get() as $loan) {
                    try {
                        $changed += (int) $refresh->handle($loan, $today);
                    } catch (Throwable $exception) {
                        report($exception);
                        $failed++;
                    }
                }
            });
        }

        $this->info("Loans changed: {$changed}");

        if ($failed > 0) {
            $this->error("Loans that could not be worked out: {$failed}");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}

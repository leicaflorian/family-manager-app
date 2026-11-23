<?php

declare(strict_types=1);

namespace App\Console\Concerns;

use App\Models\Family;
use App\Support\CurrentFamily;
use Closure;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

/**
 * The commands that do something inside each family (`reminders:run`, `banks:sync`): the `--family` option, the
 * loop over the families with `CurrentFamily::using()`, a family that fails reported and skipped, and the line of
 * the count with the exit code.
 *
 * @phpstan-require-extends Command
 */
trait RunsPerFamily
{
    /**
     * An option as text, empty when it was not given.
     *
     * @param  string  $name  the name of the option
     */
    private function textOption(string $name): string
    {
        $value = $this->option($name);

        return is_string($value) || is_int($value) ? (string) $value : '';
    }

    /**
     * Whether `--family` is empty or the id of a family; when it is not, says so.
     */
    private function hasValidFamilyOption(): bool
    {
        $only = $this->textOption('family');

        if ($only !== '' && (! ctype_digit($only) || (int) $only < 1)) {
            $this->error('--family must be the id of a family.');

            return false;
        }

        return true;
    }

    /**
     * Does the work inside each family that is not blocked, by id (only the one of `--family`, when given). A family
     * that fails is reported and skipped, so it does not stop the others; the command then ends with a failure.
     *
     * @param  string  $label  the words before the total, like `Notices created`
     * @param  Closure(Family): int  $work  what to do inside a family, returning how many things it did
     * @param  (Closure(Builder<Family>): mixed)|null  $narrow  keeps only some of the families, null for all
     * @return int the exit code
     */
    private function runPerFamily(string $label, Closure $work, ?Closure $narrow = null): int
    {
        $only = $this->textOption('family');
        $done = 0;
        $failed = 0;
        $families = Family::query()->notBlocked()->orderBy('id');

        if ($narrow instanceof Closure) {
            $narrow($families);
        }

        if ($only !== '') {
            $families->whereKey((int) $only);
        }

        foreach ($families->get(['id']) as $family) {
            try {
                $done += CurrentFamily::using($family->id, static fn (): int => $work($family));
            } catch (Throwable $exception) {
                report($exception);
                $failed++;
            }
        }

        $this->info("{$label}: {$done}");

        if ($failed > 0) {
            $this->error("Families that failed: {$failed}");

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}

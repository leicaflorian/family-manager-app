<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Support\CurrentFamily;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Tries;

/**
 * What is done with a received mail after Mailgun has its answer: the question to the AI, the notice held, the
 * readings and the notice in the bell. It runs in the queue, where nobody is signed in, so it carries the family of
 * the mail and works inside it, whatever family is set around it.
 *
 * For now it only enters the family: the work is written by the task of the plan that follows (Task 10).
 */
#[Tries(3)]
final class ScreenReceivedMail implements ShouldQueue
{
    use Queueable;

    /**
     * @param  int  $mailId  the received mail
     * @param  int  $familyId  the family of the mail
     */
    public function __construct(
        public readonly int $mailId,
        public readonly int $familyId,
    ) {}

    /**
     * Works on the mail inside its family.
     */
    public function handle(): void
    {
        CurrentFamily::using($this->familyId, static function (): void {
            // filled by Task 10: the screening, the readings and the notice in the bell
        });
    }
}

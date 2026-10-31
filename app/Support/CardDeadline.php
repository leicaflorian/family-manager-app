<?php

declare(strict_types=1);

namespace App\Support;

use App\Cards\DeadlineSource;
use App\Enums\ReminderStatus;
use App\Models\Person;
use App\Models\Reminder;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * One deadline of a card or a document: the date, who it belongs to and the
 * automatic reminder of that date, if there is one.
 */
final readonly class CardDeadline
{
    /**
     * @param  DeadlineSource  $source  the kind of deadline
     * @param  Model  $card  the card or the document that holds the date
     * @param  CarbonImmutable  $date  the day it expires
     * @param  Person|null  $person  the person it belongs to, null when it is nobody's
     * @param  string  $area  the area of the card, `general` for a document
     * @param  Reminder|null  $reminder  the automatic reminder of this date, null when there is none
     */
    public function __construct(
        public DeadlineSource $source,
        public Model $card,
        public CarbonImmutable $date,
        public ?Person $person,
        public string $area,
        public ?Reminder $reminder,
    ) {}

    /**
     * Whether the automatic reminder of this deadline is done.
     */
    public function reminderDone(): bool
    {
        return $this->reminder?->status === ReminderStatus::Done;
    }
}

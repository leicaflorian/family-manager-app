<?php

declare(strict_types=1);

namespace App\Support\Reminders;

use App\Data\AgendaEntryData;

/**
 * The week of a family as the Monday mail tells it: what is late, what is to pay and the other things to do.
 */
final readonly class WeeklySummary
{
    /**
     * @param  list<AgendaEntryData>  $late  the open entries due before the Monday
     * @param  list<AgendaEntryData>  $toPay  the unpaid payments of the week, the ones with an amount
     * @param  list<AgendaEntryData>  $tasks  the deadlines and tasks of the week without an amount
     * @param  float  $total  the amount of the late and the to pay entries, to the cent
     * @param  bool  $estimated  whether one of the amounts of the total is an estimate
     */
    public function __construct(
        public array $late,
        public array $toPay,
        public array $tasks,
        public float $total,
        public bool $estimated,
    ) {}

    /**
     * The ids of the reminders behind the entries of the three lists, to mark them as noticed.
     *
     * @return list<int>
     */
    public function reminderIds(): array
    {
        $ids = [];

        foreach ([...$this->late, ...$this->toPay, ...$this->tasks] as $entry) {
            if ($entry->reminder !== null) {
                $ids[] = $entry->reminder->id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Whether there is nothing to tell, so no mail goes out.
     */
    public function isEmpty(): bool
    {
        return $this->late === [] && $this->toPay === [] && $this->tasks === [];
    }
}

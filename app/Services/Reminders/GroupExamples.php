<?php

declare(strict_types=1);

namespace App\Services\Reminders;

use App\Data\ReminderSubjectData;
use App\Models\Reminder;
use App\Models\ReminderGroup;
use App\Support\Reminders\GroupRules;
use App\Support\Reminders\NotificationRule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;

/**
 * The sentence under a group of *Settings → Notices*: when its notices would go out for the card of the group that
 * expires next.
 */
final readonly class GroupExamples
{
    public function __construct(private ReminderList $list) {}

    /**
     * «With inspection – Panda – of 09/01/2027 the notices go out on 25/11, 25/12 and 09/01.» Null when the group
     * covers no kind with a coming card, or has no notices.
     *
     * @param  ReminderGroup  $group  the group
     * @param  CarbonImmutable  $today  today in Rome
     */
    public function for(ReminderGroup $group, CarbonImmutable $today): ?string
    {
        $rules = NotificationRule::listOf($group->notifications);
        $types = array_values(array_filter($group->deadline_types ?? [], is_string(...)));

        if ($rules === [] || $types === []) {
            return null;
        }

        $reminder = Reminder::query()
            ->open()
            ->where('is_automatic', true)
            ->whereIn('source_field', $types)
            ->whereDate('due_date', '>=', $today->toDateString())
            ->with('link')
            ->oldest('due_date')
            ->orderBy('id')
            ->first();
        $subject = $reminder instanceof Reminder ? $this->list->subject($reminder->link) : null;

        if (! $reminder instanceof Reminder || ! $subject instanceof ReminderSubjectData) {
            return null;
        }

        $deadline = CarbonImmutable::parse($reminder->due_date->toDateString());
        $days = array_values(array_unique(array_map(static fn (NotificationRule $rule): string => $deadline->addDays($rule->offset)->format('Y-m-d'), $rules)));
        sort($days);

        return __('With :kind – :card – of :date the notices go out on :days.', [
            'kind' => mb_strtolower(GroupRules::typeName((string) $reminder->source_field)),
            'card' => $subject->label,
            'date' => $deadline->format('d/m/Y'),
            'days' => Arr::join(array_map(static fn (string $day): string => CarbonImmutable::parse($day)->format('d/m'), $days), ', ', __(' and ')),
        ]);
    }
}

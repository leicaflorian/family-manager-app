<?php

declare(strict_types=1);

namespace App\Support\Reminders;

use App\Data\AgendaData;
use Carbon\CarbonImmutable;

/**
 * One notice of the morning email, with raw values: the mail words it in the language of the account when it is
 * rendered, which may be later than when it was queued.
 */
final readonly class MorningItem
{
    /**
     * @param  string  $title  the title of the reminder: an English key when `translatable`, else the text written by hand
     * @param  bool  $translatable  whether the title is a key of an automatic reminder, to translate
     * @param  bool  $manual  whether the reminder was written by hand (it says "by" instead of "due")
     * @param  string|null  $subject  the name of the thing the reminder refers to
     * @param  string  $dueOn  the due date, `aaaa-mm-gg`
     * @param  string  $url  the page of the thing, or of the reminders
     * @param  string  $area  the area of the thing, for its icon (`general` without one)
     */
    public function __construct(
        public string $title,
        public bool $translatable,
        public bool $manual,
        public ?string $subject,
        public string $dueOn,
        public string $url,
        public string $area = AgendaData::GENERAL_AREA,
    ) {}

    /**
     * The title in the language of the mail being rendered.
     */
    public function displayTitle(): string
    {
        return $this->translatable ? __($this->title) : $this->title;
    }

    /**
     * The due date as a day in Rome.
     */
    public function dueDay(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->dueOn, 'Europe/Rome');
    }

    /**
     * The line of this notice in a mail, worded in the language being rendered.
     *
     * @param  CarbonImmutable  $today  today in Rome
     * @return array{title: string, subject: ?string, when: string, late: bool, url: string, area: string}
     */
    public function line(CarbonImmutable $today): array
    {
        $due = $this->dueDay();

        return [
            'title' => $this->displayTitle(),
            'subject' => $this->subject,
            'when' => DueText::when($today, $due, $this->manual),
            // Due today or already late: written in red, as on the board
            'late' => $due->lessThanOrEqualTo($today),
            'url' => $this->url,
            'area' => $this->area,
        ];
    }
}

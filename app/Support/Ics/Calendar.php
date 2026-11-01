<?php

declare(strict_types=1);

namespace App\Support\Ics;

use Carbon\CarbonImmutable;

/**
 * The iCalendar text (RFC 5545) of the family's calendar: one all-day event per reminder.
 */
final class Calendar
{
    private const int MAX_LINE_BYTES = 75;

    private const string MISSING_EXPENSE = ' · manca la spesa prevista';

    /**
     * The whole calendar. Events last one day (the end is the day after, excluded) and do not mark the day as busy.
     * The refresh interval is a hint: Apple and Outlook follow it, Google Calendar reads the feed when it wants.
     *
     * @param  string  $name  the name the calendar shows in the app that subscribes to it
     * @param  list<CalendarEvent>  $events  the events, one per reminder
     * @return string the calendar, with `\r\n` line ends
     */
    public static function render(string $name, array $events): string
    {
        $stamp = CarbonImmutable::now('UTC')->format('Ymd\THis\Z');

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Family Manager//Promemoria//IT',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:'.self::escapeText($name),
            'REFRESH-INTERVAL;VALUE=DURATION:PT1H',
            'X-PUBLISHED-TTL:PT1H',
        ];

        foreach ($events as $event) {
            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:'.$event->uid;
            $lines[] = 'DTSTAMP:'.$stamp;
            $lines[] = 'DTSTART;VALUE=DATE:'.$event->date->format('Ymd');
            $lines[] = 'DTEND;VALUE=DATE:'.$event->date->addDay()->format('Ymd');
            $lines[] = 'SUMMARY:'.self::escapeText($event->summary);
            if ($event->description !== '') {
                $lines[] = 'DESCRIPTION:'.self::escapeText($event->description);
            }
            $lines[] = 'TRANSP:TRANSPARENT';
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map(self::fold(...), $lines))."\r\n";
    }

    /**
     * Commas, semicolons, backslashes and line breaks have a meaning in a text value.
     *
     * @param  string  $text  the text of the value
     * @return string the text with its special characters escaped
     */
    public static function escapeText(string $text): string
    {
        $text = str_replace('\\', '\\\\', $text);
        $text = (string) preg_replace('/\r\n|\r|\n/', '\n', $text);

        return str_replace([',', ';'], ['\,', '\;'], $text);
    }

    /**
     * A line longer than 75 bytes goes on in the next one, which starts with a space. Characters are never split.
     *
     * @param  string  $line  one content line
     * @return string the line, folded with `\r\n` and a space where it goes on
     */
    public static function fold(string $line): string
    {
        $parts = [];
        $current = '';
        $size = 0;

        foreach (mb_str_split($line) as $char) {
            $bytes = strlen($char);
            if ($size + $bytes > self::MAX_LINE_BYTES) {
                $parts[] = $current;
                $current = ' ';
                $size = 1;
            }
            $current .= $char;
            $size += $bytes;
        }
        $parts[] = $current;

        return implode("\r\n", $parts);
    }

    /**
     * An automatic reminder's title without the deadline (the event is on that day) and without the note on the
     * missing planned expense, which goes in the description. The patterns stay Italian: reminders copied from
     * Directus have titles like «Pagare il bollo · Panda · scade il 31/01/2027 · manca la spesa prevista».
     */
    public static function withoutDeadline(string $title): string
    {
        return (string) preg_replace('/ · scade il \d{2}\/\d{2}\/\d{4}.*$/u', '', $title);
    }

    /**
     * Whether an automatic reminder's title says that its planned expense is missing.
     */
    public static function missesExpense(string $title): bool
    {
        return str_ends_with($title, self::MISSING_EXPENSE);
    }
}

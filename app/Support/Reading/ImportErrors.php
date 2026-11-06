<?php

declare(strict_types=1);

namespace App\Support\Reading;

use App\Support\Translated;

/**
 * Why the confirm of a file in *Import* is refused, in the language of the app: what a ticked box still needs, and a
 * movement, event or card picked by hand that is no longer there. The request says them before saving; the import
 * says the same when something changed in between.
 */
final class ImportErrors
{
    /**
     * A ticked expense or income without what it needs.
     *
     * @param  string  $kind  `expense` or `income`
     * @param  bool  $toPay  "to pay" is in effect: the deadline is what is needed instead of the date
     */
    public static function movement(string $kind, bool $toPay): string
    {
        return match (true) {
            $kind === 'income' => Translated::text('To create the income the amount and the date are needed: enter them or untick the income.'),
            $toPay => Translated::text('To create the expense to pay the amount and the deadline are needed: enter them or untick the expense.'),
            default => Translated::text('To create the expense the amount and the date are needed: enter them or untick the expense.'),
        };
    }

    /**
     * A ticked health event without what it needs.
     */
    public static function event(): string
    {
        return Translated::text('To create the health event the person, the type, the date and the title are needed: enter them or untick the event.');
    }

    /**
     * A ticked card without its required fields.
     *
     * @param  string  $title  the card, as its box names it (`the policy 40/123456 · UnipolSai`)
     * @param  list<string>  $missing  the fields still empty, as words (`the type`)
     */
    public static function record(string $title, array $missing): string
    {
        return trans_choice(
            '{1} To create :title :fields is needed: enter it or untick it.|[2,*] To create :title :fields are needed: enter them or untick it.',
            count($missing),
            ['title' => $title, 'fields' => implode(', ', $missing)],
        );
    }

    /**
     * A movement, event or card picked by hand that is no longer there, or is not of the family.
     *
     * @param  string  $what  `expense`, `income`, `event` or `card`
     */
    public static function gone(string $what): string
    {
        return match ($what) {
            'expense' => Translated::text('The chosen expense no longer exists: choose another one.'),
            'income' => Translated::text('The chosen income no longer exists: choose another one.'),
            'event' => Translated::text('The chosen event no longer exists: choose another one.'),
            default => Translated::text('The chosen card no longer exists: choose another one.'),
        };
    }
}

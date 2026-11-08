<?php

declare(strict_types=1);

namespace App\Support\Mail;

use Illuminate\Database\QueryException;
use Throwable;

/**
 * What can be kept of an error met while a mail is saved: the log of the calls and the log of the app never hold
 * the text of the mail. The message of a failed query has the SQL with every value of the insert (the body of the
 * mail, its subject, the note of the files), so of a query only the SQLSTATE and the words of the database are kept.
 */
final class SafeError
{
    /** The longest description. */
    public const int MAX_CHARS = 300;

    /**
     * The class of the error and what it says, without the SQL and its values, at most MAX_CHARS characters.
     *
     * @param  Throwable  $error  the error
     */
    public static function describe(Throwable $error): string
    {
        $text = $error instanceof QueryException ? self::ofQuery($error) : $error->getMessage();

        return mb_substr(class_basename($error).': '.$text, 0, self::MAX_CHARS);
    }

    /**
     * A failed query as the database tells it: "SQLSTATE[22001] Data too long for column 'subject' at row 1".
     *
     * @param  QueryException  $error  the failed query
     */
    private static function ofQuery(QueryException $error): string
    {
        $info = is_array($error->errorInfo) ? $error->errorInfo : [];
        $state = is_string($info[0] ?? null) ? $info[0] : (string) $error->getCode();
        $words = is_string($info[2] ?? null) ? $info[2] : '';

        return trim("SQLSTATE[{$state}] {$words}");
    }
}

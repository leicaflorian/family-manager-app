<?php

declare(strict_types=1);

namespace App\Exceptions\Mail;

use App\Support\Mail\SafeError;
use RuntimeException;
use Throwable;

/**
 * A failure while a mail of Mailgun is saved or logged, as it is reported: only the safe description of the error
 * (`SafeError`), and not the error itself, whose query would carry the text of the mail into the log of the app.
 */
final class MailFailure extends RuntimeException
{
    /**
     * The failure to report in place of an error.
     *
     * @param  Throwable  $error  the error, which is not kept
     */
    public static function from(Throwable $error): self
    {
        return new self(SafeError::describe($error));
    }
}

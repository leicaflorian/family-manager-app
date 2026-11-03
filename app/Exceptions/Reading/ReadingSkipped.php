<?php

declare(strict_types=1);

namespace App\Exceptions\Reading;

use RuntimeException;

/**
 * A file the model is not asked to read: the person fills the form in by hand. The message is shown to the person.
 */
final class ReadingSkipped extends RuntimeException
{
    /**
     * @param  string  $message  what to show, as an English key of `lang/it.json`
     */
    public function __construct(string $message)
    {
        parent::__construct(__($message));
    }

    /**
     * The file is over the size the service takes.
     */
    public static function tooBig(): self
    {
        return new self('The file is too big to be read: fill it in yourself.');
    }

    /**
     * The format is not one the model reads.
     */
    public static function unsupported(): self
    {
        return new self('This kind of file cannot be read.');
    }

    /**
     * A text file over the size of a note.
     */
    public static function textTooBig(): self
    {
        return new self('The text is too big (at most 100 KB).');
    }
}

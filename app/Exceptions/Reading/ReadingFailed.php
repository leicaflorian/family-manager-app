<?php

declare(strict_types=1);

namespace App\Exceptions\Reading;

use RuntimeException;

/**
 * A reading that did not give an extraction. The message is shown to the person (English key, translated),
 * `retry` says whether trying again can work: true only for the failures that pass by themselves.
 */
final class ReadingFailed extends RuntimeException
{
    /**
     * @param  string  $message  what to show, as an English key of `lang/it.json`
     * @param  bool  $retry  true when the failure may pass (overload, network), false when trying again changes nothing
     * @param  \Throwable|null  $previous  the error underneath
     */
    public function __construct(string $message, public readonly bool $retry = false, ?\Throwable $previous = null)
    {
        parent::__construct(__($message), 0, $previous);
    }

    /**
     * The model answered nothing.
     */
    public static function emptyAnswer(): self
    {
        return new self('The answer of the model is empty.');
    }

    /**
     * The model answered something that is not the JSON of the schema.
     */
    public static function invalidAnswer(?\Throwable $previous = null): self
    {
        return new self('The answer of the model is not valid.', previous: $previous);
    }
}

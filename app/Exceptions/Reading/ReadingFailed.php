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

    /**
     * The model refused to read the document: asking again gets the same answer.
     */
    public static function refused(): self
    {
        return new self('The model refused to read the document.');
    }

    /**
     * The answer of the model ended before the JSON did.
     */
    public static function cutShort(): self
    {
        return new self('The answer of the model was cut short.');
    }

    /**
     * The service is busy (too many requests, overloaded): it passes by itself.
     */
    public static function busy(\Throwable $previous): self
    {
        return new self('The AI service is busy, try again in a while.', retry: true, previous: $previous);
    }

    /**
     * The service could not be reached: the network may come back.
     */
    public static function unreachable(\Throwable $previous): self
    {
        return new self('The AI service cannot be reached, try again in a while.', retry: true, previous: $previous);
    }

    /**
     * The service did not accept the key (401, 403).
     */
    public static function invalidKey(\Throwable $previous): self
    {
        return new self('The key of the AI service is not valid.', previous: $previous);
    }

    /**
     * The service only reads photos (Ollama), and the file is a PDF.
     */
    public static function pdfNotSupported(): self
    {
        return new self('This AI service cannot read PDF files.');
    }

    /**
     * The service answered with any other error.
     */
    public static function serviceError(\Throwable $previous): self
    {
        return new self('The AI service answered with an error.', previous: $previous);
    }
}

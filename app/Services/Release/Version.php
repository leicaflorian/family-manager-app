<?php

declare(strict_types=1);

namespace App\Services\Release;

use App\Enums\ReleaseBump;
use App\Support\AppVersion;
use InvalidArgumentException;

/**
 * A version number `X.Y.Z` of the app.
 */
final readonly class Version implements \Stringable
{
    /**
     * The version of the first release.
     */
    public const string FIRST = '2.0.0';

    /**
     * @param  int  $major  First number.
     * @param  int  $minor  Middle number.
     * @param  int  $patch  Last number.
     */
    private function __construct(
        public int $major,
        public int $minor,
        public int $patch,
    ) {}

    /**
     * Reads a version written as `X.Y.Z`.
     *
     * @param  string  $value  The text to read.
     *
     * @throws InvalidArgumentException When the text is not `X.Y.Z`.
     */
    public static function parse(string $value): self
    {
        if (preg_match(AppVersion::PATTERN, $value) !== 1) {
            throw new InvalidArgumentException(sprintf('"%s" is not a version like 1.2.3.', $value));
        }

        [$major, $minor, $patch] = array_map(intval(...), explode('.', $value));

        return new self($major, $minor, $patch);
    }

    /**
     * The version after this one: the raised number grows and the ones after it go back to zero.
     *
     * @param  ReleaseBump  $bump  Which number to raise.
     */
    public function bump(ReleaseBump $bump): self
    {
        return match ($bump) {
            ReleaseBump::Major => new self($this->major + 1, 0, 0),
            ReleaseBump::Minor => new self($this->major, $this->minor + 1, 0),
            ReleaseBump::Patch => new self($this->major, $this->minor, $this->patch + 1),
        };
    }

    /**
     * The version as `X.Y.Z`.
     */
    public function __toString(): string
    {
        return sprintf('%d.%d.%d', $this->major, $this->minor, $this->patch);
    }
}

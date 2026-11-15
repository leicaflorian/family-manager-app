<?php

declare(strict_types=1);

namespace App\Services\Release;

use RuntimeException;

/**
 * Claude's answer for a release does not have the shape the schema asks for; the message names the wrong field.
 */
final class InvalidReleaseResponse extends RuntimeException {}

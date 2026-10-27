<?php

declare(strict_types=1);

namespace App\Copy;

use RuntimeException;

/**
 * The copy from Directus does not start: the family is already in the app, or
 * an account would take an email that another family has.
 */
final class CopyRefused extends RuntimeException {}

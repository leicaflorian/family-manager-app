<?php

declare(strict_types=1);

namespace App\Exceptions\Banks;

/**
 * The bank has no reads left for the day: try again later.
 */
final class BankLimitReached extends BankFeedFailed {}

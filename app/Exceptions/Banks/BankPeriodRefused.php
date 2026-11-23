<?php

declare(strict_types=1);

namespace App\Exceptions\Banks;

/**
 * The bank does not give the transactions of the period asked: it keeps a shorter history, or counts the days in its
 * own way.
 */
final class BankPeriodRefused extends BankFeedFailed {}

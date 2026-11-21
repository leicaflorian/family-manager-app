<?php

declare(strict_types=1);

namespace App\Exceptions\Banks;

/**
 * The permission the person gave the bank is over: expired, closed or taken back.
 */
final class BankConsentEnded extends BankFeedFailed {}

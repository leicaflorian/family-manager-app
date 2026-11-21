<?php

declare(strict_types=1);

namespace App\Exceptions\Banks;

use RuntimeException;

/**
 * The provider of the banks did not give what was asked: network, an error of the bank, a bad answer.
 */
class BankFeedFailed extends RuntimeException {}

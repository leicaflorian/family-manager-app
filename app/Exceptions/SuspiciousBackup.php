<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by the file backup when the local files look gone (an unmounted volume, an emptied folder), so that nothing
 * is moved and the good copy on the Space stays as it is.
 */
final class SuspiciousBackup extends RuntimeException {}

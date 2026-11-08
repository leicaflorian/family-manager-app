<?php

declare(strict_types=1);

namespace App\Services\Mailgun;

use RuntimeException;

/**
 * A call to the routes of Mailgun that did not work. The message is meant to be shown as it is: it never has the
 * API key in it.
 */
final class MailgunRouteException extends RuntimeException {}

<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Mail\ReceiveMail;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The door of Mailgun: the route of the domain for the bills forwards here every mail, signed with the signing key.
 */
final class MailgunWebhookController
{
    /**
     * Receives one mail.
     *
     * @param  Request  $request  the call of Mailgun
     * @param  ReceiveMail  $receive  what is done with it
     */
    public function __invoke(Request $request, ReceiveMail $receive): Response
    {
        return $receive->handle($request);
    }
}

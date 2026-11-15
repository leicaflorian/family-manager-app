<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Bridge\Mailgun\Transport\MailgunHttpTransport;

test('the mailgun mailer uses the api on the eu host', function (): void {
    config([
        'mail.mailers.mailgun.domain' => 'mg.fameja.app',
        'mail.mailers.mailgun.secret' => 'key-test',
        'mail.mailers.mailgun.endpoint' => parse_url('https://api.eu.mailgun.net', PHP_URL_HOST),
    ]);

    $transport = Mail::mailer('mailgun')->getSymfonyTransport();

    expect($transport)->toBeInstanceOf(MailgunHttpTransport::class)
        ->and((string) $transport)->toContain('api.eu.mailgun.net')
        ->and((string) $transport)->toContain('mg.fameja.app');
});

test('the mailgun mailer is configured from the environment, with the host of the endpoint', function (): void {
    expect(config('mail.mailers.mailgun.transport'))->toBe('mailgun')
        ->and(config('mail.mailers.mailgun.endpoint'))->toBe('api.eu.mailgun.net');
});

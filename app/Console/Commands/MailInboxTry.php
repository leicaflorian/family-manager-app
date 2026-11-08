<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Mail\ReceiveMail;
use App\Services\Mailgun\MailgunRequest;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Delivers a mail to an address of the domain for the bills without Mailgun, only on a local machine: it makes a call
 * signed like the ones of Mailgun and hands it to `ReceiveMail` in this process, with no HTTP.
 */
#[Description('Deliver a mail to an address for the bills, as Mailgun would (local machines only)')]
#[Signature('mail-inbox:try {address : The address the mail is delivered to} {--subject= : The subject of the mail} {--file=* : A file to attach (repeat the option for more)}')]
final class MailInboxTry extends Command
{
    /** Who the tried mail comes from. */
    private const string SENDER = 'Prova <prova@example.test>';

    /**
     * Makes the call and prints how the app answered. Without a signing key in the configuration, a key made for
     * this call alone signs it and is taken away after: nothing stays configured.
     *
     * @param  ReceiveMail  $receive  The action that answers the calls of Mailgun.
     * @return int 0 when the app answered 200; 1 when the command stopped or the answer was another
     */
    public function handle(ReceiveMail $receive): int
    {
        if (! app()->isLocal()) {
            $this->error('mail-inbox:try runs only on a local machine.');

            return self::FAILURE;
        }

        $files = [];

        foreach ((array) $this->option('file') as $path) {
            if (! is_string($path) || ! is_file($path)) {
                $this->error('File not found: '.(is_string($path) ? $path : ''));

                return self::FAILURE;
            }

            $files[] = ['name' => basename($path), 'path' => $path, 'mime' => (string) mime_content_type($path)];
        }

        $configured = config('services.mailgun.signing_key');
        $key = is_string($configured) && $configured !== '' ? $configured : Str::random(40);
        config(['services.mailgun.signing_key' => $key]);

        try {
            $subject = $this->option('subject');
            $request = MailgunRequest::make(
                (string) $this->argument('address'),
                self::SENDER,
                is_string($subject) ? $subject : 'Mail tried from the command line',
                'A mail tried with mail-inbox:try.',
                $files,
                $key,
            );
            $response = $receive->handle($request);
        } finally {
            config(['services.mailgun.signing_key' => $configured]);
        }

        $this->line($response->getStatusCode().' '.$this->answer($response));

        return $response->getStatusCode() === Response::HTTP_OK ? self::SUCCESS : self::FAILURE;
    }

    /**
     * What the app answered, which is a short message or the counts of the files, never the text of the mail.
     *
     * @param  Response  $response  the answer
     */
    private function answer(Response $response): string
    {
        $body = json_decode((string) $response->getContent(), true);

        if (! is_array($body)) {
            return '';
        }

        return collect($body)->map(static fn (mixed $value, string|int $name): string => is_scalar($value) ? "{$name}: {$value}" : '')->filter()->implode(', ');
    }
}

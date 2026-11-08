<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Http\Testing\File as FakeFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Pest\TestSuite;
use RuntimeException;
use Tests\TestCase;

/**
 * A call of Mailgun to `POST mail/mailgun`, as a route of Mailgun makes it: a multipart form with the fields of the
 * mail, the files as `attachment-N` and the signature made with the signing key of the tests
 * (`services.mailgun.signing_key`, from `phpunit.xml`).
 *
 * The same object sent twice is the same mail delivered twice: the `Message-Id` is chosen once, when it is made.
 */
final class MailgunCall
{
    /** The fields that are not the signature. */
    private string $from = 'Enel Energia <bollette@enel.it>';

    private string $subject = 'La tua bolletta di ottobre';

    private string $text = '';

    private string $html = '';

    /** The `To` header, the recipient when null. */
    private ?string $toHeader = null;

    /** @var list<array{name: string, contents: string, mime: string, error: int}> */
    private array $attachments = [];

    private bool $signed = true;

    /** The moment of the signature, now when null. */
    private ?int $timestamp = null;

    /** The key the call is signed with, the one of the tests when null. */
    private ?string $key = null;

    private ?int $contentLength = null;

    private string $contentType = 'multipart/form-data; boundary=----mailgun';

    private readonly string $messageId;

    /**
     * @param  string  $recipient  the address the mail is delivered to
     */
    private function __construct(private readonly string $recipient)
    {
        $this->messageId = '<'.Str::random(20).'@mail.example>';
    }

    /**
     * A call that delivers a mail to an address.
     *
     * @param  string  $recipient  the field `recipient`; empty leaves it out
     */
    public static function to(string $recipient): self
    {
        return new self($recipient);
    }

    /**
     * Who wrote the mail.
     */
    public function from(string $from): self
    {
        $this->from = $from;

        return $this;
    }

    /**
     * The subject of the mail.
     */
    public function subject(string $subject): self
    {
        $this->subject = $subject;

        return $this;
    }

    /**
     * The `To` header of the mail, which is not the address it was delivered to.
     */
    public function toHeader(string $to): self
    {
        $this->toHeader = $to;

        return $this;
    }

    /**
     * One more file attached to the mail.
     *
     * @param  string  $name  the name the sender gave it
     * @param  string  $contents  its bytes
     * @param  string  $mime  the type the sender declared
     */
    public function attach(string $name, string $contents, string $mime): self
    {
        $this->attachments[] = ['name' => $name, 'contents' => $contents, 'mime' => $mime, 'error' => UPLOAD_ERR_OK];

        return $this;
    }

    /**
     * One more file attached to the mail, whose upload PHP did not finish: nothing of it arrives.
     *
     * @param  string  $name  the name the sender gave it
     * @param  int  $error  the error of PHP (`UPLOAD_ERR_INI_SIZE`, `UPLOAD_ERR_NO_TMP_DIR`…)
     */
    public function attachBroken(string $name, int $error): self
    {
        $this->attachments[] = ['name' => $name, 'contents' => '', 'mime' => 'application/pdf', 'error' => $error];

        return $this;
    }

    /**
     * The text part of the mail.
     */
    public function text(string $text): self
    {
        $this->text = $text;

        return $this;
    }

    /**
     * The HTML part of the mail.
     */
    public function html(string $html): self
    {
        $this->html = $html;

        return $this;
    }

    /**
     * Without the fields of the signature.
     */
    public function unsigned(): self
    {
        $this->signed = false;

        return $this;
    }

    /**
     * Signed at a moment other than now.
     *
     * @param  int  $timestamp  the seconds of the moment
     */
    public function signedAt(int $timestamp): self
    {
        $this->timestamp = $timestamp;

        return $this;
    }

    /**
     * Signed with another key.
     */
    public function signedWith(string $key): self
    {
        $this->key = $key;

        return $this;
    }

    /**
     * The `Content-Length` the call declares, whatever its body is.
     */
    public function contentLength(int $bytes): self
    {
        $this->contentLength = $bytes;

        return $this;
    }

    /**
     * The `Content-Type` of the body.
     */
    public function contentType(string $type): self
    {
        $this->contentType = $type;

        return $this;
    }

    /**
     * Sends the call from the running test.
     *
     * @throws RuntimeException outside a test
     */
    public function send(): TestResponse
    {
        $test = TestSuite::getInstance()->test;

        if (! $test instanceof TestCase) {
            throw new RuntimeException('A Mailgun call is sent from a test.');
        }

        $server = ['CONTENT_TYPE' => $this->contentType, 'HTTP_ACCEPT' => 'application/json'];

        if ($this->contentLength !== null) {
            $server['CONTENT_LENGTH'] = (string) $this->contentLength;
        }

        return $test->call('POST', '/mail/mailgun', $this->fields(), [], $this->files(), $server);
    }

    /**
     * The fields of the form.
     *
     * @return array<string, string>
     */
    private function fields(): array
    {
        $headers = [
            ['Message-Id', $this->messageId],
            ['From', $this->from],
            ['To', $this->toHeader ?? $this->recipient],
            ['Subject', $this->subject],
            ['Date', 'Sun, 25 Oct 2026 08:40:00 +0100'],
        ];

        $fields = [
            'recipient' => $this->recipient,
            'sender' => $this->from,
            'from' => $this->from,
            'subject' => $this->subject,
            'body-plain' => $this->text,
            'body-html' => $this->html,
            'message-headers' => (string) json_encode($headers),
            'attachment-count' => (string) count($this->attachments),
        ];

        if ($this->recipient === '') {
            unset($fields['recipient']);
        }

        if ($this->signed) {
            $timestamp = (string) ($this->timestamp ?? now()->getTimestamp());
            $token = Str::random(50);
            $key = $this->key ?? config()->string('services.mailgun.signing_key');
            $fields += ['timestamp' => $timestamp, 'token' => $token, 'signature' => hash_hmac('sha256', $timestamp.$token, $key)];
        }

        return $fields;
    }

    /**
     * The files of the form, as `attachment-1`, `attachment-2`…
     *
     * @return array<string, UploadedFile>
     */
    private function files(): array
    {
        $files = [];

        foreach ($this->attachments as $index => $attachment) {
            $files['attachment-'.($index + 1)] = $attachment['error'] === UPLOAD_ERR_OK
                ? FakeFile::createWithContent($attachment['name'], $attachment['contents'])->mimeType($attachment['mime'])
                : new UploadedFile('', $attachment['name'], $attachment['mime'], $attachment['error'], true);
        }

        return $files;
    }
}

<?php

declare(strict_types=1);

use App\Enums\FeedbackKind;
use App\Mail\FeedbackReportMail;
use App\Models\FeedbackReport;
use App\Models\Owner;
use App\Models\ServerError;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Mail::fake();
    $this->admin = registerFamily();
    $this->member = User::factory()->for($this->admin->family)->create(['name' => 'Giulia Bianchi', 'email' => 'giulia@example.com']);
    $this->owners = Owner::factory()->count(2)->create();
});

/**
 * The fields of a report as the panel sends them.
 *
 * @param  array<string, mixed>  $fields  the fields to change
 * @return array<string, mixed>
 */
function reportFields(array $fields = []): array
{
    return [
        'kind' => 'problem',
        'message' => "L'importo letto è sbagliato.",
        'page' => '/documents/12?tab=files',
        'layout' => 'phone',
        ...$fields,
    ];
}

/**
 * The reports of every family, outside the scope of the family.
 *
 * @return list<FeedbackReport>
 */
function allReports(): array
{
    return FeedbackReport::query()->withoutGlobalScopes()->get()->all();
}

test('a member sends a problem with a screenshot: saved, emailed to the owner and confirmed', function (): void {
    $response = $this->actingAs($this->member)
        ->withHeader('User-Agent', 'TestPhone/1.0')
        ->from('/documents/12')
        ->post(route('feedback.store'), reportFields(['file' => UploadedFile::fake()->image('schermata.png')]));

    $response->assertRedirect('/documents/12')->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast.type', 'success')
        ->assertInertiaFlash('toast.message', 'Thanks, we will answer you by email.');
    expect(allReports())->toHaveCount(1);
    $report = allReports()[0];
    expect($report->family_id)->toBe($this->admin->family_id)
        ->and($report->created_by)->toBe($this->member->id)
        ->and($report->kind)->toBe(FeedbackKind::Problem)
        ->and($report->message)->toBe("L'importo letto è sbagliato.")
        ->and($report->page)->toBe('/documents/12?tab=files')
        ->and($report->user_agent)->toBe('TestPhone/1.0')
        ->and($report->layout)->toBe('phone')
        ->and($report->file_name)->toBe('schermata.png')
        ->and($report->file_mime)->toBe('image/png')
        ->and($report->file_size)->toBeGreaterThan(0)
        ->and($report->file_path)->toStartWith("feedback/{$this->admin->family_id}/");
    Storage::disk('local')->assertExists((string) $report->file_path);

    Mail::assertQueued(FeedbackReportMail::class, fn (FeedbackReportMail $mail): bool => $mail->report->is($report)
        && $mail->hasTo($this->owners[0]->email)
        && $mail->hasTo($this->owners[1]->email)
        && $mail->hasReplyTo('giulia@example.com')
        && count($mail->attachments()) === 1);
});

test('an idea without a file has no file and no attachment', function (): void {
    $this->actingAs($this->admin)->post(route('feedback.store'), reportFields(['kind' => 'idea', 'layout' => 'computer']))
        ->assertSessionHasNoErrors();

    $report = allReports()[0];
    expect($report->kind)->toBe(FeedbackKind::Idea)
        ->and($report->file_path)->toBeNull()
        ->and($report->file_name)->toBeNull();
    Mail::assertQueued(FeedbackReportMail::class, fn (FeedbackReportMail $mail): bool => $mail->attachments() === []);
});

test('the email tells the owner who wrote, from which family, the page and the message', function (): void {
    $this->actingAs($this->member)->post(route('feedback.store'), reportFields());

    $mail = new FeedbackReportMail(allReports()[0]);
    $mail->assertSeeInHtml('Giulia Bianchi')
        ->assertSeeInHtml('Bianchi')
        ->assertSeeInHtml('/documents/12?tab=files')
        ->assertSeeInHtml('importo letto è sbagliato', false);
});

test('the email shows the message as the person wrote it, without running links or images in it', function (): void {
    $this->actingAs($this->member)->post(route('feedback.store'), reportFields([
        'message' => "Guarda ![x](https://tracker.example/p.png) e [qui](https://evil.example)\nseconda riga <b>grassetto</b>",
    ]));

    $html = new FeedbackReportMail(allReports()[0])->render();

    expect($html)->not->toContain('<img src="https://tracker.example')
        ->and($html)->not->toContain('href="https://evil.example')
        ->and($html)->not->toContain('<b>grassetto</b>')
        ->and($html)->toContain('tracker.example/p.png')
        ->and($html)->toContain('<br')
        ->and($html)->toContain('&lt;b&gt;grassetto&lt;/b&gt;');
});

test('a report is refused without a message, with a wrong kind or a wrong file, and nothing leaves', function (array $fields, string $error): void {
    $this->actingAs($this->member)->post(route('feedback.store'), reportFields($fields))
        ->assertSessionHasErrors($error);

    expect(allReports())->toBe([]);
    Mail::assertNothingQueued();
})->with([
    'no message' => [['message' => ''], 'message'],
    'message too long' => [['message' => str_repeat('a', 5001)], 'message'],
    'wrong kind' => [['kind' => 'complaint'], 'kind'],
    'no page' => [['page' => ''], 'page'],
    'wrong layout' => [['layout' => 'watch'], 'layout'],
    'not an image or a PDF' => [['file' => UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload')], 'file'],
    'over 10 MB' => [['file' => UploadedFile::fake()->create('grande.pdf', 10241, 'application/pdf')], 'file'],
]);

test('a PDF is accepted', function (): void {
    $this->actingAs($this->member)->post(route('feedback.store'), reportFields(['file' => UploadedFile::fake()->create('bolletta.pdf', 200, 'application/pdf')]))
        ->assertSessionHasNoErrors();

    expect(allReports()[0]->file_mime)->toBe('application/pdf');
});

test('the eleventh report in an hour is refused', function (): void {
    foreach (range(1, 10) as $ignored) {
        $this->actingAs($this->member)->post(route('feedback.store'), reportFields())->assertSessionHasNoErrors();
    }

    $this->actingAs($this->member)->post(route('feedback.store'), reportFields())->assertTooManyRequests();
    expect(allReports())->toHaveCount(10);
});

test('a guest is sent to sign in', function (): void {
    $this->post(route('feedback.store'), reportFields())->assertRedirect(route('login'));
    expect(allReports())->toBe([]);
});

test('a problem saves the screenshot, the details and the server errors of the account', function (): void {
    $old = ServerError::factory()->create(['user_id' => $this->member->id, 'family_id' => $this->member->family_id, 'created_at' => now()->subMinutes(40), 'request_id' => 'req-old']);
    $recent = ServerError::factory()->create(['user_id' => $this->member->id, 'family_id' => $this->member->family_id, 'created_at' => now()->subMinutes(5)]);
    $other = User::factory()->create();
    ServerError::factory()->create(['user_id' => $other->id, 'family_id' => $other->family_id, 'created_at' => now()->subMinutes(5), 'request_id' => 'req-old']);

    $details = json_encode([
        'console' => [['at' => '2026-11-15T10:00:00+01:00', 'level' => 'error', 'message' => 'Boom', 'component' => 'Pay']],
        'requests' => [['at' => '2026-11-15T10:00:01+01:00', 'method' => 'POST', 'path' => '/pay', 'status' => 500, 'request_id' => 'req-old']],
    ], JSON_THROW_ON_ERROR);

    $this->actingAs($this->member)->post(route('feedback.store'), reportFields([
        'screenshot' => UploadedFile::fake()->image('page.webp'),
        'diagnostics' => $details,
    ]))->assertSessionHasNoErrors();

    $report = allReports()[0];
    expect($report->screenshot_path)->toStartWith('feedback/'.$this->admin->family_id.'/')
        ->and($report->screenshot_size)->toBeGreaterThan(0)
        ->and($report->diagnostics['console'][0]['message'])->toBe('Boom')
        ->and($report->diagnostics['server_version'])->toBe(config('app.version'))
        ->and(array_column($report->server_errors, 'request_id'))->toBe(['req-old', $recent->request_id]);
    Storage::disk('local')->assertExists($report->screenshot_path);
});

test('an idea keeps nothing technical', function (): void {
    $this->actingAs($this->member)->post(route('feedback.store'), reportFields([
        'kind' => 'idea',
        'screenshot' => UploadedFile::fake()->image('page.webp'),
        'diagnostics' => json_encode(['console' => []], JSON_THROW_ON_ERROR),
    ]))->assertSessionHasNoErrors();

    $report = allReports()[0];
    expect($report->screenshot_path)->toBeNull()
        ->and($report->screenshot_size)->toBeNull()
        ->and($report->diagnostics)->toBeNull()
        ->and($report->server_errors)->toBeNull()
        ->and(Storage::disk('local')->allFiles('feedback'))->toBe([]);
});

test('a problem without screenshot or details is still saved', function (): void {
    $this->actingAs($this->member)->post(route('feedback.store'), reportFields())->assertSessionHasNoErrors();

    $report = allReports()[0];
    expect($report->screenshot_path)->toBeNull()
        ->and($report->diagnostics)->toBeNull();
});

test('details that are not JSON, or too large, are left out and the report is still saved', function (string $details): void {
    $this->actingAs($this->member)->post(route('feedback.store'), reportFields(['diagnostics' => $details]))
        ->assertSessionHasNoErrors();

    $report = allReports()[0];
    expect($report->message)->toBe("L'importo letto è sbagliato.")
        ->and($report->diagnostics)->toBeNull();
    Mail::assertQueued(FeedbackReportMail::class);
})->with([
    'not JSON' => ['{nope'],
    'not an object' => ['"text"'],
    'over 256 KB' => [fn (): string => '{"x":"'.str_repeat('a', 262144).'"}'],
]);

test('details between 64 and 256 KB are saved cleaned', function (): void {
    $console = array_map(fn (int $i): array => ['at' => 'x', 'level' => 'error', 'message' => str_repeat('m', 2000), 'component' => null], range(1, 50));

    $this->actingAs($this->member)->post(route('feedback.store'), reportFields([
        'diagnostics' => json_encode(['console' => $console], JSON_THROW_ON_ERROR),
    ]))->assertSessionHasNoErrors();

    $report = allReports()[0];
    expect($report->diagnostics['console'])->toHaveCount(50)
        ->and(mb_strlen($report->diagnostics['console'][0]['message']))->toBe(500);
});

test('a screenshot that is not an image is left out and the report is still saved', function (): void {
    $this->actingAs($this->member)->post(route('feedback.store'), reportFields([
        'screenshot' => UploadedFile::fake()->create('page.pdf', 20, 'application/pdf'),
    ]))->assertSessionHasNoErrors();

    $report = allReports()[0];
    expect($report->screenshot_path)->toBeNull()
        ->and(Storage::disk('local')->allFiles('feedback'))->toBe([]);
});

test('a pixel ratio that overflows is dropped and the report is still saved', function (): void {
    $this->actingAs($this->member)->post(route('feedback.store'), reportFields([
        'diagnostics' => '{"viewport":{"dpr":1e400,"width":390}}',
    ]))->assertSessionHasNoErrors();

    $report = allReports()[0];
    expect($report->diagnostics['viewport']['dpr'])->toBeNull()
        ->and($report->diagnostics['viewport']['width'])->toBe(390);
});

test('a failure while copying the server errors does not lose the report', function (): void {
    DB::listen(function (QueryExecuted $query): void {
        // Only the read of the error rows: the insert of the report also names the column.
        if (str_starts_with($query->sql, 'select') && str_contains($query->sql, 'from `server_errors`')) {
            throw new RuntimeException('server errors are down');
        }
    });

    $this->actingAs($this->member)->post(route('feedback.store'), reportFields())->assertSessionHasNoErrors();

    $report = allReports()[0];
    expect($report->message)->toBe("L'importo letto è sbagliato.")
        ->and($report->server_errors)->toBeNull();
    Mail::assertQueued(FeedbackReportMail::class);
});

test('the email of a problem attaches the screenshot and counts the errors', function (): void {
    $report = FeedbackReport::factory()->withDiagnostics()->for($this->admin->family)->create([
        'file_path' => 'feedback/1/abc.png', 'file_name' => 'schermata.png', 'file_mime' => 'image/png',
        'screenshot_path' => 'feedback/1/shot.webp',
    ]);
    Storage::disk('local')->put('feedback/1/abc.png', 'png');
    Storage::disk('local')->put('feedback/1/shot.webp', 'img');

    $mail = new FeedbackReportMail($report);

    expect($mail->attachments())->toHaveCount(2);
    $mail->assertSeeInHtml('1 page error, 1 failed request, 1 server error — details in the panel', false);
    expect($mail->render())->not->toContain('<b>bad</b>');
});

test('the email of an idea has no count line', function (): void {
    $report = FeedbackReport::factory()->for($this->admin->family)->create(['kind' => FeedbackKind::Idea]);

    new FeedbackReportMail($report)->assertDontSeeInHtml('details in the panel');
});

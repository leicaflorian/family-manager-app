<?php

declare(strict_types=1);

use App\Enums\FeedbackKind;
use App\Mail\FeedbackReportMail;
use App\Models\FeedbackReport;
use App\Models\Owner;
use App\Models\User;
use Illuminate\Http\UploadedFile;
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

    Mail::assertQueued(FeedbackReportMail::class, function (FeedbackReportMail $mail) use ($report): bool {
        return $mail->report->is($report)
            && $mail->hasTo($this->owners[0]->email)
            && $mail->hasTo($this->owners[1]->email)
            && $mail->hasReplyTo('giulia@example.com')
            && count($mail->attachments()) === 1;
    });
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

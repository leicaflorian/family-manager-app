<?php

declare(strict_types=1);

use App\Enums\FeedbackKind;
use App\Filament\Resources\FeedbackReports\FeedbackReportResource;
use App\Filament\Resources\FeedbackReports\Pages\ListFeedbackReports;
use App\Filament\Resources\FeedbackReports\Pages\ViewFeedbackReport;
use App\Models\Family;
use App\Models\FeedbackReport;
use App\Models\Owner;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    Date::setTestNow('2026-11-15 10:30:00');
    $this->rossi = Family::factory()->create(['name' => 'Rossi']);
    $this->verdi = Family::factory()->create(['name' => 'Verdi']);
    $this->anna = User::factory()->for($this->rossi)->create(['name' => 'Anna Rossi', 'email' => 'anna@example.com']);
    $this->old = FeedbackReport::factory()->for($this->verdi)->create([
        'kind' => FeedbackKind::Idea, 'message' => 'Un tema verde', 'created_at' => now()->subDays(3),
    ]);
    $this->problem = FeedbackReport::factory()->for($this->rossi)->create([
        'kind' => FeedbackKind::Problem,
        'message' => "L'importo letto è sbagliato",
        'page' => '/documents/12',
        'user_agent' => 'TestPhone/1.0',
        'layout' => 'phone',
        'file_path' => 'feedback/1/abc.png',
        'file_name' => 'schermata.png',
        'file_mime' => 'image/png',
        'file_size' => 120,
        'created_by' => $this->anna->id,
    ]);
    Storage::disk('local')->put('feedback/1/abc.png', 'png');
    // Signed in after the data, or the reports would take the owner as their author
    actingAs(Owner::factory()->withAuthenticator()->create(), 'owner');
});

it('lists the reports of every family, the newest first', function (): void {
    Livewire::test(ListFeedbackReports::class)
        ->assertCanSeeTableRecords([$this->problem, $this->old], inOrder: true)
        ->assertTableColumnStateSet('created_at', '15/11/2026 10:30', $this->problem)
        ->assertTableColumnStateSet('family.name', 'Rossi', $this->problem)
        ->assertTableColumnStateSet('creator.name', 'Anna Rossi', $this->problem)
        ->assertTableColumnStateSet('kind', 'Something does not work', $this->problem)
        ->assertTableColumnStateSet('kind', 'An idea', $this->old);
});

it('shows a report with its page, device and message', function (): void {
    Livewire::test(ViewFeedbackReport::class, ['record' => $this->problem->getKey()])
        ->assertSee("L'importo letto è sbagliato")
        ->assertSee('/documents/12')
        ->assertSee('TestPhone/1.0')
        ->assertSee('anna@example.com')
        ->assertSee('schermata.png');
});

it('downloads the file of a report, and offers no download without one', function (): void {
    Livewire::test(ViewFeedbackReport::class, ['record' => $this->problem->getKey()])
        ->callAction('download')
        ->assertFileDownloaded('schermata.png');

    Livewire::test(ViewFeedbackReport::class, ['record' => $this->old->getKey()])
        ->assertActionHidden('download');
});

it('creates, edits and deletes nothing', function (): void {
    expect(FeedbackReportResource::canCreate())->toBeFalse()
        ->and(FeedbackReportResource::canEdit($this->problem))->toBeFalse()
        ->and(FeedbackReportResource::canDelete($this->problem))->toBeFalse();
});

it('is not open to an account of a family', function (): void {
    auth('owner')->logout();

    $status = $this->actingAs($this->anna)->get(FeedbackReportResource::getUrl('index', panel: 'admin'))->status();

    expect($status)->toBeIn([302, 403]);
});

it('shows the screenshot, device, path, page errors, failed requests and server errors of a problem', function (): void {
    $report = FeedbackReport::factory()->withDiagnostics()->for($this->rossi)->createQuietly(['page' => '/documents/12', 'screenshot_path' => 'feedback/1/shot.webp']);
    Storage::disk('local')->put('feedback/1/shot.webp', 'img');
    config()->set('app.version', 'deployed-later');

    Livewire::test(ViewFeedbackReport::class, ['record' => $report->getKey()])
        ->assertSee(route('owner.feedback.file', [$report, 'screenshot']), false)
        ->assertSee('390 × 844')
        ->assertSee('Installed app')
        ->assertSee('/documents')
        ->assertSee('Boom')
        ->assertSee('Careful')
        ->assertSee('Page errors (1)')
        ->assertDontSee('<b>bad</b>', false)
        ->assertSee('POST /documents/42 · 500')
        ->assertSee('req-abc-123')
        ->assertSee('RuntimeException')
        ->assertDontSee('<i>x</i>', false)
        ->assertSee('build-old')
        ->assertSee('Different from the server');
});

it('shows a server error of a queued job without an empty method and path', function (): void {
    $report = FeedbackReport::factory()->withDiagnostics()->for($this->rossi)->createQuietly([
        'server_errors' => [[
            'at' => '2026-11-15T14:45:32+01:00', 'request_id' => 'req-job-1', 'method' => null, 'path' => null,
            'class' => 'RuntimeException', 'message' => 'late', 'file' => 'app/Jobs/ReadFile.php', 'line' => 4,
        ]],
    ]);

    Livewire::test(ViewFeedbackReport::class, ['record' => $report->getKey()])
        ->assertSee('14:45:32 · req-job-1')
        ->assertDontSee('·  ·');
});

it('shows a browser tab as Browser and no warning when the builds match', function (): void {
    $report = FeedbackReport::factory()->withDiagnostics()->for($this->rossi)->createQuietly();
    $details = $report->diagnostics;
    $details['standalone'] = false;
    $details['client_build'] = 'same';
    $details['server_version'] = 'same';
    $report->updateQuietly(['diagnostics' => $details]);
    // The live deploy differs, but the report was sent when the server ran the same build
    config()->set('app.version', 'newer');

    Livewire::test(ViewFeedbackReport::class, ['record' => $report->getKey()])
        ->assertSee('Browser')
        ->assertDontSee('Installed app')
        ->assertDontSee('Different from the server');
});

it('shows no technical sections for an idea', function (): void {
    Livewire::test(ViewFeedbackReport::class, ['record' => $this->old->getKey()])
        ->assertDontSee('Page errors')
        ->assertDontSee('Failed requests')
        ->assertDontSee('Server errors')
        ->assertDontSee('Screenshot');
});

it('lets the owner open the screenshot inline and the file, and a missing part is 404', function (): void {
    $report = FeedbackReport::factory()->withDiagnostics()->for($this->rossi)->createQuietly(['file_path' => 'feedback/1/abc.png', 'file_name' => 'schermata.png', 'file_mime' => 'image/png', 'screenshot_path' => 'feedback/1/shot.webp']);
    Storage::disk('local')->put('feedback/1/shot.webp', 'img');

    $screenshot = $this->get(route('owner.feedback.file', [$report, 'screenshot']))->assertOk();
    expect($screenshot->headers->get('Content-Disposition'))->toStartWith('inline');

    $file = $this->get(route('owner.feedback.file', [$report, 'file']))->assertOk();
    expect($file->headers->get('Content-Disposition'))->toContain('schermata.png');

    $this->get(route('owner.feedback.file', [$this->old, 'screenshot']))->assertNotFound();
    $this->get(route('owner.feedback.file', [$this->old, 'file']))->assertNotFound();
    $this->get(route('owner.feedback.file', [999999, 'file']))->assertNotFound();
    $this->get(route('owner.feedback.file', [$report, 'other']))->assertNotFound();
});

it('keeps the screenshot and the file from a family account and a guest', function (): void {
    $report = FeedbackReport::factory()->withDiagnostics()->for($this->rossi)->createQuietly();
    Storage::disk('local')->put('feedback/1/screenshot.webp', 'img');
    auth('owner')->logout();

    $this->get(route('owner.feedback.file', [$report, 'screenshot']))->assertRedirect(route('filament.admin.auth.login'));

    expect($this->actingAs($this->anna, 'web')->get(route('owner.feedback.file', [$report, 'screenshot']))->getStatusCode())->toBeIn([302, 403]);
});

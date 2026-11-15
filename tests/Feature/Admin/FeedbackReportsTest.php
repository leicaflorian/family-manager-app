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

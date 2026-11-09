<?php

declare(strict_types=1);

use App\Admin\ReadingReport;
use App\Enums\ReadingPurpose;
use App\Filament\Pages\AiReadingsPage;
use App\Models\AiReading;
use App\Models\Family;
use App\Models\Owner;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-10-25 19:04', 'Europe/Rome'));
    actingAs(Owner::factory()->withAuthenticator()->create(), 'owner');
    $this->bianchi = Family::factory()->create(['name' => 'Bianchi']);
    $this->rossi = Family::factory()->create(['name' => 'Rossi']);
});

/**
 * A reading of a family at a moment of Rome.
 *
 * @param  Family  $family  the family
 * @param  string  $at  the moment, in Rome
 * @param  array<string, mixed>  $attributes  columns to override
 */
function pageReading(Family $family, string $at, array $attributes = []): AiReading
{
    return AiReading::factory()->for($family)->create([
        'input_tokens' => 1000,
        'output_tokens' => 100,
        'cost_usd' => '0.002500',
        'created_at' => CarbonImmutable::parse($at, 'Europe/Rome')->setTimezone(config('app.timezone')),
        ...$attributes,
    ]);
}

it('shows the readings of the month by family, with the total', function (): void {
    pageReading($this->bianchi, '2026-10-03 10:00');
    pageReading($this->bianchi, '2026-10-12 10:00');
    pageReading($this->rossi, '2026-10-05 10:00');
    pageReading($this->rossi, '2026-09-30 10:00');

    Livewire::test(AiReadingsPage::class)
        ->assertSet('month', '2026-10')
        ->assertSeeInOrder(['Bianchi', '2', '2000', '200', '0,0050 $', 'Rossi', '1', '1000', '100', '0,0025 $', 'Total', '3', '3000', '300', '0,0075 $']);

    $rows = resolve(ReadingReport::class)->forMonth(CarbonImmutable::parse('2026-10-01', 'Europe/Rome'));
    expect($rows->pluck('label')->all())->toBe(['Bianchi', 'Rossi'])
        ->and(resolve(ReadingReport::class)->total($rows)->readings)->toBe(3);
});

it('moves to the previous and the next month', function (): void {
    pageReading($this->bianchi, '2026-09-10 10:00');

    Livewire::test(AiReadingsPage::class)
        ->assertDontSee('Bianchi')
        ->call('previousMonth')
        ->assertSet('month', '2026-09')
        ->assertSee('Bianchi')
        ->call('nextMonth')
        ->assertSet('month', '2026-10');
});

it('keeps the readings of a deleted family', function (): void {
    pageReading($this->rossi, '2026-10-05 10:00');
    $this->rossi->delete();

    expect(AiReading::query()->withoutGlobalScopes()->whereNull('family_id')->count())->toBe(1);

    Livewire::test(AiReadingsPage::class)->assertSee('Deleted family')->assertDontSee('Rossi');
});

it('does not count the cropping as a reading but counts its cost', function (): void {
    pageReading($this->bianchi, '2026-10-03 10:00');
    pageReading($this->bianchi, '2026-10-03 10:05', ['purpose' => ReadingPurpose::Crop]);

    $rows = resolve(ReadingReport::class)->forMonth(CarbonImmutable::parse('2026-10-01', 'Europe/Rome'));

    expect($rows->first()->readings)->toBe(1)
        ->and($rows->first()->calls)->toBe(2)
        ->and($rows->first()->cost)->toBe(0.005);
});

it('filters by provider and model', function (): void {
    pageReading($this->bianchi, '2026-10-03 10:00', ['provider' => 'gemini', 'model' => 'gemini-flash']);
    pageReading($this->rossi, '2026-10-04 10:00', ['provider' => 'anthropic', 'model' => 'claude-haiku']);

    Livewire::test(AiReadingsPage::class)
        ->assertSee('Bianchi')->assertSee('Rossi')
        ->set('provider', 'gemini')
        ->assertSee('Bianchi')->assertDontSee('Rossi')
        ->set('provider', '')
        ->set('model', 'claude-haiku')
        ->assertSee('Rossi')->assertDontSee('Bianchi');
});

it('shows the cost without a price apart', function (): void {
    pageReading($this->bianchi, '2026-10-03 10:00');
    pageReading($this->bianchi, '2026-10-04 10:00', ['cost_usd' => null]);

    Livewire::test(AiReadingsPage::class)->assertSee('0,0025 $ (1 without a price)');
});

it('says so when the month has no readings', function (): void {
    Livewire::test(AiReadingsPage::class)->assertSee('No readings this month.');
});

it('is closed to a visitor without the owner guard', function (): void {
    auth('owner')->logout();

    $this->get('/admin/ai-readings')->assertRedirect();
});

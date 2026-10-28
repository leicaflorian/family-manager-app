<?php

declare(strict_types=1);

use App\Enums\FinancialAccountType;
use App\Enums\MaintenanceType;
use App\Models\Document;
use App\Models\Family;
use App\Models\FinancialAccount;
use App\Models\HealthEvent;
use App\Models\MaintenanceRecord;
use App\Models\Organization;
use App\Models\Person;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\CurrentFamily;
use Database\Seeders\BianchiFamilySeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('local'));

test('the seeder creates the Bianchi family as on the canvas, in Italian', function (): void {
    $this->seed(BianchiFamilySeeder::class);

    insideBianchi(function (): void {
        $panda = Vehicle::query()->where('name', 'Fiat Panda')->sole();
        $document = Document::query()->where('title', 'Bolletta luce di ottobre')->sole();

        expect(Person::query()->count())->toBe(3)
            ->and(User::query()->where('family_id', CurrentFamily::id())->count())->toBe(2)
            ->and(Organization::query()->count())->toBe(15)
            ->and(Document::query()->count())->toBe(18)
            ->and($panda->documents()->pluck('title')->all())->toBe(['Polizza RC auto 2026'])
            ->and($document->category?->name)->toBe('Luce')
            ->and($document->organization?->name)->toBe('Edison')
            ->and($document->date?->format('d/m/Y'))->toBe('26/10/2026')
            ->and($document->linkables()->count())->toBe(1)
            ->and(Family::query()->sole()->isWelcomed())->toBeTrue();
        Storage::disk('local')->assertExists($document->file->path);
    });
});

test('run twice it duplicates nothing', function (): void {
    $this->seed(BianchiFamilySeeder::class);
    $this->seed(BianchiFamilySeeder::class);

    expect(Family::query()->count())->toBe(1)
        ->and(Document::withoutGlobalScopes()->count())->toBe(18);
});

test('the Bianchi accounts sign in with the test password', function (): void {
    $this->seed(BianchiFamilySeeder::class);

    $this->post(route('login.store'), ['email' => 'laura.bianchi@example.com', 'password' => 'password'])
        ->assertSessionHasNoErrors();

    $this->assertAuthenticatedAs(User::query()->where('email', 'laura.bianchi@example.com')->sole());
});

test('it creates the cards of the canvas', function (): void {
    $this->seed(BianchiFamilySeeder::class);

    insideBianchi(function (): void {
        $boiler = MaintenanceRecord::query()->where('title', 'Revisione caldaia')->sole();
        expect($boiler->date->toDateString())->toBe('2026-09-15')
            ->and($boiler->type)->toBe(MaintenanceType::BoilerService)
            ->and($boiler->home?->name)->toBe('Casa di via Roma')
            ->and($boiler->organization?->name)->toBe('Termoidraulica Rossi');

        $dentist = HealthEvent::query()->where('title', 'Dentista di Laura')->sole();
        expect($dentist->date->toDateString())->toBe('2026-10-08')
            ->and($dentist->next_checkup_date?->toDateString())->toBe('2027-04-08')
            ->and($dentist->organization?->name)->toBe('Studio Ferri')
            ->and($dentist->person->first_name)->toBe('Laura');

        $card = FinancialAccount::query()->where('name', 'Carta Laura')->sole();
        expect($card->type)->toBe(FinancialAccountType::Card)
            ->and($card->last_four_digits)->toBe('0937');

        $netflix = Subscription::query()->where('name', 'Netflix')->sole();
        expect($netflix->financial_account_id)->toBe($card->id)
            ->and($netflix->trial_end_date?->toDateString())->toBe('2026-11-04')
            ->and($netflix->activation_date?->toDateString())->toBe('2026-10-05')
            ->and($netflix->is_auto_renew)->toBeTrue();
    });
});

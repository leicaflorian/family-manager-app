<?php

declare(strict_types=1);

use App\Models\Document;
use App\Models\Family;
use App\Models\Organization;
use App\Models\Person;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\CurrentFamily;
use Database\Seeders\FamigliaBianchiSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('local'));

/**
 * Esegue un lavoro dentro la famiglia Bianchi.
 *
 * @template T
 *
 * @param  callable(): T  $lavoro
 * @return T
 */
function dentroBianchi(callable $lavoro): mixed
{
    return CurrentFamily::per(Family::query()->where('name', 'Bianchi')->sole()->id, $lavoro);
}

test('il seeder crea la famiglia Bianchi come sulla tela', function (): void {
    $this->seed(FamigliaBianchiSeeder::class);

    dentroBianchi(function (): void {
        $panda = Vehicle::query()->where('name', 'Fiat Panda')->sole();
        $documento = Document::query()->where('title', 'Bolletta luce di ottobre')->sole();

        expect(Person::query()->count())->toBe(3)
            ->and(User::query()->where('family_id', CurrentFamily::id())->count())->toBe(2)
            ->and(Organization::query()->count())->toBe(14)
            ->and(Document::query()->count())->toBe(18)
            ->and($panda->documents()->pluck('title')->all())->toBe(['Polizza RC auto 2026'])
            ->and($documento->category?->name)->toBe('Luce')
            ->and($documento->organization?->name)->toBe('Edison')
            ->and($documento->date?->format('d/m/Y'))->toBe('26/10/2026')
            ->and($documento->linkables()->count())->toBe(1);
        Storage::disk('local')->assertExists($documento->file->path);
    });
});

test('lanciato due volte non duplica niente', function (): void {
    $this->seed(FamigliaBianchiSeeder::class);
    $this->seed(FamigliaBianchiSeeder::class);

    expect(Family::query()->count())->toBe(1)
        ->and(Document::withoutGlobalScopes()->count())->toBe(18);
});

test('gli account dei Bianchi entrano con la password di prova', function (): void {
    $this->seed(FamigliaBianchiSeeder::class);

    $this->post(route('login.store'), ['email' => 'laura.bianchi@example.com', 'password' => 'password'])
        ->assertSessionHasNoErrors();

    $this->assertAuthenticatedAs(User::query()->where('email', 'laura.bianchi@example.com')->sole());
});

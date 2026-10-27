<?php

declare(strict_types=1);

use App\Models\Document;
use App\Models\Family;
use App\Models\Organization;
use App\Models\Person;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\CurrentFamily;
use Database\Seeders\BianchiFamilySeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('local'));

/**
 * Runs some work inside the Bianchi family.
 *
 * @template T
 *
 * @param  callable(): T  $work
 * @return T
 */
function insideBianchi(callable $work): mixed
{
    return CurrentFamily::using(Family::query()->where('name', 'Bianchi')->sole()->id, $work);
}

test('the seeder creates the Bianchi family as on the canvas, in Italian', function (): void {
    $this->seed(BianchiFamilySeeder::class);

    insideBianchi(function (): void {
        $panda = Vehicle::query()->where('name', 'Fiat Panda')->sole();
        $document = Document::query()->where('title', 'Bolletta luce di ottobre')->sole();

        expect(Person::query()->count())->toBe(3)
            ->and(User::query()->where('family_id', CurrentFamily::id())->count())->toBe(2)
            ->and(Organization::query()->count())->toBe(14)
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

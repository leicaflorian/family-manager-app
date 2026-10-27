<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\BianchiFamilySeeder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    Storage::fake('local');
});

test('the Home of the Bianchis shows the documents expiring, the most urgent first, and the last five uploaded', function (): void {
    Date::setTestNow('2026-10-26 18:30:00');
    $this->seed(BianchiFamilySeeder::class);
    $this->actingAs(User::query()->where('email', 'marco.bianchi@example.com')->sole());

    $this->get(route('dashboard'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->component('Home')
        ->where('firstName', 'Marco')
        ->where('today', '2026-10-26')
        ->where('hasDocuments', true)
        ->where('expiring', fn ($documents): bool => collect($documents)->map(fn (array $document): string => $document['title'].' '.$document['expiry_date'])->all() === [
            'Tessera sanitaria 2026-10-20',
            'Polizza RC auto 2026 2026-11-20',
            'Certificato medico sportivo 2026-12-11',
            'Scontrino TV Samsung 2026-12-20',
        ])
        ->has('recent', 5));
});

test('a document expiring in 60 days is there, one in 61 is not', function (): void {
    Date::setTestNow('2026-10-21 23:59:00');
    $this->seed(BianchiFamilySeeder::class);
    $this->actingAs(User::query()->where('email', 'marco.bianchi@example.com')->sole());

    // 21/10 + 60 days is 20/12: the TV receipt is in, the identity card (2027) is not.
    $this->get(route('dashboard'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('expiring.3.title', 'Scontrino TV Samsung')
        ->has('expiring', 4));

    Date::setTestNow('2026-10-20 00:01:00');
    $this->get(route('dashboard'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->has('expiring', 3));
});

test('without documents the Home invites to upload the first', function (): void {
    $this->actingAs(registerFamily());

    $this->get(route('dashboard'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->component('Home')
        ->where('hasDocuments', false)
        ->has('expiring', 0)
        ->has('recent', 0));
});

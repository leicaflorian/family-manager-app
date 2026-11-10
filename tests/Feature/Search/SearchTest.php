<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\BianchiFamilySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

/** The results of «Panda» in the Bianchi seeder, summed over the groups (read from the seeder). */
const PANDA_TOTAL = 39;

beforeEach(function (): void {
    Storage::fake('local');
    $this->travelTo('2026-10-27 10:00:00');
    $this->seed(BianchiFamilySeeder::class);
    $this->marco = User::query()->where('email', 'marco.bianchi@example.com')->sole();
    $this->laura = User::query()->where('email', 'laura.bianchi@example.com')->sole();
});

/**
 * The tables the groups read: a short search must not select from any of them.
 *
 * @return list<string>
 */
function searchedTables(): array
{
    return ['cards', 'documents', 'expenses', 'incomes', 'planned_expenses', 'planned_incomes', 'organizations', 'people', 'vehicles', 'homes', 'loans', 'subscriptions', 'utilities', 'insurance_policies'];
}

it('searches every group from the page and lists at most 20 per group', function (): void {
    $this->actingAs($this->marco)->get(route('search', ['q' => 'Panda']))->assertOk()->assertInertia(function (AssertableInertia $page): void {
        $page->component('Search')->where('results.query', 'Panda')->where('results.type', null);
        $results = $page->toArray()['props']['results'];
        $groups = $results['groups'];
        expect($groups)->not->toBeEmpty()
            ->and($results['total'])->toBe(array_sum(array_column($groups, 'count')))
            ->and($results['total'])->toBe(PANDA_TOTAL);
        $order = ['cards', 'documents', 'movements', 'dues', 'contacts'];
        $keys = array_column($groups, 'group');
        expect($keys)->toBe(array_values(array_intersect($order, $keys)));
        foreach ($groups as $group) {
            expect(count($group['results']))->toBeLessThanOrEqual(20)->and($group['count'])->toBeGreaterThan(0);
        }
    });
});

it('narrows the page to one group and still counts the others', function (): void {
    $this->actingAs($this->marco)->get(route('search', ['q' => 'polizza', 'type' => 'documents']))->assertInertia(function (AssertableInertia $page): void {
        $page->component('Search')->where('results.type', 'documents');
        $results = $page->toArray()['props']['results'];
        expect($results['total'])->toBe(array_sum(array_column($results['groups'], 'count')));
        foreach ($results['groups'] as $group) {
            if ($group['group'] === 'documents') {
                expect($group['results'])->not->toBeEmpty()->and(count($group['results']))->toBeLessThanOrEqual($group['count']);
            } else {
                expect($group['results'])->toBe([])->and($group['count'])->toBeGreaterThan(0);
            }
        }
        expect(array_column($results['groups'], 'group'))->toContain('documents');
    });
});

it('falls back to all the groups when the group asked for found nothing', function (): void {
    $this->actingAs($this->marco)->get(route('search', ['q' => 'Panda', 'type' => 'contacts']))->assertInertia(function (AssertableInertia $page): void {
        $results = $page->toArray()['props']['results'];
        expect($results['type'])->toBeNull()
            ->and($results['total'])->toBeGreaterThan(0)
            ->and(array_column($results['groups'], 'group'))->not->toContain('contacts');
        foreach ($results['groups'] as $group) {
            expect($group['results'])->not->toBeEmpty();
        }
    });
});

it('suggests at most 3 results per group as JSON with the total of all', function (): void {
    $response = $this->actingAs($this->marco)->getJson(route('search.suggest', ['q' => 'Pan']))->assertOk();

    $groups = $response->json('groups');
    expect($response->json('total'))->toBe(array_sum(array_column($groups, 'count')))->and($response->json('total'))->toBeGreaterThan(0);
    foreach ($groups as $group) {
        expect(count($group['results']))->toBeLessThanOrEqual(3);
    }
});

it('does not touch the data for a text that is too short', function (?string $q): void {
    DB::enableQueryLog();
    $response = $this->actingAs($this->marco)->getJson(route('search.suggest', $q === null ? [] : ['q' => $q]))->assertOk();
    $selects = collect(DB::getQueryLog())->pluck('query')->filter(fn (string $sql): bool => collect(searchedTables())->contains(fn (string $table): bool => str_contains($sql, "`{$table}`")));

    expect($response->json('total'))->toBe(0)->and($response->json('groups'))->toBe([])->and($selects->all())->toBe([]);
})->with([null, '', 'a', ' a ']);

it('refuses a text of 101 characters and an unknown type', function (): void {
    $this->actingAs($this->marco)->getJson(route('search', ['q' => str_repeat('a', 101)]))->assertUnprocessable();
    $this->actingAs($this->marco)->getJson(route('search', ['q' => 'Panda', 'type' => 'foo']))->assertUnprocessable();
});

it('finds nothing for a percent sign', function (): void {
    $this->actingAs($this->marco)->getJson(route('search.suggest', ['q' => '%']))->assertOk()->assertJsonPath('total', 0);
});

it('gives a member the same results as an admin', function (): void {
    $this->laura->forceFill(['role' => Role::Member])->save();

    $marco = $this->actingAs($this->marco)->getJson(route('search.suggest', ['q' => 'Panda']))->json();
    $laura = $this->actingAs($this->laura->refresh())->getJson(route('search.suggest', ['q' => 'Panda']))->json();

    expect($laura)->toBe($marco)->and($laura['total'])->toBe(PANDA_TOTAL);
});

it('sends someone who has not signed in to the login', function (): void {
    $this->get(route('search', ['q' => 'Panda']))->assertRedirect(route('login'));
    $this->getJson(route('search.suggest', ['q' => 'Panda']))->assertUnauthorized();
});

it('does not use a shared prop name for the results', function (): void {
    expect(file_get_contents(app_path('Http/Middleware/HandleInertiaRequests.php')))->not->toContain("'results' =>");
});

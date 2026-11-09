<?php

declare(strict_types=1);

use App\Models\Family;
use App\Models\LinkedDevice;
use App\Models\User;
use App\Services\AgentRelease;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;

const RELEASES_API = 'https://api.github.com/repos/leicaflorian/family-manager-agent/releases*';
const RELEASES_PAGE = 'https://github.com/leicaflorian/family-manager-agent/releases';

beforeEach(function (): void {
    Cache::flush();
    Http::preventStrayRequests();
    $this->family = Family::factory()->create();
    $this->laura = User::factory()->for($this->family)->admin()->create();
});

/**
 * Fakes GitHub: the list of releases (the newest first, a 2.0.0 among them) and the `version.json` of each.
 */
function fakeGithub(): void
{
    $versionJson = static fn (string $version): array => [
        'version' => $version,
        'built' => '2026-10-01',
        'files' => ['mac' => "FamilyManagerAgent-{$version}-mac.zip", 'windows' => "FamilyManagerAgent-{$version}-windows.zip"],
    ];

    Http::fake([
        RELEASES_API => Http::response([
            ['tag_name' => 'v2.0.0', 'draft' => false, 'prerelease' => false],
            ['tag_name' => 'v1.2.0', 'draft' => false, 'prerelease' => false],
            ['tag_name' => 'v1.3.0-beta', 'draft' => false, 'prerelease' => true],
            ['tag_name' => 'v1.1.0', 'draft' => false, 'prerelease' => false],
        ]),
        RELEASES_PAGE.'/download/v1.2.0/version.json' => Http::response($versionJson('1.2.0')),
        RELEASES_PAGE.'/download/v2.0.0/version.json' => Http::response($versionJson('2.0.0')),
    ]);
}

test('the page opens for the people of the family, not for a guest', function (): void {
    $this->get(route('scanner'))->assertRedirect(route('login'));

    $this->actingAs($this->laura)->get(route('scanner'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->component('scan/Scanner'));
});

test('the last 1.x release is told to the page once it is asked for, not the 2.0', function (): void {
    fakeGithub();

    $this->actingAs($this->laura)->get(route('scanner'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->missing('agent_release')
            ->loadDeferredProps(fn (AssertableInertia $page): AssertableInertia => $page
                ->where('agent_release.version', '1.2.0')
                ->where('agent_release.mac', true)
                ->where('agent_release.windows', true)));
});

test('the download of the agent goes to the zip of the last 1.x release', function (string $system): void {
    fakeGithub();

    $this->actingAs($this->laura)->get(route('scanner.agent', $system))
        ->assertRedirect(RELEASES_PAGE."/download/v1.2.0/FamilyManagerAgent-1.2.0-{$system}.zip");
})->with(['mac', 'windows']);

test('the version is asked to GitHub once an hour', function (): void {
    fakeGithub();

    $this->actingAs($this->laura)->get(route('scanner.agent', 'mac'))->assertRedirect();
    $this->actingAs($this->laura)->get(route('scanner.agent', 'windows'))->assertRedirect();

    Http::assertSentCount(2); // the list of releases and its version.json, one time

    $this->travel(61)->minutes();
    $this->actingAs($this->laura)->get(route('scanner.agent', 'mac'))->assertRedirect();

    Http::assertSentCount(4);
});

test('with GitHub not answering the download goes to the page of the releases, and it is asked again after a while', function (): void {
    Http::fake([
        RELEASES_API => Http::sequence()
            ->push('', 503)
            ->push([['tag_name' => 'v1.2.0', 'draft' => false, 'prerelease' => false]]),
        RELEASES_PAGE.'/download/v1.2.0/version.json' => Http::response(['version' => '1.2.0', 'files' => ['mac' => 'FamilyManagerAgent-1.2.0-mac.zip']]),
    ]);

    $this->actingAs($this->laura)->get(route('scanner.agent', 'mac'))->assertRedirect(RELEASES_PAGE);
    $this->actingAs($this->laura)->get(route('scanner.agent', 'mac'))->assertRedirect(RELEASES_PAGE);
    Http::assertSentCount(1);

    $this->travel(6)->minutes();
    $this->actingAs($this->laura)->get(route('scanner.agent', 'mac'))
        ->assertRedirect(RELEASES_PAGE.'/download/v1.2.0/FamilyManagerAgent-1.2.0-mac.zip');
});

test('with GitHub not answering after the hour, the version known before is still offered and asked again after a while', function (): void {
    Http::fake([
        RELEASES_API => Http::sequence()
            ->push([['tag_name' => 'v1.2.0', 'draft' => false, 'prerelease' => false]])
            ->push('', 503)
            ->push([['tag_name' => 'v1.3.0', 'draft' => false, 'prerelease' => false]]),
        RELEASES_PAGE.'/download/v1.2.0/version.json' => Http::response(['version' => '1.2.0', 'files' => ['mac' => 'FamilyManagerAgent-1.2.0-mac.zip']]),
        RELEASES_PAGE.'/download/v1.3.0/version.json' => Http::response(['version' => '1.3.0', 'files' => ['mac' => 'FamilyManagerAgent-1.3.0-mac.zip']]),
    ]);

    $this->actingAs($this->laura)->get(route('scanner.agent', 'mac'))
        ->assertRedirect(RELEASES_PAGE.'/download/v1.2.0/FamilyManagerAgent-1.2.0-mac.zip');

    $this->travel(61)->minutes();
    $this->actingAs($this->laura)->get(route('scanner.agent', 'mac'))
        ->assertRedirect(RELEASES_PAGE.'/download/v1.2.0/FamilyManagerAgent-1.2.0-mac.zip');
    $this->actingAs($this->laura)->get(route('scanner.agent', 'mac'))
        ->assertRedirect(RELEASES_PAGE.'/download/v1.2.0/FamilyManagerAgent-1.2.0-mac.zip');
    Http::assertSentCount(3); // the first list and its version.json, then one failed list

    $this->travel(6)->minutes();
    $this->actingAs($this->laura)->get(route('scanner.agent', 'mac'))
        ->assertRedirect(RELEASES_PAGE.'/download/v1.3.0/FamilyManagerAgent-1.3.0-mac.zip');
});

test('a version.json that is not what it should be is not believed', function (): void {
    Http::fake([
        RELEASES_API => Http::response([['tag_name' => 'v1.2.0', 'draft' => false, 'prerelease' => false]]),
        RELEASES_PAGE.'/download/v1.2.0/version.json' => Http::response(['version' => '1.2.0', 'files' => ['mac' => '../../evil.sh']]),
    ]);

    $this->actingAs($this->laura)->get(route('scanner.agent', 'mac'))->assertRedirect(RELEASES_PAGE);
});

test('a release without a zip for the system goes to the page of the releases', function (): void {
    Http::fake([
        RELEASES_API => Http::response([['tag_name' => 'v1.2.0', 'draft' => false, 'prerelease' => false]]),
        RELEASES_PAGE.'/download/v1.2.0/version.json' => Http::response(['version' => '1.2.0', 'files' => ['mac' => 'a-mac.zip']]),
    ]);

    $this->actingAs($this->laura)->get(route('scanner.agent', 'windows'))->assertRedirect(RELEASES_PAGE);
});

test('only mac and windows have an agent', function (): void {
    $this->actingAs($this->laura)->get('/scanner/agent/linux')->assertNotFound();
});

test('the lookup sends nothing but the two GET calls to GitHub', function (): void {
    fakeGithub();

    resolve(AgentRelease::class)->latest();

    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET');
    Http::assertNotSent(fn (Request $request): bool => $request->method() !== 'GET');
});

test('a linked phone cannot open the scanner or its download: it has the scan only', function (): void {
    $device = LinkedDevice::factory()->for($this->family)->for($this->laura)->create(['token_hash' => hash('sha256', 'x'), 'last_used_at' => now()]);

    $this->actingAs($this->laura)->withSession(['linked_device_id' => $device->id])->get(route('scanner'))->assertRedirect(route('scan'));
    $this->actingAs($this->laura)->withSession(['linked_device_id' => $device->id])->get(route('scanner.agent', 'mac'))->assertRedirect(route('scan'));
});

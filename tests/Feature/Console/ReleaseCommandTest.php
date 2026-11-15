<?php

declare(strict_types=1);

use App\Services\Release\ReleaseFiles;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->root = sys_get_temp_dir().'/release-command-'.bin2hex(random_bytes(6));
    mkdir($this->root.'/storage/app/release', 0755, true);
    app()->instance(ReleaseFiles::class, new ReleaseFiles($this->root));
    // The command takes the path from the repository root; absolute paths are accepted too.
    $this->response = $this->root.'/storage/app/release/response.json';
    $this->write = fn (string $bump = 'minor'): int|false => file_put_contents($this->response, json_encode([
        'bump' => $bump,
        'reason' => 'A new page arrives.',
        'changelog' => "### Agenda\n- Added the week view",
        'notes' => ['it' => ['Ora vedi la settimana.'], 'en' => ['You can now see the week.']],
    ], JSON_THROW_ON_ERROR));
});

afterEach(function (): void {
    File::deleteDirectory($this->root);
});

test('the suggested version is written everywhere', function (): void {
    file_put_contents($this->root.'/VERSION', "2.0.0\n");
    ($this->write)();

    $this->artisan('app:release', ['response' => $this->response])
        ->expectsOutputToContain('Added the week view')
        ->expectsOutputToContain('Ora vedi la settimana.')
        ->expectsOutputToContain('You can now see the week.')
        ->expectsOutputToContain('A new page arrives.')
        ->expectsChoice('Which version?', '2.1.0 (minor, suggested)', ['2.1.0 (minor, suggested)', '2.0.1 (patch)', '3.0.0 (major)', 'Cancel'])
        ->assertExitCode(0);

    expect(file_get_contents($this->root.'/VERSION'))->toBe("2.1.0\n")
        ->and(file_get_contents($this->root.'/storage/app/release/version'))->toBe('2.1.0')
        ->and(file_get_contents($this->root.'/storage/app/release/tag-message.md'))->toStartWith('## [2.1.0] - ')
        ->and(file_get_contents($this->root.'/CHANGELOG.md'))->toContain('## [2.1.0]')
        ->and(file_get_contents($this->root.'/resources/release/notes.json'))->toContain('"2.1.0"');
});

test('the owner can pick another version', function (): void {
    file_put_contents($this->root.'/VERSION', "2.0.0\n");
    ($this->write)();

    $this->artisan('app:release', ['response' => $this->response])
        ->expectsChoice('Which version?', '3.0.0 (major)', ['2.1.0 (minor, suggested)', '2.0.1 (patch)', '3.0.0 (major)', 'Cancel'])
        ->assertExitCode(0);

    expect(file_get_contents($this->root.'/VERSION'))->toBe("3.0.0\n")
        ->and(file_get_contents($this->root.'/storage/app/release/version'))->toBe('3.0.0');
});

test('a patch answer suggests the patch first', function (): void {
    file_put_contents($this->root.'/VERSION', "2.0.0\n");
    ($this->write)('patch');

    $this->artisan('app:release', ['response' => $this->response])
        ->expectsChoice('Which version?', '2.0.1 (patch, suggested)', ['2.0.1 (patch, suggested)', '2.1.0 (minor)', '3.0.0 (major)', 'Cancel'])
        ->assertExitCode(0);
});

test('cancel leaves the files as they were', function (): void {
    file_put_contents($this->root.'/VERSION', "2.0.0\n");
    ($this->write)();

    $this->artisan('app:release', ['response' => $this->response])
        ->expectsChoice('Which version?', 'Cancel', ['2.1.0 (minor, suggested)', '2.0.1 (patch)', '3.0.0 (major)', 'Cancel'])
        ->assertExitCode(1);

    expect(file_get_contents($this->root.'/VERSION'))->toBe("2.0.0\n")
        ->and(file_exists($this->root.'/storage/app/release/version'))->toBeFalse()
        ->and(file_exists($this->root.'/CHANGELOG.md'))->toBeFalse();
});

test('the first release asks only for a confirmation', function (): void {
    ($this->write)();

    $this->artisan('app:release', ['response' => $this->response])
        ->expectsConfirmation('Release 2.0.0?', 'yes')
        ->assertExitCode(0);

    expect(file_get_contents($this->root.'/VERSION'))->toBe("2.0.0\n")
        ->and(file_get_contents($this->root.'/storage/app/release/version'))->toBe('2.0.0');
});

test('declining the first release writes nothing', function (): void {
    ($this->write)();

    $this->artisan('app:release', ['response' => $this->response])
        ->expectsConfirmation('Release 2.0.0?', 'no')
        ->assertExitCode(1);

    expect(file_exists($this->root.'/VERSION'))->toBeFalse()
        ->and(file_exists($this->root.'/storage/app/release/version'))->toBeFalse();
});

test('an invalid answer exits 1 and names the field', function (): void {
    file_put_contents($this->root.'/VERSION', "2.0.0\n");
    file_put_contents($this->response, json_encode(['bump' => 'major']));

    $this->artisan('app:release', ['response' => $this->response])
        ->expectsOutputToContain('"bump"')
        ->assertExitCode(1);

    expect(file_get_contents($this->root.'/VERSION'))->toBe("2.0.0\n");
});

test('a missing answer file exits 1', function (): void {
    $this->artisan('app:release', ['response' => 'storage/app/release/nope.json'])
        ->expectsOutputToContain('nope.json')
        ->assertExitCode(1);
});

test('a relative path is read from the repository root', function (): void {
    file_put_contents($this->root.'/VERSION', "2.0.0\n");
    ($this->write)();
    $relative = 'storage/app/release/response-test-'.bin2hex(random_bytes(4)).'.json';
    @mkdir(dirname(base_path($relative)), 0755, true);
    copy($this->response, base_path($relative));

    try {
        $this->artisan('app:release', ['response' => $relative])
            ->expectsChoice('Which version?', 'Cancel', ['2.1.0 (minor, suggested)', '2.0.1 (patch)', '3.0.0 (major)', 'Cancel'])
            ->assertExitCode(1);
    } finally {
        File::delete(base_path($relative));
    }
});

test('a corrupt VERSION file stops the release and writes nothing', function (): void {
    file_put_contents($this->root.'/VERSION', "banana\n");
    ($this->write)();

    $this->artisan('app:release', ['response' => $this->response])
        ->expectsOutputToContain('VERSION')
        ->assertExitCode(1);

    expect(file_get_contents($this->root.'/VERSION'))->toBe("banana\n")
        ->and(file_exists($this->root.'/CHANGELOG.md'))->toBeFalse();
});

test('a real run, with the default root, fails on a missing file', function (): void {
    app()->forgetInstance(ReleaseFiles::class);

    $this->artisan('app:release', ['response' => 'storage/app/release/does-not-exist.json'])
        ->expectsOutputToContain('does not exist')
        ->assertExitCode(1);
});

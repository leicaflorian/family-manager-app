<?php

declare(strict_types=1);

use App\Enums\ReleaseBump;
use App\Services\Release\ReleaseFiles;
use App\Services\Release\ReleaseResponse;
use App\Services\Release\Version;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->root = sys_get_temp_dir().'/release-files-'.bin2hex(random_bytes(6));
    mkdir($this->root);
});

afterEach(function (): void {
    File::deleteDirectory($this->root);
});

/**
 * An answer with the given changelog and notes.
 */
function releaseFor(string $changelog, string $it, string $en): ReleaseResponse
{
    return ReleaseResponse::fromJson(json_encode([
        'bump' => 'minor',
        'reason' => 'Because.',
        'changelog' => $changelog,
        'notes' => ['it' => [$it], 'en' => [$en]],
    ], JSON_THROW_ON_ERROR));
}

test('there is no current version without the VERSION file', function (): void {
    expect(new ReleaseFiles($this->root)->current())->toBeNull();
});

test('the current version is read from the VERSION file', function (): void {
    file_put_contents($this->root.'/VERSION', "2.4.1\n");

    expect((string) new ReleaseFiles($this->root)->current())->toBe('2.4.1');
});

test('the section is the header, a blank line, the changelog and a newline', function (): void {
    $section = new ReleaseFiles($this->root)->section(
        Version::parse('2.0.0'),
        releaseFor("### Added\n- One", 'Uno', 'One'),
        CarbonImmutable::parse('2026-11-15'),
    );

    expect($section)->toBe("## [2.0.0] - 2026-11-15\n\n### Added\n- One\n");
});

test('the first write creates the three files', function (): void {
    $files = new ReleaseFiles($this->root);

    $files->write(Version::parse('2.0.0'), releaseFor('- One', 'È nuovo', 'New'), CarbonImmutable::parse('2026-11-15'));

    expect(file_get_contents($this->root.'/VERSION'))->toBe("2.0.0\n")
        ->and(file_get_contents($this->root.'/CHANGELOG.md'))->toStartWith('# Changelog')
        ->and(file_get_contents($this->root.'/CHANGELOG.md'))->toContain('## [2.0.0] - 2026-11-15');

    $raw = (string) file_get_contents($this->root.'/resources/release/notes.json');

    expect($raw)->toContain('È nuovo')->toEndWith("}\n]\n")
        ->and(json_decode($raw, true, flags: JSON_THROW_ON_ERROR))->toBe([
            ['version' => '2.0.0', 'date' => '2026-11-15', 'notes' => ['it' => ['È nuovo'], 'en' => ['New']]],
        ]);
});

test('a later write goes above the older one and keeps it', function (): void {
    $files = new ReleaseFiles($this->root);
    $files->write(Version::parse('2.0.0'), releaseFor('- One', 'Uno', 'One'), CarbonImmutable::parse('2026-11-15'));
    $files->write(Version::parse('2.0.0')->bump(ReleaseBump::Minor), releaseFor('- Two', 'Due', 'Two'), CarbonImmutable::parse('2026-11-20'));

    $changelog = (string) file_get_contents($this->root.'/CHANGELOG.md');
    $notes = json_decode((string) file_get_contents($this->root.'/resources/release/notes.json'), true, flags: JSON_THROW_ON_ERROR);

    expect(file_get_contents($this->root.'/VERSION'))->toBe("2.1.0\n")
        ->and(strpos($changelog, '## [2.1.0]'))->toBeLessThan(strpos($changelog, '## [2.0.0]'))
        ->and(substr_count($changelog, '# Changelog'))->toBe(1)
        ->and(array_column($notes, 'version'))->toBe(['2.1.0', '2.0.0']);
});

test('a corrupt VERSION file is an error, not a first release', function (): void {
    file_put_contents($this->root.'/VERSION', "banana\n");

    expect(fn (): ?Version => new ReleaseFiles($this->root)->current())->toThrow(InvalidArgumentException::class);
});

test('the handoff files live under storage/app/release', function (): void {
    expect(new ReleaseFiles($this->root)->handoffPath('version'))->toBe($this->root.'/storage/app/release/version');
});

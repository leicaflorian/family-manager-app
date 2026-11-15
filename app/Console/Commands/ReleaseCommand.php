<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ReleaseBump;
use App\Services\Release\InvalidReleaseResponse;
use App\Services\Release\ReleaseFiles;
use App\Services\Release\ReleaseResponse;
use App\Services\Release\Version;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use InvalidArgumentException;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;

/**
 * Reads Claude's answer for a release, shows it, lets the owner confirm or change the version and writes the release
 * files. A script on the Mac then commits and tags from the files in `storage/app/release/`.
 */
#[Description("Write the release files from Claude's answer and the version the owner picks")]
#[Signature('app:release {response : Path of the JSON answer of Claude, from the repository root}')]
final class ReleaseCommand extends Command
{
    private const string CANCEL = 'Cancel';

    /**
     * Shows the answer, asks the version and writes the files.
     *
     * @param  ReleaseFiles  $files  The release files of the repository.
     * @return int `SUCCESS` when the files are written, `FAILURE` when nothing is written
     */
    public function handle(ReleaseFiles $files): int
    {
        $path = (string) $this->argument('response');
        $absolute = str_starts_with($path, '/') ? $path : base_path($path);

        if (! is_file($absolute)) {
            $this->error(sprintf('The answer file %s does not exist.', $path));

            return self::FAILURE;
        }

        try {
            $response = ReleaseResponse::fromJson((string) file_get_contents($absolute));
            $current = $files->current();
        } catch (InvalidReleaseResponse $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } catch (InvalidArgumentException $e) {
            $this->error('The VERSION file is not a version like 1.2.3: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->show($response);
        $version = $current instanceof Version ? $this->pick($current, $response->bump) : $this->confirmFirst();

        if (! $version instanceof Version) {
            $this->warn('Release cancelled, nothing was written.');

            return self::FAILURE;
        }

        $date = CarbonImmutable::now('Europe/Rome');
        $files->write($version, $response, $date);
        $this->writeHandoff($files, $version, $files->section($version, $response, $date));
        $this->info(sprintf('Release %s written.', $version));

        return self::SUCCESS;
    }

    /**
     * Prints the changelog, the notes in both languages and the reason.
     *
     * @param  ReleaseResponse  $response  Claude's answer.
     */
    private function show(ReleaseResponse $response): void
    {
        $this->line('Changelog:');
        $this->line($response->changelog);
        $this->newLine();

        foreach (['it' => 'Italian', 'en' => 'English'] as $language => $label) {
            $this->line(sprintf('Notes (%s):', $label));

            foreach ($response->notes[$language] as $note) {
                $this->line('- '.$note);
            }

            $this->newLine();
        }

        $this->line('Why: '.$response->reason);
        $this->newLine();
    }

    /**
     * Asks to confirm the first release.
     *
     * @return Version|null The first version, or null when the owner declines
     */
    private function confirmFirst(): ?Version
    {
        $first = Version::parse(Version::FIRST);

        return confirm(sprintf('Release %s?', $first), true) ? $first : null;
    }

    /**
     * Asks which version to release, the one Claude suggests first.
     *
     * @param  Version  $current  The version now in the VERSION file.
     * @param  ReleaseBump  $suggested  The bump Claude chose.
     * @return Version|null The chosen version, or null when the owner cancels
     */
    private function pick(Version $current, ReleaseBump $suggested): ?Version
    {
        $order = [$suggested, ...array_filter(
            [ReleaseBump::Minor, ReleaseBump::Patch, ReleaseBump::Major],
            static fn (ReleaseBump $bump): bool => $bump !== $suggested,
        )];

        /** @var array<string, ReleaseBump> $options */
        $options = [];

        foreach ($order as $bump) {
            $suffix = $bump === $suggested ? $bump->value.', suggested' : $bump->value;
            $options[sprintf('%s (%s)', $current->bump($bump), $suffix)] = $bump;
        }

        $labels = [...array_keys($options), self::CANCEL];
        $chosen = select('Which version?', $labels, $labels[0]);

        $bump = $options[$chosen] ?? null;

        return $bump === null ? null : $current->bump($bump);
    }

    /**
     * Writes the two files the script reads: the version and the message of the tag.
     *
     * @param  ReleaseFiles  $files  The release files of the repository.
     * @param  Version  $version  The version of the release.
     * @param  string  $section  The changelog section, which is the tag message.
     */
    private function writeHandoff(ReleaseFiles $files, Version $version, string $section): void
    {
        $directory = dirname($files->handoffPath('version'));

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($files->handoffPath('version'), (string) $version);
        file_put_contents($files->handoffPath('tag-message.md'), $section);
    }
}

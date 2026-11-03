<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\FileSource;
use App\Models\File;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The page *To sort*: the files waiting for their document, newest first, filtered by where they came from, with
 * how many there are for each filter.
 */
#[TypeScript]
final class ToSortPageData extends Data
{
    /**
     * @param  list<PendingFileData>  $files  the files of the filter chosen, the newest first
     * @param  FileSource|null  $source  the filter chosen, null for *All*
     * @param  array{all: int, mail: int, phone: int, upload: int}  $counts  how many files each filter has, whatever the one chosen
     */
    public function __construct(
        public readonly array $files,
        public readonly ?FileSource $source,
        public readonly array $counts,
    ) {}

    /**
     * The page for a filter.
     *
     * @param  FileSource|null  $source  where the files shown came from, null for all of them
     */
    public static function for(?FileSource $source): self
    {
        $files = File::query()->toSort()->with('document')
            ->when($source instanceof FileSource, static fn ($query) => $query->where('source', $source))
            ->latest('id')->get();

        return new self(array_values(PendingFileData::fromFiles($files)), $source, self::counts());
    }

    /**
     * How many files wait for each filter, in one query.
     *
     * @return array{all: int, mail: int, phone: int, upload: int}
     */
    private static function counts(): array
    {
        /** @var array<string, int> $perSource */
        $perSource = File::query()->toSort()->selectRaw('source, count(*) as total')->groupBy('source')
            ->pluck('total', 'source')->map(static fn (mixed $total): int => is_numeric($total) ? (int) $total : 0)->all();

        return [
            'all' => array_sum($perSource),
            'mail' => $perSource[FileSource::Mail->value] ?? 0,
            'phone' => $perSource[FileSource::Phone->value] ?? 0,
            'upload' => $perSource[FileSource::Upload->value] ?? 0,
        ];
    }
}

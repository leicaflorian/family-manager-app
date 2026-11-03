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
    /** The sources that have a filter of their own, in the order of the tabs. */
    private const array TABS = [FileSource::Mail, FileSource::Phone, FileSource::Upload];

    /**
     * @param  list<PendingFileData>  $files  the files of the filter chosen, the newest first
     * @param  FileSource|null  $source  the filter chosen, null for *All*
     * @param  list<array{value: string|null, label: string, count: int}>  $filters  the tabs in order, *All* first, with how many files each has whatever the one chosen
     */
    public function __construct(
        public readonly array $files,
        public readonly ?FileSource $source,
        public readonly array $filters,
    ) {}

    /**
     * The page for a filter.
     *
     * @param  mixed  $asked  the `source` of the address: a source with a tab, anything else (a list, an unknown or other source) shows all of them
     */
    public static function for(mixed $asked): self
    {
        $source = is_string($asked) ? FileSource::tryFrom($asked) : null;
        $source = in_array($source, self::TABS, true) ? $source : null;

        $files = File::query()->toSort()->with('document')
            ->when($source instanceof FileSource, static fn ($query) => $query->where('source', $source))
            ->latest('id')->get();

        return new self(array_values(PendingFileData::fromFiles($files)), $source, self::filters());
    }

    /**
     * The tabs with how many files wait for each, in one query.
     *
     * @return list<array{value: string|null, label: string, count: int}>
     */
    private static function filters(): array
    {
        /** @var array<string, int> $perSource */
        $perSource = File::query()->toSort()->selectRaw('source, count(*) as total')->groupBy('source')
            ->pluck('total', 'source')->map(static fn (mixed $total): int => is_numeric($total) ? (int) $total : 0)->all();

        return [
            ['value' => null, 'label' => __('All'), 'count' => array_sum($perSource)],
            ...array_map(
                static fn (FileSource $source): array => ['value' => $source->value, 'label' => $source->label(), 'count' => $perSource[$source->value] ?? 0],
                self::TABS,
            ),
        ];
    }
}

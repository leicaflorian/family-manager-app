<?php

declare(strict_types=1);

namespace App\Filament\Resources\FeedbackReports\Schemas;

use App\Models\FeedbackReport;
use Carbon\CarbonImmutable;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Throwable;

/**
 * The technical part of the page of a report: the facts of the device as fields, and the last pages, the page
 * errors, the failed requests and the server errors as tables. Everything here comes from the browser of a
 * person or from an error message, so it is printed as text, never as HTML.
 */
final class FeedbackDiagnosticsSchema
{
    /**
     * The sections of the technical part, each shown only when the report has it.
     *
     * @return list<Section>
     */
    public static function sections(): array
    {
        return [
            self::device(),
            self::table(__('Last pages'), 'pages', [
                [__('When'), self::when('at')],
                [__('Path'), TextEntry::make('path')->fontFamily(FontFamily::Mono)],
                [__('Component'), self::text('component')],
            ])->visible(static fn (FeedbackReport $record): bool => self::list($record, 'pages') !== []),
            self::table(static fn (FeedbackReport $record): string => __('Page errors').' ('.$record->pageErrorCount().')', 'console', [
                [__('When'), self::when('at')],
                [__('Level'), TextEntry::make('level')->badge()
                    ->color(static fn (?string $state): string => $state === 'error' ? 'danger' : 'warning')],
                [__('Message'), self::text('message')],
                [__('Component'), self::text('component')],
            ]),
            self::table(static fn (FeedbackReport $record): string => __('Failed requests').' ('.$record->failedRequestCount().')', 'requests', [
                [__('When'), self::when('at')],
                [__('Method'), self::text('method')],
                [__('Path'), TextEntry::make('path')->fontFamily(FontFamily::Mono)->placeholder('—')],
                [__('Status'), self::text('status')],
                [__('Request id'), TextEntry::make('request_id')->fontFamily(FontFamily::Mono)->copyable()->placeholder('—')],
            ]),
            Section::make(static fn (FeedbackReport $record): string => __('Server errors').' ('.$record->serverErrorCount().')')
                ->visible(static fn (FeedbackReport $record): bool => $record->server_errors !== null)
                ->schema([
                    self::rows('server_errors', static fn (FeedbackReport $record): array => $record->server_errors ?? [], [
                        [__('When'), self::when('at')],
                        // A queued job has no method and no path: the cell keeps only the parts that are there
                        [__('Request'), TextEntry::make('request')->fontFamily(FontFamily::Mono)
                            ->state(static fn (TextEntry $component): string => self::requestOf($component->getContainer()->getConstantState()))
                            ->placeholder('—')],
                        [__('Error'), TextEntry::make('class')->weight(FontWeight::Bold)
                            ->state(static fn (TextEntry $component): string => self::errorOf($component->getContainer()->getConstantState()))],
                        [__('Where'), TextEntry::make('where')->fontFamily(FontFamily::Mono)
                            ->state(static fn (TextEntry $component): string => self::whereOf($component->getContainer()->getConstantState()))
                            ->placeholder('—')],
                    ]),
                ]),
        ];
    }

    /**
     * The facts of the device and of the app at sending time, in words.
     */
    private static function device(): Section
    {
        return Section::make(__('Technical details'))
            ->visible(static fn (FeedbackReport $record): bool => $record->diagnostics !== null)
            ->columns(3)
            ->schema([
                self::fact('screen', __('Screen'), static function (array $details): ?string {
                    $viewport = is_array($details['viewport'] ?? null) ? $details['viewport'] : [];
                    if (! isset($viewport['width'], $viewport['height'])) {
                        return null;
                    }
                    $size = self::scalar($viewport['width']).' × '.self::scalar($viewport['height']);

                    return empty($viewport['dpr']) ? $size : $size.' @ '.self::scalar($viewport['dpr']).'x';
                }),
                self::fact('orientation', __('Orientation'), static function (array $details): ?string {
                    $orientation = is_array($details['viewport'] ?? null) ? ($details['viewport']['orientation'] ?? null) : null;

                    return match (true) {
                        ! is_string($orientation) => null,
                        str_starts_with($orientation, 'portrait') => (string) __('Portrait'),
                        str_starts_with($orientation, 'landscape') => (string) __('Landscape'),
                        default => $orientation,
                    };
                }),
                self::fact('opened_as', __('Opened as'), static fn (array $details): ?string => self::choice($details['standalone'] ?? null, __('Installed app'), __('Browser'))),
                self::fact('phone', __('On a phone'), static fn (array $details): ?string => self::choice($details['phone'] ?? null, __('Yes'), __('No'))),
                self::fact('ios', __('iPhone or iPad'), static fn (array $details): ?string => self::choice($details['ios'] ?? null, __('Yes'), __('No'))),
                self::fact('theme', __('Theme'), static fn (array $details): ?string => match ($details['theme'] ?? null) {
                    'dark' => (string) __('Dark theme'),
                    'light' => (string) __('Light theme'),
                    null => null,
                    default => self::scalar($details['theme']),
                }),
                self::fact('locale', __('Language'), static fn (array $details): ?string => self::optional($details['locale'] ?? null)),
                self::fact('online', __('Online'), static fn (array $details): ?string => self::choice($details['online'] ?? null, __('Yes'), __('No'))),
                self::fact('connection', __('Connection'), static fn (array $details): ?string => self::optional($details['connection'] ?? null)),
                self::fact('page_age', __('Seconds on the page'), static fn (array $details): ?string => self::optional($details['page_age_seconds'] ?? null)),
                self::fact('component', __('Component'), static fn (array $details): ?string => self::optional($details['component'] ?? null)),
                self::fact('version', __('App version'), static function (array $details): ?string {
                    $build = self::optional($details['client_build'] ?? null);
                    $server = self::optional($details['server_version'] ?? null);

                    return $build === null && $server === null ? null : ($build ?? '—').' / '.($server ?? '—');
                })
                    ->fontFamily(FontFamily::Mono)
                    // The version the server wrote at sending time: a different client build means a stale app
                    ->belowContent(static fn (FeedbackReport $record): ?string => self::isStale($record) ? (string) __('Different from the server') : null),
            ]);
    }

    /**
     * A section with a list of the diagnostics as a table, hidden when the report has no diagnostics.
     *
     * @param  string|\Closure(FeedbackReport): string  $heading  the heading of the section
     * @param  string  $key  the key of the list in the diagnostics
     * @param  list<array{0: string, 1: TextEntry}>  $columns  the label and the entry of each column
     */
    private static function table(string|\Closure $heading, string $key, array $columns): Section
    {
        return Section::make($heading)
            ->visible(static fn (FeedbackReport $record): bool => $record->diagnostics !== null)
            ->schema([self::rows($key, static fn (FeedbackReport $record): array => self::list($record, $key), $columns)]);
    }

    /**
     * The rows of a list, one table row per entry.
     *
     * @param  string  $name  the name of the entry
     * @param  \Closure(FeedbackReport): array<array-key, mixed>  $state  the entries of the list
     * @param  list<array{0: string, 1: TextEntry}>  $columns  the label and the entry of each column
     */
    private static function rows(string $name, \Closure $state, array $columns): RepeatableEntry
    {
        return RepeatableEntry::make($name)
            ->hiddenLabel()
            ->state($state)
            ->placeholder('—')
            ->table(array_map(static fn (array $column): TableColumn => TableColumn::make($column[0]), $columns))
            ->schema(array_map(static fn (array $column): TextEntry => $column[1], $columns));
    }

    /**
     * A fact of the device, read from the diagnostics of the report.
     *
     * @param  string  $name  the name of the entry
     * @param  string  $label  the label shown above the value
     * @param  \Closure(array<array-key, mixed>): ?string  $value  the value in words, null when it was not sent
     */
    private static function fact(string $name, string $label, \Closure $value): TextEntry
    {
        return TextEntry::make($name)
            ->label($label)
            ->state(static fn (FeedbackReport $record): ?string => $value($record->diagnostics ?? []))
            ->placeholder('—');
    }

    /**
     * A cell with a text of the entry, or a dash when it is empty.
     *
     * @param  string  $key  the key of the text in the entry
     */
    private static function text(string $key): TextEntry
    {
        return TextEntry::make($key)->placeholder('—')->wrap();
    }

    /**
     * A cell with the moment of the entry, in the time zone of the app.
     *
     * @param  string  $key  the key of the moment in the entry
     */
    private static function when(string $key): TextEntry
    {
        return TextEntry::make($key)
            ->formatStateUsing(static fn (mixed $state): string => self::moment($state))
            ->placeholder('—');
    }

    /**
     * A moment sent as text, written as `15/11/2026 14:45:32`, or a dash when it cannot be read.
     *
     * @param  mixed  $at  the moment as the browser or the server wrote it
     */
    private static function moment(mixed $at): string
    {
        if (! is_string($at) || $at === '') {
            return '—';
        }

        try {
            return CarbonImmutable::parse($at)->timezone('Europe/Rome')->format('d/m/Y H:i:s');
        } catch (Throwable) {
            return '—';
        }
    }

    /**
     * The entries of a list of the diagnostics, empty when the report does not have it.
     *
     * @param  FeedbackReport  $record  the report
     * @param  string  $key  the key of the list
     * @return array<array-key, mixed>
     */
    private static function list(FeedbackReport $record, string $key): array
    {
        $list = ($record->diagnostics ?? [])[$key] ?? [];

        return is_array($list) ? $list : [];
    }

    /**
     * One of two words for a yes or no of the browser, or null when it was not sent.
     *
     * @param  mixed  $value  the yes or no
     * @param  string  $yes  the word for yes
     * @param  string  $no  the word for no
     */
    private static function choice(mixed $value, string $yes, string $no): ?string
    {
        return $value === null ? null : ($value ? $yes : $no);
    }

    /**
     * A value of the browser as text, or null when it is missing or empty.
     *
     * @param  mixed  $value  the value
     */
    private static function optional(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : self::scalar($value);
    }

    /**
     * A plain value as text; anything else as an empty text.
     *
     * @param  mixed  $value  the value
     */
    private static function scalar(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * Method, path and request id of a server error, only the parts that are there.
     *
     * @param  mixed  $error  the server error as it was copied into the report
     */
    private static function requestOf(mixed $error): string
    {
        $error = is_array($error) ? $error : [];
        $parts = [trim(self::scalar($error['method'] ?? null).' '.self::scalar($error['path'] ?? null)), self::scalar($error['request_id'] ?? null)];

        return implode(' · ', array_filter($parts, static fn (string $part): bool => $part !== ''));
    }

    /**
     * Class and message of a server error.
     *
     * @param  mixed  $error  the server error as it was copied into the report
     */
    private static function errorOf(mixed $error): string
    {
        $error = is_array($error) ? $error : [];

        return trim(self::scalar($error['class'] ?? null).': '.self::scalar($error['message'] ?? null), ': ');
    }

    /**
     * File and line where a server error was thrown.
     *
     * @param  mixed  $error  the server error as it was copied into the report
     */
    private static function whereOf(mixed $error): string
    {
        $error = is_array($error) ? $error : [];
        $file = self::scalar($error['file'] ?? null);

        return $file === '' ? '' : $file.':'.self::scalar($error['line'] ?? null);
    }

    /**
     * Whether the page of the report ran another build than the server at sending time.
     *
     * @param  FeedbackReport  $record  the report
     */
    private static function isStale(FeedbackReport $record): bool
    {
        $build = self::optional(($record->diagnostics ?? [])['client_build'] ?? null);
        $server = self::optional(($record->diagnostics ?? [])['server_version'] ?? null);

        return $build !== null && $server !== null && $build !== $server;
    }
}

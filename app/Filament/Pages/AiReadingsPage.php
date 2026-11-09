<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Admin\ReadingReport;
use App\Admin\ReadingReportRow;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

/**
 * The AI readings page of the owner panel: for a month, one row per family with readings, tokens and cost, and the
 * total above the table. The month moves with the arrows; the service and the model are table filters.
 */
final class AiReadingsPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'ai-readings';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cpu-chip';

    /** The month shown, as `yyyy-mm` of Rome. */
    public string $month = '';

    /** @var Collection<int, ReadingReportRow>|null the rows of this render, read once */
    private ?Collection $cachedRows = null;

    /**
     * The navigation and page title.
     */
    public function getTitle(): string
    {
        return __('AI readings');
    }

    /**
     * The label in the navigation.
     */
    public static function getNavigationLabel(): string
    {
        return __('AI readings');
    }

    /**
     * Starts from the current month of Rome.
     */
    public function mount(): void
    {
        $this->month = CarbonImmutable::now('Europe/Rome')->format('Y-m');
    }

    /**
     * Goes to the month before.
     */
    public function previousMonth(): void
    {
        $this->month = $this->monthStart()->subMonth()->format('Y-m');
        $this->cachedRows = null;
    }

    /**
     * Goes to the month after.
     */
    public function nextMonth(): void
    {
        $this->month = $this->monthStart()->addMonth()->format('Y-m');
        $this->cachedRows = null;
    }

    /**
     * The arrows that move the month.
     *
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('previousMonth')->label(__('Previous month'))->icon('heroicon-m-chevron-left')->color('gray')
                ->action(fn () => $this->previousMonth()),
            Action::make('nextMonth')->label(__('Next month'))->icon('heroicon-m-chevron-right')->color('gray')
                ->action(fn () => $this->nextMonth()),
        ];
    }

    /**
     * The page content: the table.
     *
     * @param  Schema  $schema  the schema to fill
     */
    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    /**
     * The table of the families of the month, from `ReadingReport` and not from the database.
     *
     * @param  Table  $table  the table to fill
     */
    public function table(Table $table): Table
    {
        return $table
            ->heading(fn (): string => __('Readings of :month', ['month' => $this->monthStart()->format('m/Y')]))
            ->description(function (): string {
                $total = resolve(ReadingReport::class)->total($this->rows());

                return __('Total: :readings readings, :input input tokens, :output output tokens, cost :cost', [
                    'readings' => $total->readings,
                    'input' => $total->inputTokens,
                    'output' => $total->outputTokens,
                    'cost' => $total->costText().($total->unpriced > 0 ? ' ('.__(':count without a price', ['count' => $total->unpriced]).')' : ''),
                ]);
            })
            ->records(fn (): array => $this->rows()
                ->mapWithKeys(fn (ReadingReportRow $row): array => [
                    $row->familyId ?? 'deleted' => [
                        'label' => $row->label,
                        'readings' => $row->readings,
                        'input_tokens' => $row->inputTokens,
                        'output_tokens' => $row->outputTokens,
                        'cost' => $row->costText().($row->unpriced > 0 ? ' ('.__(':count without a price', ['count' => $row->unpriced]).')' : ''),
                    ],
                ])
                ->all())
            ->columns([
                TextColumn::make('label')->label(__('Family')),
                TextColumn::make('readings')->label(__('Readings')),
                TextColumn::make('input_tokens')->label(__('Input tokens')),
                TextColumn::make('output_tokens')->label(__('Output tokens')),
                TextColumn::make('cost')->label(__('Cost')),
            ])
            ->filters([
                SelectFilter::make('provider')->label(__('Service'))
                    ->options(fn (): array => $this->optionsOf('providers')),
                SelectFilter::make('model')->label(__('Model'))
                    ->options(fn (): array => $this->optionsOf('models')),
            ])
            ->emptyStateHeading(__('No readings this month.'))
            ->paginated(false);
    }

    /**
     * The rows of the shown month with the filters of the table, read once per render.
     *
     * @return Collection<int, ReadingReportRow>
     */
    private function rows(): Collection
    {
        return $this->cachedRows ??= resolve(ReadingReport::class)->forMonth(
            $this->monthStart(),
            $this->filterValue('provider'),
            $this->filterValue('model'),
        );
    }

    /**
     * The value chosen in a table filter, or null when it is empty.
     *
     * @param  string  $name  the filter
     */
    private function filterValue(string $name): ?string
    {
        $state = $this->tableFilters[$name] ?? null;
        $value = is_array($state) ? ($state['value'] ?? null) : null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * The services or the models used in the shown month, as options of a filter.
     *
     * @param  'providers'|'models'  $kind  which list
     * @return array<string, string>
     */
    private function optionsOf(string $kind): array
    {
        $names = resolve(ReadingReport::class)->filterOptions($this->monthStart())[$kind];

        return array_combine($names, $names);
    }

    /**
     * The first day of the shown month in Rome.
     */
    private function monthStart(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m', $this->month, 'Europe/Rome') ?: CarbonImmutable::now('Europe/Rome')->startOfMonth();
    }
}

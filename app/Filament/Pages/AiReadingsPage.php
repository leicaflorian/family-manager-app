<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Admin\ReadingReport;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;

/**
 * The AI readings page of the owner panel: for a month, one row per family with readings, tokens and cost, and the
 * total. The month moves with the arrows; the service and the model filter the rows.
 */
final class AiReadingsPage extends Page
{
    protected static ?string $slug = 'ai-readings';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cpu-chip';

    protected string $view = 'filament.pages.ai-readings';

    /** The month shown, as `yyyy-mm` of Rome. */
    public string $month = '';

    /** The service filter, empty for all. */
    public string $provider = '';

    /** The model filter, empty for all. */
    public string $model = '';

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
    }

    /**
     * Goes to the month after.
     */
    public function nextMonth(): void
    {
        $this->month = $this->monthStart()->addMonth()->format('Y-m');
    }

    /**
     * The name of the shown month, `10/2026`.
     */
    public function monthLabel(): string
    {
        return $this->monthStart()->format('m/Y');
    }

    /**
     * What the view needs: the rows, the total and the options of the filters.
     *
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $report = resolve(ReadingReport::class);
        $month = $this->monthStart();
        $rows = $report->forMonth($month, $this->provider, $this->model);

        return [
            'rows' => $rows,
            'total' => $report->total($rows),
            'options' => $report->filterOptions($month),
        ];
    }

    /**
     * The first day of the shown month in Rome.
     */
    private function monthStart(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m', $this->month, 'Europe/Rome') ?: CarbonImmutable::now('Europe/Rome')->startOfMonth();
    }
}

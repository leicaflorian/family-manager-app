<?php

declare(strict_types=1);

namespace App\Filament\Resources\FeedbackReports;

use App\Admin\FeedbackReports;
use App\Filament\Resources\Families\FamilyResource;
use App\Filament\Resources\FeedbackReports\Pages\ListFeedbackReports;
use App\Filament\Resources\FeedbackReports\Pages\ViewFeedbackReport;
use App\Filament\Resources\FeedbackReports\Schemas\FeedbackDiagnosticsSchema;
use App\Models\FeedbackReport;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The reports that people send from *Report a problem or an idea*, read only: what it is about, who and which
 * family, the page, the device, the message and the file.
 */
final class FeedbackReportResource extends Resource
{
    protected static ?string $model = FeedbackReport::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-ellipsis';

    /**
     * The singular name of a report.
     */
    public static function getModelLabel(): string
    {
        return __('Report');
    }

    /**
     * The plural name, also the label in the navigation.
     */
    public static function getPluralModelLabel(): string
    {
        return __('Reports');
    }

    /**
     * The reports of every family, through the one query of the panel that reads them.
     *
     * @return Builder<FeedbackReport>
     */
    public static function getEloquentQuery(): Builder
    {
        return FeedbackReports::query();
    }

    /**
     * Reports are written by the families, never from the panel.
     */
    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * A report stays as it was sent.
     *
     * @param  Model  $record  the report
     */
    public static function canEdit(Model $record): bool
    {
        return false;
    }

    /**
     * A report is not deleted from the panel.
     *
     * @param  Model  $record  the report
     */
    public static function canDelete(Model $record): bool
    {
        return false;
    }

    /**
     * The list: when, which family, who, what about and the start of the message.
     *
     * @param  Table  $table  the table to fill
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label(__('Date'))
                    ->state(static fn (FeedbackReport $record): string => FamilyResource::dateTime($record->created_at)),
                TextColumn::make('family.name')->label(__('Family')),
                TextColumn::make('creator.name')->label(__('Who'))->placeholder('—'),
                TextColumn::make('kind')->label(__('What about'))->badge()
                    ->state(static fn (FeedbackReport $record): string => $record->kind->label()),
                TextColumn::make('message')->label(__('Message'))->limit(80),
                TextColumn::make('file_name')->label(__('File'))->placeholder('—'),
            ])
            ->recordUrl(static fn (FeedbackReport $record): string => self::getUrl('view', ['record' => $record]));
    }

    /**
     * The page of a report: everything that was sent.
     *
     * @param  Schema  $schema  the schema to fill
     */
    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('Report'))->columns(3)->schema([
                TextEntry::make('kind')->label(__('What about'))
                    ->state(static fn (FeedbackReport $record): string => $record->kind->label()),
                TextEntry::make('created_at')->label(__('Date'))
                    ->state(static fn (FeedbackReport $record): string => FamilyResource::dateTime($record->created_at)),
                TextEntry::make('family.name')->label(__('Family')),
                TextEntry::make('creator.name')->label(__('Who'))->placeholder('—'),
                TextEntry::make('creator.email')->label(__('Email'))->placeholder('—'),
                TextEntry::make('file_name')->label(__('File'))->placeholder('—'),
                TextEntry::make('message')->label(__('Message'))->columnSpanFull(),
            ]),
            Section::make(__('Where it was sent from'))->columns(3)->schema([
                TextEntry::make('page')->label(__('Page')),
                TextEntry::make('layout')->label(__('Layout'))->placeholder('—'),
                TextEntry::make('user_agent')->label(__('Device'))->placeholder('—'),
            ]),
            Section::make(__('Screenshot'))
                ->visible(static fn (FeedbackReport $record): bool => $record->screenshot_path !== null)
                ->schema([ViewEntry::make('screenshot')->hiddenLabel()->view('filament.feedback.screenshot')]),
            ...FeedbackDiagnosticsSchema::sections(),
        ]);
    }

    /**
     * The pages of the resource.
     *
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListFeedbackReports::route('/'),
            'view' => ViewFeedbackReport::route('/{record}'),
        ];
    }
}

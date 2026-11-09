<?php

declare(strict_types=1);

namespace App\Filament\Resources\Families;

use App\Enums\FamilyState;
use App\Filament\Resources\Families\Pages\ListFamilies;
use App\Filament\Resources\Families\Pages\ViewFamily;
use App\Filament\Resources\Families\RelationManagers\LinkedDevicesRelationManager;
use App\Filament\Resources\Families\RelationManagers\OwnerActionsRelationManager;
use App\Filament\Resources\Families\RelationManagers\UsersRelationManager;
use App\Models\Family;
use Carbon\CarbonInterface;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The families in the owner panel: who they are, how many accounts, people and documents, the space they use
 * and what the AI readings cost. Only numbers: the content of a family is never shown.
 */
final class FamilyResource extends Resource
{
    protected static ?string $model = Family::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    /**
     * The singular name of a family.
     */
    public static function getModelLabel(): string
    {
        return __('Family');
    }

    /**
     * The plural name of the families, also the label in the navigation.
     */
    public static function getPluralModelLabel(): string
    {
        return __('Families');
    }

    /**
     * Nobody creates a family from the panel: a family signs up on its own.
     */
    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * The list: one row per family with its figures, the newest first.
     *
     * @param  Table  $table  the table to fill
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('Family'))
                    ->searchable(query: static fn (Builder $query, string $search): Builder => $query->where(
                        static fn (Builder $query): Builder => $query
                            ->where('name', 'like', "%{$search}%")
                            ->orWhereHas('users', static fn (Builder $users): Builder => $users->where('email', 'like', "%{$search}%")),
                    )),
                TextColumn::make('created_at')
                    ->label(__('Signed up on'))
                    ->state(static fn (Family $record): string => self::signedUp($record))
                    ->sortable(),
                TextColumn::make('state')
                    ->label(__('State'))
                    ->badge()
                    ->state(static fn (Family $record): string => $record->state()->label())
                    ->color(static fn (Family $record): string => self::stateColor($record->state())),
                TextColumn::make('users_count')->label(__('Accounts'))->counts('users'),
                TextColumn::make('people')
                    ->label(__('People'))
                    ->state(static fn (Family $record, ListFamilies $livewire): int => $livewire->figureOf($record)->people),
                TextColumn::make('documents')
                    ->label(__('Documents'))
                    ->state(static fn (Family $record, ListFamilies $livewire): int => $livewire->figureOf($record)->documents),
                TextColumn::make('space')
                    ->label(__('Space'))
                    ->state(static fn (Family $record, ListFamilies $livewire): string => self::megabytes($livewire->figureOf($record)->fileBytes)),
                TextColumn::make('readings')
                    ->label(__('Readings this month'))
                    ->state(static fn (Family $record, ListFamilies $livewire): int => $livewire->figureOf($record)->readings),
                TextColumn::make('reading_cost')
                    ->label(__('Cost this month'))
                    ->state(static fn (Family $record, ListFamilies $livewire): string => self::dollars($livewire->figureOf($record)->readingCost)),
                TextColumn::make('last_activity')
                    ->label(__('Last access'))
                    ->state(static fn (Family $record, ListFamilies $livewire): string => self::dateTime($livewire->figureOf($record)->lastActivity)),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordUrl(static fn (Family $record): string => self::getUrl('view', ['record' => $record]));
    }

    /**
     * The family page: the same figures as the list, the reading limit and the readings of the last twelve months.
     *
     * @param  Schema  $schema  the schema to fill
     */
    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('Family'))->columns(3)->schema([
                TextEntry::make('name')->label(__('Family')),
                TextEntry::make('created_at')->label(__('Signed up on'))->state(static fn (Family $record): string => self::signedUp($record)),
                TextEntry::make('state')->label(__('State'))->badge()
                    ->state(static fn (Family $record): string => $record->state()->label())
                    ->color(static fn (Family $record): string => self::stateColor($record->state())),
                TextEntry::make('users_count')->label(__('Accounts'))->state(static fn (Family $record): int => $record->users()->count()),
                TextEntry::make('people')->label(__('People'))->state(static fn (Family $record, ViewFamily $livewire): int => $livewire->figure()->people),
                TextEntry::make('documents')->label(__('Documents'))->state(static fn (Family $record, ViewFamily $livewire): int => $livewire->figure()->documents),
                TextEntry::make('space')->label(__('Space'))->state(static fn (Family $record, ViewFamily $livewire): string => self::megabytes($livewire->figure()->fileBytes)),
                TextEntry::make('readings')->label(__('Readings this month'))->state(static fn (Family $record, ViewFamily $livewire): int => $livewire->figure()->readings),
                TextEntry::make('reading_cost')->label(__('Cost this month'))->state(static fn (Family $record, ViewFamily $livewire): string => self::dollars($livewire->figure()->readingCost)),
                TextEntry::make('last_activity')->label(__('Last access'))->state(static fn (Family $record, ViewFamily $livewire): string => self::dateTime($livewire->figure()->lastActivity)),
                TextEntry::make('reading_limit')->label(__('Reading limit of this family'))
                    ->state(static fn (Family $record): string => $record->reading_limit === null ? __('Same as everyone') : (string) $record->reading_limit),
            ]),
            Section::make(__('Readings of the last 12 months'))->schema([
                TextEntry::make('months')->hiddenLabel()->listWithLineBreaks()
                    ->state(static fn (Family $record, ViewFamily $livewire): array => $livewire->monthLines()),
            ]),
        ]);
    }

    /**
     * The accounts and the linked phones of a family, under its figures.
     *
     * @return list<class-string>
     */
    public static function getRelations(): array
    {
        return [
            UsersRelationManager::class,
            LinkedDevicesRelationManager::class,
            OwnerActionsRelationManager::class,
        ];
    }

    /**
     * The pages of the resource.
     *
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListFamilies::route('/'),
            'view' => ViewFamily::route('/{record}'),
        ];
    }

    /**
     * Bytes as megabytes: `148 MB`, `< 1 MB` for a little, `0 MB` for nothing.
     *
     * @param  int  $bytes  the size
     */
    public static function megabytes(int $bytes): string
    {
        return match (true) {
            $bytes === 0 => '0 MB',
            $bytes < 1048576 => '< 1 MB',
            default => number_format(round($bytes / 1048576), 0, ',', '.').' MB',
        };
    }

    /**
     * A cost in dollars with four decimals, as in `0,0142 $`.
     *
     * @param  float  $amount  the cost
     */
    public static function dollars(float $amount): string
    {
        return number_format($amount, 4, ',', '.').' $';
    }

    /**
     * A moment as `15/10/2026 21:30` in the time of Rome, or a dash when there is none.
     *
     * @param  CarbonInterface|null  $moment  the moment
     */
    public static function dateTime(?CarbonInterface $moment): string
    {
        return $moment?->setTimezone('Europe/Rome')->format('d/m/Y H:i') ?? '—';
    }

    /**
     * When a family signed up, or when it was copied from Directus.
     *
     * @param  Family  $family  the family
     */
    private static function signedUp(Family $family): string
    {
        $copied = $family->copied_from_directus_at;

        return $copied !== null
            ? __('Copied from Directus on :date', ['date' => $copied->setTimezone('Europe/Rome')->format('d/m/Y')])
            : ($family->created_at?->setTimezone('Europe/Rome')->format('d/m/Y') ?? '—');
    }

    /**
     * The colour of the state badge.
     *
     * @param  FamilyState  $state  the state
     */
    private static function stateColor(FamilyState $state): string
    {
        return match ($state) {
            FamilyState::Active => 'success',
            FamilyState::Suspended => 'warning',
            FamilyState::ToDelete => 'danger',
        };
    }
}

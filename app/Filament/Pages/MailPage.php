<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Admin\MailCalls;
use App\Enums\CallOutcome;
use App\Models\Family;
use App\Models\MailInboxCall;
use App\Services\Mailgun\MailgunRouteException;
use App\Services\Mailgun\MailgunRouteSetup;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The Mail page of the owner panel: the state of the Mailgun setup (never the keys), the log of its calls (never
 * the text of a mail) and the button that writes the Mailgun route, to use only at step 4 of the move.
 */
final class MailPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'mail';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-envelope';

    private ?MailgunRouteSetup $setup = null;

    /** @var array<int, string>|null the family names by id, read once per render */
    private ?array $familyNames = null;

    /**
     * The navigation and page title.
     */
    public function getTitle(): string
    {
        return __('Mail');
    }

    /**
     * The label in the navigation.
     */
    public static function getNavigationLabel(): string
    {
        return __('Mail');
    }

    /**
     * The buttons of the page: the one that writes the Mailgun route, visible only with both keys.
     *
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('writeRoute')
                ->label(__('Write the Mailgun route'))
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription(__('Mailgun will send to this app all the mail of the domain. Do it only at step 4, when the owner\'s family moves here.'))
                ->visible(fn (): bool => $this->setup()->hasApiKey() && $this->setup()->hasSigningKey())
                ->action(function (): void {
                    try {
                        $done = $this->setup()->run();
                    } catch (MailgunRouteException $e) {
                        Notification::make()->title(__('The route was not written.'))->body($e->getMessage())->danger()->send();

                        return;
                    }

                    $title = match ($done['result']) {
                        'created' => __('Route created for :domain.', ['domain' => $done['domain']]),
                        'updated' => __('Route updated for :domain.', ['domain' => $done['domain']]),
                        'unchanged' => __('Route already right for :domain.', ['domain' => $done['domain']]),
                    };

                    Notification::make()->title($title)->success()->send();
                }),
        ];
    }

    /**
     * The page content: the state of the setup and the table of the calls.
     *
     * @param  Schema  $schema  the schema to fill
     */
    public function content(Schema $schema): Schema
    {
        $setup = $this->setup();

        return $schema->components([
            Section::make(__('Mailgun'))->schema([
                Text::make(__('Domain').': '.($setup->domain() ?? __('Not set'))),
                Text::make($setup->hasSigningKey() ? __('Signing key: set') : __('Signing key: not set')),
                Text::make($setup->hasApiKey() ? __('API key: set') : __('API key: not set')),
            ]),
            EmbeddedTable::make(),
        ]);
    }

    /**
     * The table of the calls, newest first. The query selects no payload, so no text of a mail can be shown.
     *
     * @param  Table  $table  the table to fill
     */
    public function table(Table $table): Table
    {
        return $table
            ->query(static fn (): Builder => MailCalls::query())
            ->columns([
                TextColumn::make('created_at')->label(__('Date'))
                    ->state(static fn (MailInboxCall $record): string => $record->created_at?->setTimezone('Europe/Rome')->format('d/m/Y H:i') ?? '-'),
                TextColumn::make('outcome')->label(__('Outcome'))
                    ->state(static fn (MailInboxCall $record): string => $record->outcome->label()),
                TextColumn::make('http_status')->label(__('Status')),
                TextColumn::make('family_id')->label(__('Family'))
                    ->state(fn (MailInboxCall $record): string => $record->family_id === null ? '-' : ($this->familyNames()[$record->family_id] ?? '#'.$record->family_id)),
                TextColumn::make('attachments')->label(__('Files')),
                TextColumn::make('saved')->label(__('Saved')),
            ])
            ->filters([
                SelectFilter::make('family_id')->label(__('Family'))
                    ->options(fn (): array => $this->familyNames()),
                SelectFilter::make('outcome')->label(__('Outcome'))
                    ->options(collect(CallOutcome::cases())->mapWithKeys(static fn (CallOutcome $case): array => [$case->value => $case->label()])->all()),
                Filter::make('since')
                    ->schema([DatePicker::make('since')->label(__('From day'))])
                    ->query(static fn (Builder $query, array $data): Builder => $query->when(
                        is_string($data['since'] ?? null) && $data['since'] !== '',
                        static fn (Builder $query) => $query->where('created_at', '>=', CarbonImmutable::parse((string) $data['since'], 'Europe/Rome')->startOfDay()),
                    )),
            ])
            ->emptyStateHeading(__('No calls.'));
    }

    /**
     * The service that knows the Mailgun settings and writes the route, resolved once.
     */
    private function setup(): MailgunRouteSetup
    {
        return $this->setup ??= resolve(MailgunRouteSetup::class);
    }

    /**
     * The names of the families by id, read once.
     *
     * @return array<int, string>
     */
    private function familyNames(): array
    {
        if ($this->familyNames === null) {
            $this->familyNames = [];

            foreach (Family::query()->orderBy('name')->orderBy('id')->get(['id', 'name']) as $family) {
                $this->familyNames[$family->id] = $family->name;
            }
        }

        return $this->familyNames;
    }
}

<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Admin\MailCalls;
use App\Enums\CallOutcome;
use App\Models\Family;
use App\Services\Mailgun\MailgunRouteException;
use App\Services\Mailgun\MailgunRouteSetup;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Throwable;

/**
 * The Mail page of the owner panel: the state of the Mailgun setup (never the keys), the log of its calls (never
 * the text of a mail) and the button that writes the Mailgun route, to use only at step 4 of the move.
 */
final class MailPage extends Page
{
    /** How many calls are shown, the newest first. */
    private const int LIMIT = 100;

    protected static ?string $slug = 'mail';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-envelope';

    protected string $view = 'filament.pages.mail';

    /** The family filter (its id), empty for all. */
    public string $family = '';

    /** The outcome filter (a `CallOutcome` value), empty for all. */
    public string $outcome = '';

    /** The date filter, `yyyy-mm-dd`, empty for all. */
    public string $since = '';

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
     * What the view needs: the state of the setup, the filters' options and the calls.
     *
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $outcome = CallOutcome::tryFrom($this->outcome);
        $since = null;

        if ($this->since !== '') {
            try {
                $since = CarbonImmutable::parse($this->since, 'Europe/Rome')->startOfDay();
            } catch (Throwable) {
                $since = null;
            }
        }

        $calls = MailCalls::query($this->family === '' ? null : (int) $this->family, $outcome, $since)->limit(self::LIMIT)->get();

        return [
            'domain' => $this->setup()->domain(),
            'hasSigningKey' => $this->setup()->hasSigningKey(),
            'hasApiKey' => $this->setup()->hasApiKey(),
            'families' => Family::query()->orderBy('name')->orderBy('id')->pluck('name', 'id')->all(),
            'outcomes' => CallOutcome::cases(),
            'calls' => $calls,
        ];
    }

    /**
     * The service that knows the Mailgun settings and writes the route.
     */
    private function setup(): MailgunRouteSetup
    {
        return resolve(MailgunRouteSetup::class);
    }
}

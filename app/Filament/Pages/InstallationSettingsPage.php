<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\InstallationSetting;
use App\Support\InstallationSettings;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\ValidationException;

/**
 * The Settings page of the owner panel: the reading service, the keys and the times of the installation.
 * The database values win over the `.env`; a key is never put in the page state, only its status.
 */
final class InstallationSettingsPage extends Page
{
    protected static ?string $slug = 'settings';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    /** @var array<string, string|null> the form state (provider, model, ollama_url, limit, reminders_time, loans_time), with no keys */
    public array $data = [];

    /**
     * The navigation and page title.
     */
    public function getTitle(): string
    {
        return __('Settings');
    }

    /**
     * The label in the navigation.
     */
    public static function getNavigationLabel(): string
    {
        return __('Settings');
    }

    /**
     * Loads the stored values (not the keys) into the form.
     */
    public function mount(): void
    {
        $settings = resolve(InstallationSettings::class);

        $this->data = [
            'provider' => $settings->stored(InstallationSetting::Provider) ?? '',
            'model' => $settings->stored(InstallationSetting::Model),
            'ollama_url' => $settings->stored(InstallationSetting::OllamaUrl),
            'limit' => $settings->stored(InstallationSetting::MonthlyLimit),
            'reminders_time' => $settings->stored(InstallationSetting::RemindersTime),
            'loans_time' => $settings->stored(InstallationSetting::LoansTime),
        ];
    }

    /**
     * The page content: the three sections inside one form.
     *
     * @param  Schema  $schema  the schema to fill
     */
    public function content(Schema $schema): Schema
    {
        $settings = resolve(InstallationSettings::class);

        return $schema->components([
            Form::make([
                Section::make(__('Reading of the documents'))->schema([
                    Select::make('data.provider')
                        ->label(__('Service'))
                        ->options([
                            '' => __('Same as the server'),
                            'gemini' => 'Gemini',
                            'anthropic' => 'Anthropic',
                            'ollama' => __('Own model'),
                            'off' => __('Off'),
                        ])
                        ->selectablePlaceholder(false),
                    TextInput::make('data.model')
                        ->label(__('Model'))
                        ->placeholder(Config::string('document_reader.model', '')),
                    TextInput::make('data.ollama_url')
                        ->label(__('Own model address'))
                        ->placeholder(Config::string('ai.providers.ollama.url', '')),
                    TextInput::make('data.limit')
                        ->label(__('Reading limit per family per month'))
                        ->placeholder((string) Config::integer('document_reader.monthly_limit', 0)),
                ]),
                Section::make(__('Times'))->schema([
                    TextInput::make('data.reminders_time')
                        ->label(__('Morning round'))
                        ->placeholder(Config::string('reminders.time', '')),
                    TextInput::make('data.loans_time')
                        ->label(__('Instalment count'))
                        ->placeholder($settings->loansTime()),
                ]),
            ])
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')->label(__('Save'))->submit('save'),
                    ]),
                ]),
            Section::make(__('Keys'))->schema([
                $this->keyRow(InstallationSetting::GeminiKey, 'Gemini'),
                $this->keyRow(InstallationSetting::AnthropicKey, 'Anthropic'),
                $this->keyRow(InstallationSetting::OllamaKey, __('Own model')),
            ]),
        ]);
    }

    /**
     * A row of the Keys section: the name, the status and the Replace and Remove buttons.
     *
     * @param  InstallationSetting  $setting  the key
     * @param  string  $name  the label of the row
     */
    private function keyRow(InstallationSetting $setting, string $name): Section
    {
        $settings = resolve(InstallationSettings::class);
        $updated = $settings->updatedAt($setting);

        $status = match (true) {
            $settings->unreadable($setting) => __('To write again'),
            $settings->stored($setting) !== null && $updated !== null => __('Set on :date', [
                'date' => Date::parse($updated)->setTimezone('Europe/Rome')->format('d/m/Y'),
            ]),
            default => __('Not set: the server one is used'),
        };

        return Section::make($name)->compact()->schema([
            Text::make($status),
            Actions::make([
                $this->replaceAction($setting),
                $this->removeAction($setting),
            ])->key('actions_'.$setting->value),
        ]);
    }

    /**
     * The Replace button: a modal with a write-only field.
     *
     * @param  InstallationSetting  $setting  the key
     */
    private function replaceAction(InstallationSetting $setting): Action
    {
        return Action::make('replace_'.$setting->value)
            ->label(__('Replace'))
            ->color('gray')
            ->modalHeading(__('Replace'))
            ->schema([
                TextInput::make('key')->label(__('New key'))->password()->required(),
            ])
            ->action(function (array $data) use ($setting): void {
                resolve(InstallationSettings::class)->set($setting, (string) $data['key']);

                Notification::make()->title(__('Saved'))->success()->send();
            });
    }

    /**
     * The Remove button: the server key counts again.
     *
     * @param  InstallationSetting  $setting  the key
     */
    private function removeAction(InstallationSetting $setting): Action
    {
        return Action::make('remove_'.$setting->value)
            ->label(__('Remove'))
            ->color('danger')
            ->requiresConfirmation()
            ->action(function () use ($setting): void {
                resolve(InstallationSettings::class)->set($setting, null);

                Notification::make()->title(__('Saved'))->success()->send();
            });
    }

    /**
     * Validates the form and writes the settings that changed.
     *
     * @throws ValidationException when the limit or a time is not valid
     */
    public function save(): void
    {
        $validated = $this->validate([
            'data.provider' => ['nullable', 'in:,gemini,anthropic,ollama,off'],
            'data.model' => ['nullable', 'string', 'max:120'],
            'data.ollama_url' => ['nullable', 'string', 'max:255'],
            'data.limit' => ['nullable', 'integer', 'min:0'],
            'data.reminders_time' => ['nullable', 'date_format:H:i'],
            'data.loans_time' => ['nullable', 'date_format:H:i'],
        ]);
        $data = is_array($validated['data'] ?? null) ? $validated['data'] : [];

        $settings = resolve(InstallationSettings::class);

        $fields = [
            'provider' => InstallationSetting::Provider,
            'model' => InstallationSetting::Model,
            'ollama_url' => InstallationSetting::OllamaUrl,
            'limit' => InstallationSetting::MonthlyLimit,
            'reminders_time' => InstallationSetting::RemindersTime,
            'loans_time' => InstallationSetting::LoansTime,
        ];

        foreach ($fields as $field => $setting) {
            $value = $data[$field] ?? null;
            $new = is_string($value) && $value !== '' ? $value : null;

            if ($new !== $settings->stored($setting)) {
                $settings->set($setting, $new);
            }
        }

        Notification::make()->title(__('Saved'))->success()->send();
    }
}

<?php

declare(strict_types=1);

use App\Enums\InstallationSetting;
use App\Filament\Pages\InstallationSettingsPage;
use App\Models\Owner;
use App\Support\InstallationSettings;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    actingAs(Owner::factory()->withAuthenticator()->create(), 'owner');
});

it('shows the stored values and the env ones as placeholders', function (): void {
    config(['document_reader.model' => 'env-model']);
    resolve(InstallationSettings::class)->set(InstallationSetting::Provider, 'gemini');

    Livewire::test(InstallationSettingsPage::class)
        ->assertSet('data.provider', 'gemini')
        ->assertSet('data.model', null)
        ->assertSeeHtml('placeholder="env-model"');
});

it('saves the settings', function (): void {
    Livewire::test(InstallationSettingsPage::class)
        ->fillForm(['data.provider' => 'anthropic', 'data.model' => 'claude-haiku-4-5', 'data.limit' => '50'])
        ->call('save')
        ->assertHasNoErrors();

    $settings = resolve(InstallationSettings::class);
    expect($settings->get(InstallationSetting::Provider))->toBe('anthropic')
        ->and($settings->get(InstallationSetting::Model))->toBe('claude-haiku-4-5')
        ->and($settings->get(InstallationSetting::MonthlyLimit))->toBe('50');
});

it('never shows a key', function (): void {
    resolve(InstallationSettings::class)->set(InstallationSetting::GeminiKey, 'secret-gemini-value');

    Livewire::test(InstallationSettingsPage::class)
        ->assertDontSee('secret-gemini-value', true, false)
        ->assertSee('Set on');
});

it('replaces and removes a key', function (): void {
    $settings = resolve(InstallationSettings::class);

    Livewire::test(InstallationSettingsPage::class)
        ->callAction(TestAction::make('replace_anthropic_key')->schemaComponent('actions_anthropic_key'), ['key' => 'new-key'])
        ->assertDontSee('new-key', true, false);

    expect($settings->stored(InstallationSetting::AnthropicKey))->toBe('new-key');

    Livewire::test(InstallationSettingsPage::class)
        ->callAction(TestAction::make('remove_anthropic_key')->schemaComponent('actions_anthropic_key'));

    expect($settings->stored(InstallationSetting::AnthropicKey))->toBeNull();
});

it('says to write an unreadable key again', function (): void {
    resolve(InstallationSettings::class)->set(InstallationSetting::GeminiKey, 'old');
    DB::table('app_settings')->where('key', 'gemini_key')->update(['value' => 'garbage']);
    Cache::flush();

    Livewire::test(InstallationSettingsPage::class)->assertSee('To write again');
});

it('refuses a wrong time', function (): void {
    Livewire::test(InstallationSettingsPage::class)
        ->fillForm(['data.reminders_time' => '25:00'])
        ->call('save')
        ->assertHasErrors(['data.reminders_time']);

    expect(resolve(InstallationSettings::class)->stored(InstallationSetting::RemindersTime))->toBeNull();
});

it('refuses a negative limit', function (): void {
    Livewire::test(InstallationSettingsPage::class)
        ->fillForm(['data.limit' => '-1'])
        ->call('save')
        ->assertHasErrors(['data.limit']);

    expect(resolve(InstallationSettings::class)->stored(InstallationSetting::MonthlyLimit))->toBeNull();
});

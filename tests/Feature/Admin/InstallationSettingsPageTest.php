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

it('shows Remove only for a key that is stored', function (): void {
    $action = TestAction::make('remove_anthropic_key')->schemaComponent('actions_anthropic_key');

    Livewire::test(InstallationSettingsPage::class)->assertActionDoesNotExist($action);

    resolve(InstallationSettings::class)->set(InstallationSetting::AnthropicKey, 'a-key');

    Livewire::test(InstallationSettingsPage::class)->assertActionExists($action);
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

it('writes nothing when the form is saved untouched', function (): void {
    $this->travelTo(now()->subDay());
    resolve(InstallationSettings::class)->set(InstallationSetting::Model, 'stored-model');
    $before = DB::table('app_settings')->get()->all();
    $this->travelBack();

    Livewire::test(InstallationSettingsPage::class)->call('save')->assertHasNoErrors();

    expect(DB::table('app_settings')->get()->all())->toEqual($before);

    DB::table('app_settings')->delete();
    Livewire::test(InstallationSettingsPage::class)->call('save')->assertHasNoErrors();

    expect(DB::table('app_settings')->count())->toBe(0);
});

it('gives the env value back when a stored field is cleared', function (): void {
    config(['document_reader.model' => 'env-model']);
    resolve(InstallationSettings::class)->set(InstallationSetting::Model, 'stored-model');

    Livewire::test(InstallationSettingsPage::class)
        ->fillForm(['data.model' => ''])
        ->call('save')
        ->assertHasNoErrors();

    $settings = resolve(InstallationSettings::class);
    expect($settings->stored(InstallationSetting::Model))->toBeNull()
        ->and($settings->get(InstallationSetting::Model))->toBe('env-model');
});

it('keeps the provider off as it is through mount and save', function (): void {
    config(['document_reader.provider' => 'gemini']);
    resolve(InstallationSettings::class)->set(InstallationSetting::Provider, 'off');

    Livewire::test(InstallationSettingsPage::class)
        ->assertSet('data.provider', 'off')
        ->call('save')
        ->assertHasNoErrors();

    expect(resolve(InstallationSettings::class)->stored(InstallationSetting::Provider))->toBe('off');
});

<?php

declare(strict_types=1);

use App\Enums\InstallationSetting;
use App\Models\AppSetting;
use App\Services\Reading\AiDocumentReader;
use App\Support\InstallationSettings;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\AiManager;

uses(RefreshDatabase::class);

test('the env value wins while the database is empty', function (): void {
    config(['document_reader.model' => 'from-env']);

    expect(resolve(InstallationSettings::class)->get(InstallationSetting::Model))->toBe('from-env');
});

test('the database wins over the env', function (): void {
    config(['document_reader.model' => 'from-env', 'document_reader.provider' => 'gemini']);
    $settings = resolve(InstallationSettings::class);

    $settings->set(InstallationSetting::Model, 'from-database');
    $settings->set(InstallationSetting::Provider, 'anthropic');
    $settings->apply();

    expect($settings->get(InstallationSetting::Model))->toBe('from-database')
        ->and(config('document_reader.model'))->toBe('from-database')
        ->and(config('document_reader.provider'))->toBe('anthropic');
});

test('off turns the reading off even with the env on', function (): void {
    config(['document_reader.provider' => 'gemini', 'ai.providers.gemini.key' => 'env-key']);
    expect(resolve(AiDocumentReader::class)->isActive())->toBeTrue();

    $settings = resolve(InstallationSettings::class);
    $settings->set(InstallationSetting::Provider, 'off');
    $settings->apply();

    expect(resolve(AiDocumentReader::class)->isActive())->toBeFalse();
});

test('a key is stored encrypted', function (): void {
    $settings = resolve(InstallationSettings::class);

    $settings->set(InstallationSetting::GeminiKey, 'secret-key-123');

    $raw = (string) DB::table('app_settings')->where('key', 'gemini_key')->value('value');
    expect($raw)->not->toContain('secret-key-123')
        ->and($settings->get(InstallationSetting::GeminiKey))->toBe('secret-key-123')
        ->and($settings->updatedAt(InstallationSetting::GeminiKey))->not->toBeNull();

    $settings->apply();
    expect(config('ai.providers.gemini.key'))->toBe('secret-key-123');
});

test('a key encrypted with another APP_KEY is unreadable and the env one is used', function (): void {
    config(['ai.providers.gemini.key' => 'env-key']);
    $other = new Encrypter(random_bytes(32), 'AES-256-CBC');
    AppSetting::query()->create(['key' => 'gemini_key', 'value' => $other->encryptString('old-secret')]);
    // The row is written behind the settings' back, as by another process: forget the cache the way set() does, and
    // read with a new object, as that process would (the singleton keeps the rows it read until apply() or set())
    Cache::forget('installation-settings');
    $settings = new InstallationSettings;

    expect($settings->unreadable(InstallationSetting::GeminiKey))->toBeTrue()
        ->and($settings->stored(InstallationSetting::GeminiKey))->toBeNull()
        ->and($settings->get(InstallationSetting::GeminiKey))->toBe('env-key');

    $settings->apply();
    expect(config('ai.providers.gemini.key'))->toBe('env-key');
});

test('an empty value removes the row', function (): void {
    $settings = resolve(InstallationSettings::class);
    $settings->set(InstallationSetting::Model, 'x');
    expect(AppSetting::query()->count())->toBe(1);

    $settings->set(InstallationSetting::Model, '');

    expect(AppSetting::query()->count())->toBe(0)
        ->and($settings->stored(InstallationSetting::Model))->toBeNull();

    $settings->set(InstallationSetting::Model, 'x');
    $settings->set(InstallationSetting::Model, null);
    expect(AppSetting::query()->count())->toBe(0);
});

test('the times fall back to 06:00 and 00:05', function (): void {
    $settings = resolve(InstallationSettings::class);

    expect($settings->remindersTime())->toBe('06:00')
        ->and($settings->loansTime())->toBe('00:05');
});

test('only the three keys are secret and only the loans time has no config key', function (): void {
    $secret = array_filter(InstallationSetting::cases(), fn (InstallationSetting $s): bool => $s->isSecret());

    expect($secret)->toHaveCount(3)
        ->and(InstallationSetting::LoansTime->configKey())->toBeNull()
        ->and(InstallationSetting::MonthlyLimit->configKey())->toBe('document_reader.monthly_limit');
});

test('a removed setting gives the env value back after apply', function (): void {
    config(['document_reader.model' => 'from-env', 'document_reader.provider' => 'gemini']);
    $settings = resolve(InstallationSettings::class);

    $settings->set(InstallationSetting::Model, 'from-database');
    $settings->set(InstallationSetting::Provider, 'off');
    $settings->apply();
    expect(config('document_reader.model'))->toBe('from-database')
        ->and(config('document_reader.provider'))->toBeNull();

    $settings->set(InstallationSetting::Model, '');
    $settings->set(InstallationSetting::Provider, '');
    $settings->apply();

    expect(config('document_reader.model'))->toBe('from-env')
        ->and(config('document_reader.provider'))->toBe('gemini');
});

test('with a cache table that does not exist the env values apply and nothing throws', function (): void {
    // A store on a table that is not there stands for a fresh database before `migrate` (dropping real tables would commit the test transaction)
    config(['cache.default' => 'database', 'cache.stores.database.table' => 'no_such_cache', 'cache.stores.database.lock_table' => 'no_such_locks', 'reminders.time' => '05:30']);
    $settings = new InstallationSettings;

    expect($settings->remindersTime())->toBe('05:30')
        ->and($settings->loansTime())->toBe('00:05');

    $settings->apply();
    expect(config('reminders.time'))->toBe('05:30');
});

test('a new key reaches the providers an earlier job already built', function (): void {
    config(['ai.providers.gemini.key' => 'env-key', 'ai.providers.ollama.url' => 'http://env:11434']);
    $settings = resolve(InstallationSettings::class);
    $manager = resolve(AiManager::class);
    $settings->apply();

    expect($manager->textProvider('gemini')->providerCredentials()['key'])->toBe('env-key')
        ->and($manager->textProvider('ollama')->additionalConfiguration()['url'])->toBe('http://env:11434');

    $settings->set(InstallationSetting::GeminiKey, 'panel-key');
    $settings->set(InstallationSetting::OllamaUrl, 'http://panel:11434');
    $settings->apply();

    expect($manager->textProvider('gemini')->providerCredentials()['key'])->toBe('panel-key')
        ->and($manager->textProvider('ollama')->additionalConfiguration()['url'])->toBe('http://panel:11434');
});

test('apply reads the settings from the database once', function (): void {
    $settings = resolve(InstallationSettings::class);
    $settings->set(InstallationSetting::Model, 'from-database');

    DB::flushQueryLog();
    DB::enableQueryLog();
    $settings->apply();
    $settings->get(InstallationSetting::Provider);
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    // The check of the table and the read of the rows, not one pair for each setting
    expect($queries)->toBeLessThanOrEqual(2)
        ->and(config('document_reader.model'))->toBe('from-database');
});

<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\InstallationSetting;
use App\Models\AppSetting;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Laravel\Ai\AiManager;
use PDOException;

/**
 * The settings of the installation: the value in the database (written from the admin panel) wins, the `.env`
 * (through `config`) counts when the setting is empty. `apply()` copies the database values into `config`, so
 * the Laravel AI SDK and the rest of the app keep reading `config` unchanged.
 */
final class InstallationSettings
{
    private const string CACHE_KEY = 'installation-settings';

    private const string LOANS_TIME = '00:05';

    /** @var array<string, mixed>|null the `config` values before the first `apply()` */
    private ?array $original = null;

    /** @var array<string, array{value: ?string, updated_at: ?string}>|null the rows read once, until the next `apply()` or `set()` */
    private ?array $rows = null;

    /**
     * The current `config` value of every key a setting replaces.
     *
     * @return array<string, mixed>
     */
    private function snapshot(): array
    {
        $values = [];

        foreach (InstallationSetting::cases() as $setting) {
            if (($configKey = $setting->configKey()) !== null) {
                $values[$configKey] = Config::get($configKey);
            }
        }

        return $values;
    }

    /**
     * The value in force: the database one, else the `config` one (`00:05` for the loans time).
     *
     * @param  InstallationSetting  $setting  the setting
     */
    public function get(InstallationSetting $setting): ?string
    {
        $stored = $this->stored($setting);

        if ($stored !== null) {
            return $stored;
        }

        $configKey = $setting->configKey();

        if ($configKey === null) {
            return self::LOANS_TIME;
        }

        $value = Config::get($configKey);

        return is_scalar($value) ? (string) $value : null;
    }

    /**
     * The value in the database only; null when there is none or when a key cannot be decrypted (the `APP_KEY` changed).
     *
     * @param  InstallationSetting  $setting  the setting
     */
    public function stored(InstallationSetting $setting): ?string
    {
        $raw = $this->rows()[$setting->value]['value'] ?? null;

        if ($raw === null || $raw === '') {
            return null;
        }

        if (! $setting->isSecret()) {
            return $raw;
        }

        try {
            return Crypt::decryptString($raw);
        } catch (DecryptException) {
            return null;
        }
    }

    /**
     * Whether a key is saved but cannot be decrypted with the current `APP_KEY`: it has to be written again.
     *
     * @param  InstallationSetting  $setting  the setting
     */
    public function unreadable(InstallationSetting $setting): bool
    {
        $raw = $this->rows()[$setting->value]['value'] ?? null;

        return $setting->isSecret() && filled($raw) && $this->stored($setting) === null;
    }

    /**
     * When the setting was last written, null when it is not in the database.
     *
     * @param  InstallationSetting  $setting  the setting
     */
    public function updatedAt(InstallationSetting $setting): ?CarbonImmutable
    {
        $at = $this->rows()[$setting->value]['updated_at'] ?? null;

        return $at === null ? null : CarbonImmutable::parse($at);
    }

    /**
     * Writes a setting; an empty value deletes the row (the `.env` counts again). Keys are stored encrypted.
     *
     * @param  InstallationSetting  $setting  the setting
     * @param  string|null  $value  the new value
     */
    public function set(InstallationSetting $setting, ?string $value): void
    {
        if ($value === null || $value === '') {
            AppSetting::query()->whereKey($setting->value)->delete();
        } else {
            AppSetting::query()->updateOrCreate(
                ['key' => $setting->value],
                ['value' => $setting->isSecret() ? Crypt::encryptString($value) : $value, 'updated_at' => now()],
            );
        }

        Cache::forget(self::CACHE_KEY);
        $this->rows = null;
    }

    /**
     * Copies the database values into `config`. The provider `off` writes null, which turns the reading off even
     * when the `.env` turns it on. Does nothing before the table exists (e.g. while `migrate` runs on an empty database).
     * The rows are read again, and the AI providers built with a key or an address that changed are forgotten, so a
     * queue worker running for days uses what the panel saved.
     */
    public function apply(): void
    {
        // The `.env` values as the process started: a worker lives for days and a removed setting must go back to them
        $this->original ??= $this->snapshot();
        $this->rows = null;
        $changed = [];

        foreach (InstallationSetting::cases() as $setting) {
            $configKey = $setting->configKey();

            if ($configKey === null) {
                continue;
            }

            $stored = $this->stored($setting);
            $value = match (true) {
                $stored === null => $this->original[$configKey] ?? null,
                $setting === InstallationSetting::Provider && $stored === 'off' => null,
                $setting === InstallationSetting::MonthlyLimit => (int) $stored,
                default => $stored,
            };

            if (str_starts_with($configKey, 'ai.providers.') && Config::get($configKey) !== $value) {
                $changed[] = explode('.', $configKey)[2];
            }

            Config::set($configKey, $value);
        }

        $this->forgetProviders(array_values(array_unique($changed)));
    }

    /**
     * Forgets the AI providers already built: the Laravel AI SDK keeps each one with the configuration it had, and
     * builds it again from `config` at the next use.
     *
     * @param  list<string>  $names  the providers whose configuration changed
     */
    private function forgetProviders(array $names): void
    {
        if ($names !== [] && app()->resolved(AiManager::class)) {
            resolve(AiManager::class)->forgetInstance($names);
        }
    }

    /**
     * The time of the morning round (`reminders:run`), as `HH:MM`.
     */
    public function remindersTime(): string
    {
        return $this->get(InstallationSetting::RemindersTime) ?? '06:00';
    }

    /**
     * The time of the nightly refresh of the loans (`loans:refresh`), as `HH:MM`.
     */
    public function loansTime(): string
    {
        return $this->get(InstallationSetting::LoansTime) ?? self::LOANS_TIME;
    }

    /**
     * All the rows, by key, cached for a minute and kept in this object until the next `apply()` or `set()`. Empty
     * (and not cached nor kept) when the table does not exist yet, so the first read after `migrate` sees the real rows.
     *
     * @return array<string, array{value: ?string, updated_at: ?string}>
     */
    private function rows(): array
    {
        if ($this->rows !== null) {
            return $this->rows;
        }

        try {
            // The table first: before `migrate` the cache table of the database store may be missing too
            if (! Schema::hasTable('app_settings')) {
                return [];
            }

            /** @var array<string, array{value: ?string, updated_at: ?string}>|null $cached */
            $cached = Cache::get(self::CACHE_KEY);

            if ($cached !== null) {
                return $this->rows = $cached;
            }

            $rows = [];

            foreach (AppSetting::query()->get() as $row) {
                $rows[$row->key] = ['value' => $row->value, 'updated_at' => $row->updated_at?->toDateTimeString()];
            }

            Cache::put(self::CACHE_KEY, $rows, 60);

            return $this->rows = $rows;
        } catch (QueryException|PDOException) {
            // Database unreachable or cache table missing: the `.env` values apply and nothing is cached
            return [];
        }
    }
}

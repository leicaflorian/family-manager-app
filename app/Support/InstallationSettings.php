<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\InstallationSetting;
use App\Models\AppSetting;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;

/**
 * The settings of the installation: the value in the database (written from the admin panel) wins, the `.env`
 * (through `config`) counts when the setting is empty. `apply()` copies the database values into `config`, so
 * the Laravel AI SDK and the rest of the app keep reading `config` unchanged.
 */
final class InstallationSettings
{
    private const string CACHE_KEY = 'installation-settings';

    private const string LOANS_TIME = '00:05';

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
    }

    /**
     * Copies the database values into `config`. The provider `off` writes null, which turns the reading off even
     * when the `.env` turns it on. Does nothing before the table exists (e.g. while `migrate` runs on an empty database).
     */
    public function apply(): void
    {
        foreach (InstallationSetting::cases() as $setting) {
            $configKey = $setting->configKey();
            $stored = $this->stored($setting);

            if ($configKey === null || $stored === null) {
                continue;
            }

            Config::set($configKey, match (true) {
                $setting === InstallationSetting::Provider && $stored === 'off' => null,
                $setting === InstallationSetting::MonthlyLimit => (int) $stored,
                default => $stored,
            });
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
     * All the rows, by key, cached for a minute. Empty (and not cached) when the table does not exist yet, so the
     * first read after `migrate` sees the real rows.
     *
     * @return array<string, array{value: ?string, updated_at: ?string}>
     */
    private function rows(): array
    {
        /** @var array<string, array{value: ?string, updated_at: ?string}>|null $cached */
        $cached = Cache::get(self::CACHE_KEY);

        if ($cached !== null) {
            return $cached;
        }

        if (! Schema::hasTable('app_settings')) {
            return [];
        }

        $rows = [];

        foreach (AppSetting::query()->get() as $row) {
            $rows[$row->key] = ['value' => $row->value, 'updated_at' => $row->updated_at?->toDateTimeString()];
        }

        Cache::put(self::CACHE_KEY, $rows, 60);

        return $rows;
    }
}

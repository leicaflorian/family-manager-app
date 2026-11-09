<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * A setting of the installation that the admin panel can write in the database and that wins over the `.env`.
 */
enum InstallationSetting: string
{
    case Provider = 'reader_provider';
    case Model = 'reader_model';
    case GeminiKey = 'gemini_key';
    case AnthropicKey = 'anthropic_key';
    case OllamaUrl = 'ollama_url';
    case OllamaKey = 'ollama_key';
    case MonthlyLimit = 'reader_monthly_limit';
    case RemindersTime = 'reminders_time';
    case LoansTime = 'loans_time';

    /**
     * Whether the value is a key: stored encrypted and never shown.
     */
    public function isSecret(): bool
    {
        return match ($this) {
            self::GeminiKey, self::AnthropicKey, self::OllamaKey => true,
            default => false,
        };
    }

    /**
     * The `config` key this setting replaces, null when the setting has none (the loans time is fixed in code).
     */
    public function configKey(): ?string
    {
        return match ($this) {
            self::Provider => 'document_reader.provider',
            self::Model => 'document_reader.model',
            self::GeminiKey => 'ai.providers.gemini.key',
            self::AnthropicKey => 'ai.providers.anthropic.key',
            self::OllamaUrl => 'ai.providers.ollama.url',
            self::OllamaKey => 'ai.providers.ollama.key',
            self::MonthlyLimit => 'document_reader.monthly_limit',
            self::RemindersTime => 'reminders.time',
            self::LoansTime => null,
        };
    }
}

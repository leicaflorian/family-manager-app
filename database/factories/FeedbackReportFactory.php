<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\FeedbackKind;
use App\Models\Family;
use App\Models\FeedbackReport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeedbackReport>
 */
final class FeedbackReportFactory extends Factory
{
    /**
     * A problem without a file, sent from the home page of a phone.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'family_id' => Family::factory(),
            'kind' => FeedbackKind::Problem,
            'message' => fake()->sentence(),
            'page' => '/dashboard',
            'user_agent' => 'Mozilla/5.0 (iPhone)',
            'layout' => 'phone',
        ];
    }

    /**
     * A problem with a screenshot, device details and a server error.
     */
    public function withDiagnostics(): self
    {
        return $this->state(fn (): array => [
            'screenshot_path' => 'feedback/1/screenshot.webp',
            'screenshot_size' => 1024,
            'diagnostics' => [
                'client_build' => 'build-old',
                'server_version' => 'dev',
                'viewport' => ['width' => 390, 'height' => 844, 'dpr' => 3.0, 'orientation' => 'portrait'],
                'phone' => true, 'ios' => true, 'standalone' => true, 'theme' => 'dark', 'locale' => 'it',
                'online' => true, 'connection' => '4g', 'page_age_seconds' => 42, 'component' => 'documents/Show',
                'pages' => [['at' => '2026-11-15T10:20:00+01:00', 'path' => '/documents', 'component' => 'documents/Index']],
                'console' => [
                    ['at' => '2026-11-15T10:21:00+01:00', 'level' => 'error', 'message' => 'Boom <b>bad</b>', 'component' => 'documents/Show'],
                    ['at' => '2026-11-15T10:22:00+01:00', 'level' => 'warn', 'message' => 'Careful', 'component' => null],
                ],
                'requests' => [['at' => '2026-11-15T10:21:30+01:00', 'method' => 'POST', 'path' => '/documents/42', 'status' => 500, 'request_id' => 'req-abc-123']],
            ],
            'server_errors' => [[
                'at' => '2026-11-15T10:21:30+01:00', 'request_id' => 'req-abc-123', 'method' => 'POST', 'path' => '/documents/42',
                'class' => 'RuntimeException', 'message' => 'Kaput <i>x</i>', 'file' => 'app/Foo.php', 'line' => 12,
            ]],
        ]);
    }
}

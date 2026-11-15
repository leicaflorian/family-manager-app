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
            'diagnostics' => ['server_version' => 'dev', 'console' => [], 'requests' => [], 'pages' => []],
            'server_errors' => [],
        ]);
    }
}

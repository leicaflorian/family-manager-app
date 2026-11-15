<?php

declare(strict_types=1);

use App\Support\FeedbackDiagnostics;

test('a list keeps its last entries and a long message is cut', function (): void {
    $console = array_map(fn (int $i): array => ['at' => 'x', 'level' => 'error', 'message' => str_repeat('m', 900), 'component' => null, 'n' => $i], range(1, 80));
    $requests = array_map(fn (int $i): array => ['at' => 'x', 'method' => 'GET', 'path' => '/p'.$i, 'status' => 500, 'request_id' => null], range(1, 30));
    $pages = array_map(fn (int $i): array => ['at' => 'x', 'path' => '/p'.$i, 'component' => null], range(1, 15));

    $result = FeedbackDiagnostics::normalize(['console' => $console, 'requests' => $requests, 'pages' => $pages], 'v1');

    expect($result['console'])->toHaveCount(50)
        ->and(mb_strlen($result['console'][0]['message']))->toBe(500)
        ->and($result['requests'])->toHaveCount(20)
        ->and($result['requests'][19]['path'])->toBe('/p30')
        ->and($result['pages'])->toHaveCount(10)
        ->and($result['pages'][0]['path'])->toBe('/p6')
        ->and($result['server_version'])->toBe('v1');
});

test('unknown keys are dropped and wrong types become null', function (): void {
    $result = FeedbackDiagnostics::normalize([
        'cookies' => 'secret',
        'viewport' => ['width' => 'wide', 'height' => 800],
        'console' => [
            ['at' => 'x', 'level' => 'info', 'message' => 'no'],
            ['at' => 'x', 'level' => 'warn', 'message' => 'yes'],
        ],
    ], 'v1');

    expect($result)->not->toHaveKey('cookies')
        ->and($result['viewport']['width'])->toBeNull()
        ->and($result['viewport']['height'])->toBe(800)
        ->and($result['console'])->toHaveCount(1)
        ->and($result['console'][0]['level'])->toBe('warn')
        ->and($result['phone'])->toBeNull();
});

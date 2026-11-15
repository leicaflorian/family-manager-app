<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

test('every response carries a new request id', function (): void {
    $first = $this->get('/login')->headers->get('X-Request-Id');
    $second = $this->get('/login')->headers->get('X-Request-Id');

    expect(Str::isUuid((string) $first))->toBeTrue()
        ->and(Str::isUuid((string) $second))->toBeTrue()
        ->and($first)->not->toBe($second);
});

test('an incoming request id is ignored', function (): void {
    $response = $this->withHeader('X-Request-Id', 'chosen')->get('/login');

    expect($response->headers->get('X-Request-Id'))->not->toBe('chosen');
});

test('the request id is in the context of the request', function (): void {
    Route::middleware('web')->get('/_ctx', fn () => Context::get('request_id'));

    $response = $this->get('/_ctx');

    expect($response->getContent())->toBe($response->headers->get('X-Request-Id'));
});

test('a signed-in account is in the context and a guest has neither id', function (): void {
    Route::middleware('web')->get('/_ctx', fn (): string|false => json_encode([
        'user' => Context::get('user_id'),
        'family' => Context::get('family_id'),
        'has_user' => Context::has('user_id'),
        'has_family' => Context::has('family_id'),
    ]));

    $member = User::factory()->for(registerFamily()->family)->create();

    $this->actingAs($member)->get('/_ctx')->assertJson(['user' => $member->id, 'family' => $member->family_id]);

    Context::flush();
    $this->app['auth']->forgetGuards();
    $this->get('/_ctx')->assertJson(['has_user' => false, 'has_family' => false]);
});

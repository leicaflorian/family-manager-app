<?php

declare(strict_types=1);

use App\Actions\Diagnostics\RecordServerError;
use App\Models\Family;
use App\Models\ServerError;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    Route::middleware('web')->get('/_boom', function (): never {
        throw new RuntimeException(str_repeat('x', 900));
    });
});

test('a reported error leaves a row', function (): void {
    $member = User::factory()->for(registerFamily()->family)->create();

    $response = $this->actingAs($member)->get('/_boom?q=secret');

    $response->assertStatus(500);
    $row = ServerError::sole();
    expect($row->request_id)->toBe($response->headers->get('X-Request-Id'))
        ->and($row->user_id)->toBe($member->id)
        ->and($row->family_id)->toBe($member->family_id)
        ->and($row->method)->toBe('GET')
        ->and($row->path)->toBe('/_boom')
        ->and($row->class)->toBe(RuntimeException::class)
        ->and(mb_strlen($row->message))->toBe(500)
        ->and($row->file)->toContain('ServerErrorTest.php')
        ->and($row->line)->toBeInt();
});

test('an error before sign-in has no account', function (): void {
    $this->get('/_boom')->assertStatus(500);

    $row = ServerError::sole();
    expect($row->user_id)->toBeNull()->and($row->family_id)->toBeNull();
});

test('404, 403 and 422 leave no row', function (): void {
    Route::middleware('web')->get('/_forbidden', fn () => abort(403));
    Route::middleware('web')->post('/_invalid', fn () => request()->validate(['a' => 'required']));

    $this->get('/_missing')->assertNotFound();
    $this->get('/_forbidden')->assertForbidden();
    $this->postJson('/_invalid')->assertUnprocessable();

    expect(ServerError::count())->toBe(0);
});

test('a row that cannot be written does not hide the error', function (): void {
    ServerError::creating(fn () => throw new RuntimeException('db'));
    Log::spy();

    $this->get('/_boom')->assertStatus(500);

    Log::shouldHaveReceived('error')->withArgs(fn (string $message): bool => str_starts_with($message, 'xxxx'))->once();
});

test('rows older than 30 days are pruned', function (): void {
    $old = ServerError::factory()->create(['created_at' => now()->subDays(31)]);
    $recent = ServerError::factory()->create(['created_at' => now()->subDays(29)]);

    Artisan::call('model:prune', ['--model' => [ServerError::class]]);

    expect(ServerError::find($old->id))->toBeNull()->and(ServerError::find($recent->id))->not->toBeNull();
});

test('forReport takes only the account\'s rows of the 30 minutes before, and its failed requests', function (): void {
    $sentAt = now();
    $family = Family::factory()->create();
    $other = Family::factory()->create();
    $mine = User::factory()->for($family)->create();
    $stranger = User::factory()->for($other)->create();

    $inWindow = ServerError::factory()->create(['user_id' => $mine->id, 'family_id' => $family->id, 'created_at' => $sentAt->copy()->subMinutes(5)]);
    ServerError::factory()->create(['user_id' => $stranger->id, 'family_id' => $other->id, 'created_at' => $sentAt->copy()->subMinutes(5)]);
    ServerError::factory()->create(['user_id' => $mine->id, 'family_id' => $family->id, 'created_at' => $sentAt->copy()->subMinutes(31)]);
    $listed = ServerError::factory()->create(['user_id' => $mine->id, 'family_id' => $family->id, 'request_id' => 'req-mine', 'created_at' => $sentAt->copy()->subMinutes(40)]);
    ServerError::factory()->create(['user_id' => $stranger->id, 'family_id' => $other->id, 'request_id' => 'req-other', 'created_at' => $sentAt->copy()->subMinutes(40)]);

    $rows = ServerError::forReport($mine->id, $sentAt, ['req-mine', 'req-other']);

    expect($rows->pluck('id')->all())->toBe([$listed->id, $inWindow->id])
        ->and($inWindow->toReportArray())->toMatchArray([
            'request_id' => $inWindow->request_id, 'class' => $inWindow->class, 'message' => $inWindow->message,
        ])
        ->and($inWindow->toReportArray()['at'])->toBe($inWindow->created_at?->toIso8601String());
});

test('an error in a job keeps the account and request id but no method or path', function (): void {
    // A job inherits the context of the request that queued it, while its request() is the console fake
    Context::add(['request_id' => 'req-queued', 'user_id' => 7, 'family_id' => 3]);

    app(RecordServerError::class)->handle(new RuntimeException('late'));

    $row = ServerError::sole();
    expect($row->request_id)->toBe('req-queued')
        ->and($row->user_id)->toBe(7)
        ->and($row->method)->toBeNull()
        ->and($row->path)->toBeNull();
});

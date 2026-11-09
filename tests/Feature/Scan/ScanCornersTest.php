<?php

declare(strict_types=1);

use App\Contracts\DocumentReader;
use App\Enums\ReadingPurpose;
use App\Exceptions\Reading\ReadingFailed;
use App\Models\AiReading;
use App\Models\Family;
use App\Models\LinkedDevice;
use App\Models\User;
use App\Services\Reading\ReadingAllowance;
use App\Support\CurrentFamily;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Support\FakeDocumentReader;
use Tests\TestCase;

beforeEach(function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-10-25 19:04', 'Europe/Rome'));
    Storage::fake('local');
    Http::preventStrayRequests();
    $this->family = Family::factory()->create();
    $this->laura = User::factory()->for($this->family)->admin()->create();
    $this->sheet = [['x' => 0.1, 'y' => 0.1], ['x' => 0.9, 'y' => 0.1], ['x' => 0.9, 'y' => 0.9], ['x' => 0.1, 'y' => 0.9]];
    $this->reader = new FakeDocumentReader()->findsCorners($this->sheet);
    app()->instance(DocumentReader::class, $this->reader);
});

afterEach(fn () => CurrentFamily::clear());

/**
 * A small JPEG.
 */
function photoBytes(): string
{
    $image = imagecreatetruecolor(8, 8);
    ob_start();
    imagejpeg($image);

    return (string) ob_get_clean();
}

/**
 * Posts bytes to the corners route as a raw body.
 *
 * @param  TestCase  $test  the test
 * @param  string  $bytes  the body
 */
function postCorners(TestCase $test, string $bytes): TestResponse
{
    return $test->call('POST', route('scan.corners'), [], [], [], ['CONTENT_TYPE' => 'image/jpeg', 'HTTP_ACCEPT' => 'application/json'], $bytes);
}

/**
 * The readings the family has, whatever the family in use.
 */
function loggedReadings(): int
{
    return AiReading::query()->withoutGlobalScopes()->count();
}

test('a good answer gives the corners and writes a crop row for the account, without a file', function (): void {
    postCorners($this->actingAs($this->laura), photoBytes())
        ->assertOk()
        ->assertExactJson(['corners' => $this->sheet]);

    $row = AiReading::query()->withoutGlobalScopes()->sole();
    expect($row->purpose)->toBe(ReadingPurpose::Crop)
        ->and($row->user_id)->toBe($this->laura->id)
        ->and($row->family_id)->toBe($this->family->id)
        ->and($row->file_id)->toBeNull();
});

test('a crooked answer gives null', function (): void {
    $this->reader->findsCorners(null);

    postCorners($this->actingAs($this->laura), photoBytes())->assertOk()->assertExactJson(['corners' => null]);
});

test('a service that fails gives null, not an error page', function (): void {
    $this->reader->fails(ReadingFailed::busy(new RuntimeException('busy')));

    postCorners($this->actingAs($this->laura), photoBytes())->assertOk()->assertExactJson(['corners' => null]);
});

test('a member can find the corners too', function (): void {
    $rosa = User::factory()->for($this->family)->create();

    postCorners($this->actingAs($rosa), photoBytes())->assertOk()->assertJsonPath('corners.0.x', 0.1);
});

test('a linked phone can find the corners', function (): void {
    $device = LinkedDevice::factory()->for($this->family)->for($this->laura)->create(['last_used_at' => now()]);

    postCorners($this->actingAs($this->laura)->withSession(['linked_device_id' => $device->id]), photoBytes())
        ->assertOk()
        ->assertJsonPath('corners.2.y', 0.9);
});

test('somebody who is not signed in is sent away', function (): void {
    postCorners($this, photoBytes())->assertUnauthorized();
    expect(loggedReadings())->toBe(0);
});

test('a photo over 1 MB answers 413 and a body that is not a JPEG answers 400, with no call', function (): void {
    postCorners($this->actingAs($this->laura), photoBytes().str_repeat('0', 1024 * 1024))->assertStatus(413);
    postCorners($this->actingAs($this->laura), 'GIF89a not a photo')->assertStatus(400);
    postCorners($this->actingAs($this->laura), '')->assertStatus(400);

    expect(loggedReadings())->toBe(0);
});

test('a photo of exactly 1 MB is accepted', function (): void {
    $bytes = photoBytes();
    $bytes .= str_repeat("\0", 1024 * 1024 - strlen($bytes));

    postCorners($this->actingAs($this->laura), $bytes)->assertOk();
});

test('the photo is not saved', function (): void {
    postCorners($this->actingAs($this->laura), photoBytes())->assertOk();

    expect(Storage::disk('local')->allFiles())->toBe([]);
});

test('with the reading off the answer is 503 and nothing is counted', function (): void {
    app()->instance(DocumentReader::class, new FakeDocumentReader()->off());

    postCorners($this->actingAs($this->laura), photoBytes())
        ->assertStatus(503)
        ->assertJsonPath('message', 'The edges are set by hand');
    expect(loggedReadings())->toBe(0);
});

test('the 51st of the day answers 429 and writes nothing, and the first of the next day goes', function (): void {
    $this->actingAs($this->laura);
    foreach (range(1, 50) as $ignored) {
        postCorners($this, photoBytes())->assertOk();
    }

    postCorners($this, photoBytes())->assertStatus(429)->assertJsonPath('message', 'The edges are set by hand for today');
    expect(loggedReadings())->toBe(50);

    // still the same day of Rome at 23:59
    Date::setTestNow(CarbonImmutable::parse('2026-10-25 23:59', 'Europe/Rome'));
    postCorners($this, photoBytes())->assertStatus(429);

    Date::setTestNow(CarbonImmutable::parse('2026-10-26 00:01', 'Europe/Rome'));
    postCorners($this, photoBytes())->assertOk();
    expect(loggedReadings())->toBe(51);
});

test('the limit is for each account', function (): void {
    $rosa = User::factory()->for($this->family)->create();
    foreach (range(1, 50) as $ignored) {
        postCorners($this->actingAs($this->laura), photoBytes())->assertOk();
    }

    postCorners($this->actingAs($this->laura), photoBytes())->assertStatus(429);
    postCorners($this->actingAs($rosa), photoBytes())->assertOk();
});

test('the corners do not count in the readings of the month', function (): void {
    config(['document_reader.monthly_limit' => 5]);
    postCorners($this->actingAs($this->laura), photoBytes())->assertOk();

    $left = CurrentFamily::using($this->family->id, fn (): int => resolve(ReadingAllowance::class)->left(now()));
    expect($left)->toBe(5);
});

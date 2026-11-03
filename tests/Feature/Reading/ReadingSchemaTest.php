<?php

declare(strict_types=1);

use App\Enums\FileSource;
use App\Enums\ReadingPurpose;
use App\Enums\ReadingStatus;
use App\Models\AiReading;
use App\Models\Family;
use App\Models\File;
use App\Models\User;

test('a deleted file leaves its reading', function (): void {
    $family = Family::factory()->create();
    $this->actingAs(User::factory()->for($family)->create());
    $file = File::factory()->for($family)->create();
    $reading = AiReading::factory()->for($family)->create(['file_id' => $file->id]);

    $file->delete();

    expect($reading->fresh()?->file_id)->toBeNull();
});

test('a deleted account leaves its reading', function (): void {
    $family = Family::factory()->create();
    $user = User::factory()->for($family)->create();
    $this->actingAs($user);
    $reading = AiReading::factory()->for($family)->create(['user_id' => $user->id]);

    $user->delete();

    expect($reading->fresh()?->user_id)->toBeNull();
});

test('a reading is never changed', function (): void {
    $family = Family::factory()->create();
    $this->actingAs(User::factory()->for($family)->create());
    $reading = AiReading::factory()->for($family)->create();

    $reading->update(['model' => 'other']);
})->throws(LogicException::class);

test('a reading has no update time and keeps its purpose as an enum', function (): void {
    $family = Family::factory()->create();
    $this->actingAs(User::factory()->for($family)->create());
    $reading = AiReading::factory()->for($family)->create(['purpose' => ReadingPurpose::Crop])->fresh();

    expect($reading?->purpose)->toBe(ReadingPurpose::Crop)
        ->and($reading?->created_at)->not->toBeNull()
        ->and($reading?->getAttributes())->not->toHaveKey('updated_at');
});

test('a file reads its reading as an array and its states as enums', function (): void {
    $family = Family::factory()->create();
    $this->actingAs(User::factory()->for($family)->create());
    $file = File::factory()->for($family)->create([
        'reading_status' => ReadingStatus::Done,
        'reading' => ['title' => 'Bolletta', 'tags' => ['luce']],
    ])->fresh();

    expect($file?->reading)->toEqual(['title' => 'Bolletta', 'tags' => ['luce']])
        ->and($file?->reading_status)->toBe(ReadingStatus::Done)
        ->and($file?->source)->toBe(FileSource::Upload)
        ->and(File::factory()->for($family)->create()->fresh()?->reading_status)->toBeNull();
});

test('a file lists its AI readings', function (): void {
    $family = Family::factory()->create();
    $this->actingAs(User::factory()->for($family)->create());
    $file = File::factory()->for($family)->create();
    AiReading::factory()->for($family)->count(2)->create(['file_id' => $file->id]);

    expect($file->aiReadings)->toHaveCount(2);
});

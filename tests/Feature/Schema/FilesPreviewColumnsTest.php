<?php

declare(strict_types=1);

use App\Models\File;
use Illuminate\Support\Facades\Schema;

test('files has the preview columns', function (): void {
    expect(Schema::hasColumns('files', ['preview_path', 'preview_failed']))->toBeTrue()
        ->and(Schema::getColumnType('files', 'preview_path'))->toBe('varchar');
});

test('a new file has no preview and has not failed', function (): void {
    $file = File::factory()->create()->refresh();

    expect($file->preview_path)->toBeNull()
        ->and($file->preview_failed)->toBeFalse();
});

test('the preview columns are not mass assignable', function (): void {
    $file = new File;
    $file->fill(['preview_path' => 'x.jpg', 'preview_failed' => true]);

    expect($file->preview_path)->toBeNull()
        ->and($file->preview_failed)->toBeNull();
});

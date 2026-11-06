<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Document;
use App\Models\File;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    Storage::fake('local');
    $this->marco = registerFamily();
    $this->rosa = User::factory()->for($this->marco->family)->create(['role' => Role::Member, 'email_verified_at' => now()]);
});

/**
 * Uploads one file as an account and returns its row.
 */
function uploadAs(User $user, string $name, string $content): File
{
    test()->actingAs($user)->post(route('upload.store'), ['files' => [UploadedFile::fake()->createWithContent($name, $content)]])
        ->assertSessionHasNoErrors();

    return File::query()->latest('id')->firstOrFail();
}

test('a member deletes only his files and the copies', function (): void {
    $marcos = uploadAs($this->marco, 'Bolletta Edison.pdf', 'the bill of october');
    $rosas = uploadAs($this->rosa, 'Pagella Giulia.pdf', 'the school report');
    $copy = uploadAs($this->marco, 'Bolletta di nuovo.pdf', 'the bill of october');

    $this->actingAs($this->rosa)->get(route('import', ['files' => "{$marcos->id},{$rosas->id},{$copy->id}"]))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('files.0.can_delete', false)
        ->where('files.1.can_delete', true)
        ->where('files.2.can_delete', true));

    $this->actingAs($this->rosa)->delete(route('upload.destroy', $marcos))->assertForbidden();
    $this->actingAs($this->rosa)->from(route('to-sort'))->delete(route('upload.destroy', $rosas))->assertRedirect(route('to-sort'));
    $this->actingAs($this->rosa)->from(route('to-sort'))->delete(route('upload.destroy', $copy))->assertRedirect(route('to-sort'));

    expect($marcos->fresh())->not->toBeNull()->and($rosas->fresh())->toBeNull()->and($copy->fresh())->toBeNull();
});

test('an admin deletes every waiting file', function (): void {
    $rosas = uploadAs($this->rosa, 'Pagella Giulia.pdf', 'the school report');

    $this->actingAs($this->marco)->get(route('import', ['files' => (string) $rosas->id]))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('files.0.can_delete', true));
    $this->actingAs($this->marco)->from(route('to-sort'))->delete(route('upload.destroy', $rosas))->assertRedirect(route('to-sort'));

    expect($rosas->fresh())->toBeNull();
});

test('nobody deletes a file that has its document', function (): void {
    $file = uploadAs($this->rosa, 'Pagella Giulia.pdf', 'the school report');
    Document::factory()->for($this->marco->family)->create(['file_id' => $file->id]);
    $file->refresh();

    expect($this->marco->can('delete', $file))->toBeFalse()
        ->and($this->rosa->can('delete', $file))->toBeFalse();
});

test('everyone sees a file, and who files documents reads it', function (): void {
    $file = uploadAs($this->marco, 'Bolletta Edison.pdf', 'the bill of october');

    expect($this->rosa->can('view', $file))->toBeTrue()
        ->and($this->rosa->can('read', $file))->toBeTrue()
        ->and($this->marco->can('read', $file))->toBeTrue();
});

test('everyone of the family imports a waiting file, not one already filed nor one of another family', function (): void {
    $waiting = uploadAs($this->rosa, 'Fattura Studio Ferri.pdf', 'the invoice');
    $filed = uploadAs($this->marco, 'Bolletta Edison.pdf', 'the bill of october');
    $filed->update(['to_sort' => false]);
    $foreign = File::factory()->create(['to_sort' => true]);

    expect($this->rosa->can('import', $waiting))->toBeTrue()
        ->and($this->marco->can('import', $waiting))->toBeTrue()
        ->and($this->marco->can('import', $filed))->toBeFalse()
        ->and($this->marco->can('import', $foreign))->toBeFalse();
});

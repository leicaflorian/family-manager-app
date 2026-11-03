<?php

declare(strict_types=1);

use App\Models\DocumentCategory;
use App\Models\ExpenseCategory;
use App\Models\Family;
use App\Models\File;
use App\Models\Organization;
use App\Models\Person;
use App\Services\Reading\ReadingContext;
use App\Services\Reading\ReadingPrompt;
use App\Support\CurrentFamily;

beforeEach(function (): void {
    $this->bianchi = Family::factory()->create(['name' => 'Bianchi']);
    $this->rossi = Family::factory()->create(['name' => 'Rossi']);
});

afterEach(fn () => CurrentFamily::clear());

test('the lists are only those of the family of the file', function (): void {
    CurrentFamily::using($this->rossi->id, function (): void {
        DocumentCategory::factory()->for($this->rossi)->create(['name' => 'Rossi documents']);
        ExpenseCategory::factory()->for($this->rossi)->create(['name' => 'Rossi expenses']);
        Organization::factory()->for($this->rossi)->create(['name' => 'Rossi Bank']);
        Person::factory()->for($this->rossi)->create(['first_name' => 'Rocco', 'last_name' => 'Rossi']);
    });
    $file = CurrentFamily::using($this->bianchi->id, function (): File {
        $parent = DocumentCategory::factory()->for($this->bianchi)->create(['name' => 'Casa']);
        DocumentCategory::factory()->for($this->bianchi)->create(['name' => 'Luce', 'parent_id' => $parent->id]);
        ExpenseCategory::factory()->for($this->bianchi)->create(['name' => 'Salute']);
        Organization::factory()->for($this->bianchi)->create(['name' => 'Enel']);
        Person::factory()->for($this->bianchi)->create(['first_name' => 'Laura', 'last_name' => 'Bianchi']);

        return File::factory()->for($this->bianchi)->create(['original_name' => 'scan.pdf']);
    });

    // Asked from the other family: the file decides which family's lists are read
    $context = CurrentFamily::using($this->rossi->id, fn (): ReadingContext => ReadingContext::for($file));

    $labels = fn (array $list): array => array_column($list, 'label');
    expect($labels($context->documentCategories))->toBe(['Casa', 'Casa › Luce'])
        ->and($labels($context->expenseCategories))->toBe(['Salute'])
        ->and($labels($context->organizations))->toBe(['Enel'])
        ->and($labels($context->people))->toBe(['Laura Bianchi'])
        ->and($context->fileName)->toBe('scan.pdf');
});

test('the note of the file reaches the instructions', function (): void {
    CurrentFamily::set($this->bianchi->id);
    Person::factory()->for($this->bianchi)->create(['first_name' => 'Giulia', 'last_name' => 'Bianchi']);
    $file = File::factory()->for($this->bianchi)->create([
        'original_name' => 'Telefono 2026-10-25 19.04.pdf',
        'note' => 'Caricato dal computer il 25/10/2026 da Laura: «dentista Giulia»',
    ]);

    $text = ReadingPrompt::instructions(ReadingContext::for($file), 'it');

    expect($text)->toContain('Giulia Bianchi')
        ->toContain('Telefono 2026-10-25 19.04.pdf')
        ->toContain('«dentista Giulia»');
});

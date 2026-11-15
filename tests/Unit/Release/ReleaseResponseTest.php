<?php

declare(strict_types=1);

use App\Enums\ReleaseBump;
use App\Services\Release\InvalidReleaseResponse;
use App\Services\Release\ReleaseResponse;

/**
 * A valid answer, with some fields replaced.
 *
 * @param  array<string, mixed>  $override
 */
function releaseAnswer(array $override = []): string
{
    return json_encode(array_replace([
        'bump' => 'minor',
        'reason' => 'A new page.',
        'changelog' => "### Added\n- A new page",
        'notes' => ['it' => ['Una pagina nuova'], 'en' => ['A new page']],
    ], $override), JSON_THROW_ON_ERROR);
}

test('a valid answer is read with its fields', function (): void {
    $response = ReleaseResponse::fromJson(releaseAnswer());

    expect($response->bump)->toBe(ReleaseBump::Minor)
        ->and($response->reason)->toBe('A new page.')
        ->and($response->changelog)->toBe("### Added\n- A new page")
        ->and($response->notes)->toBe(['it' => ['Una pagina nuova'], 'en' => ['A new page']]);
});

test('a patch answer is read', function (): void {
    expect(ReleaseResponse::fromJson(releaseAnswer(['bump' => 'patch']))->bump)->toBe(ReleaseBump::Patch);
});

test('a wrong answer is refused and the message names the field', function (string $json, string $field): void {
    expect(fn (): ReleaseResponse => ReleaseResponse::fromJson($json))->toThrow(InvalidReleaseResponse::class, $field);
})->with([
    'not json' => ['not json', 'JSON'],
    'major' => [fn (): string => releaseAnswer(['bump' => 'major']), 'bump'],
    'empty changelog' => [fn (): string => releaseAnswer(['changelog' => '']), 'changelog'],
    'empty reason' => [fn (): string => releaseAnswer(['reason' => ' ']), 'reason'],
    'no italian notes' => [fn (): string => releaseAnswer(['notes' => ['it' => [], 'en' => ['A']]]), 'notes.it'],
    'no english notes' => [fn (): string => releaseAnswer(['notes' => ['it' => ['A']]]), 'notes.en'],
    'note not a string' => [fn (): string => releaseAnswer(['notes' => ['it' => [3], 'en' => ['A']]]), 'notes.it'],
    'nine italian notes' => [fn (): string => releaseAnswer(['notes' => ['it' => array_fill(0, 9, 'A'), 'en' => ['A']]]), 'notes.it'],
    'nine english notes' => [fn (): string => releaseAnswer(['notes' => ['it' => ['A'], 'en' => array_fill(0, 9, 'A')]]), 'notes.en'],
    'empty note' => [fn (): string => releaseAnswer(['notes' => ['it' => ['A'], 'en' => ['']]]), 'notes.en'],
]);

test('the schema and the parser agree', function (): void {
    $schema = json_decode((string) file_get_contents(base_path('resources/release/response.schema.json')), true, flags: JSON_THROW_ON_ERROR);

    expect($schema['required'])->toBe(['bump', 'reason', 'changelog', 'notes'])
        ->and($schema['properties']['bump']['enum'])->toBe(['minor', 'patch'])
        ->and($schema['properties']['notes']['required'])->toBe(['it', 'en'])
        ->and($schema['additionalProperties'])->toBeFalse()
        ->and($schema['properties']['notes']['properties']['it']['minItems'])->toBe(1)
        ->and($schema['properties']['notes']['properties']['en']['minItems'])->toBe(1)
        ->and($schema['properties']['notes']['properties']['it']['maxItems'])->toBe(8)
        ->and($schema['properties']['notes']['properties']['en']['maxItems'])->toBe(8);
});

test('the schema names no meta-schema, which the claude CLI may not know', function (): void {
    // `claude --json-schema` stops with "no schema with key or ref" on the draft 2020-12 URI.
    $schema = json_decode((string) file_get_contents(base_path('resources/release/response.schema.json')), true, flags: JSON_THROW_ON_ERROR);

    expect($schema)->not->toHaveKey('$schema');
});

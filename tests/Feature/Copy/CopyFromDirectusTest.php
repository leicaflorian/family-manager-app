<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

/** The fake Directus: a database of the MySQL of Sail, `testing_directus`. */
const DIRECTUS_TEST_DATABASE = 'testing_directus';

beforeEach(function (): void {
    Storage::fake('local');

    $mysql = config('database.connections.mysql');
    config(['database.connections.directus_fixture' => $mysql]);
    $fixture = DB::connection('directus_fixture');
    $fixture->statement('DROP DATABASE IF EXISTS '.DIRECTUS_TEST_DATABASE);
    $fixture->statement('CREATE DATABASE '.DIRECTUS_TEST_DATABASE);
    $fixture->unprepared('USE '.DIRECTUS_TEST_DATABASE.";\n".File::get(base_path('tests/Fixtures/directus.sql')));
    DB::purge('directus_fixture');

    config(['database.connections.directus' => [...$mysql, 'database' => DIRECTUS_TEST_DATABASE, 'options' => config('database.connections.directus.options')]]);
    DB::purge('directus');

    $this->uploads = storage_path('framework/testing/directus-uploads');
    File::deleteDirectory($this->uploads);
    File::ensureDirectoryExists($this->uploads);
    File::put("{$this->uploads}/aaaaaaaa-0000-0000-0000-000000000001.pdf", '%PDF-1.4 bolletta');
    config(['directus.uploads' => $this->uploads]);
});

afterEach(function (): void {
    File::deleteDirectory($this->uploads);
});

/**
 * How many rows of the family named Verdi a table has.
 *
 * @param  string  $table  a table with `family_id`
 */
function ofVerdi(string $table): int
{
    $family = DB::table('families')->where('name', 'Verdi')->value('id');

    return DB::table($table)->where('family_id', $family)->count();
}

/**
 * The rows the copy leaves in each table, the same at every copy.
 *
 * @return array<string, int>
 */
function verdiCounts(): array
{
    return collect(['files', 'users', 'people', 'organizations', 'document_categories', 'documents', 'utilities', 'reminders', 'family_settings'])
        ->mapWithKeys(fn (string $table): array => [$table => ofVerdi($table)])
        ->put('documentables', DB::table('documentables')->count())
        ->put('remindables', DB::table('remindables')->count())
        ->all();
}

/** The counts of the fake Directus, once copied. */
const VERDI_COUNTS = [
    'files' => 3, 'users' => 1, 'people' => 3, 'organizations' => 2, 'document_categories' => 3, 'documents' => 3,
    'utilities' => 1, 'reminders' => 2, 'family_settings' => 1, 'documentables' => 2, 'remindables' => 1,
];

test('the Directus connection is read only', function (): void {
    expect(DB::connection('directus')->table('people')->count())->toBe(3);
    expect(fn () => DB::connection('directus')->table('people')->insert(['first_name' => 'Mario']))
        ->toThrow(QueryException::class);
});

test('the copy brings the family with its records, links, categories and accounts, without a document that has no file or an account without email', function (): void {
    $this->artisan('directus:copy', ['--family' => 'Verdi'])
        ->expectsOutputToContain('contratto-luce.pdf')
        ->expectsOutputToContain('Left out')
        ->expectsOutputToContain('Paolo Verdi')
        ->assertSuccessful()
        ->run();

    expect(verdiCounts())->toBe(VERDI_COUNTS);

    $family = DB::table('families')->where('name', 'Verdi')->sole();
    $laura = User::query()->where('email', 'laura.verdi@example.com')->sole();
    expect($laura->family_id)->toBe($family->id)
        ->and($laura->role)->toBe(Role::Admin)
        ->and($laura->name)->toBe('Laura Verdi')
        ->and($laura->email_verified_at)->not->toBeNull()
        ->and(DB::table('people')->where('first_name', 'Laura')->value('user_id'))->toBe($laura->id)
        ->and(DB::table('people')->where('first_name', 'Laura')->value('created_at'))->toBeNull()
        ->and(DB::table('people')->where('first_name', 'Paolo')->value('user_id'))->toBeNull();

    $casa = DB::table('document_categories')->where('name', 'Casa')->value('id');
    expect(DB::table('document_categories')->where('name', 'Bollette')->value('parent_id'))->toBe($casa);

    $bill = DB::table('documents')->where('title', 'Bolletta luce di ottobre')->sole();
    expect(DB::table('people')->where('id', $bill->person_id)->value('first_name'))->toBe('Laura')
        ->and(DB::table('organizations')->where('id', $bill->organization_id)->value('name'))->toBe('Edison')
        ->and(DB::table('files')->where('id', $bill->file_id)->value('original_name'))->toBe('bolletta-ottobre.pdf')
        ->and(Storage::disk('local')->get(DB::table('files')->where('id', $bill->file_id)->value('path')))->toBe('%PDF-1.4 bolletta');

    $utility = DB::table('utilities')->sole();
    expect(DB::table('remindables')->sole())->remindable_type->toBe('utility')->remindable_id->toBe($utility->id)
        ->and(DB::table('family_settings')->sole()->mail_inbox_recipient)->toBe('documenti@example.com');
});

test('the copied family reads its Edison with both documents of Luce in the app', function (): void {
    $this->artisan('directus:copy', ['--family' => 'Verdi'])->assertSuccessful()->run();
    $laura = User::query()->where('email', 'laura.verdi@example.com')->sole();
    $edison = DB::table('organizations')->where('name', 'Edison')->value('id');

    $this->actingAs($laura)->get(route('organizations.show', $edison))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('organization.types', ['utility_provider'])
        ->where('links.0.type', 'utility')
        ->has('documents', 2));
});

test('a file missing in the uploads is listed, with its storage when it is not local, and its document opens to 404', function (): void {
    $this->artisan('directus:copy', ['--family' => 'Verdi'])
        ->expectsOutputToContain('contratto-luce.pdf (aaaaaaaa-0000-0000-0000-000000000002)')
        ->expectsOutputToContain('tessera.jpg (aaaaaaaa-0000-0000-0000-000000000003, on s3)')
        ->assertSuccessful()
        ->run();
    $laura = User::query()->where('email', 'laura.verdi@example.com')->sole();
    $contract = DB::table('documents')->where('title', 'Contratto luce')->value('file_id');

    $this->actingAs($laura)->get(route('files.show', $contract))->assertNotFound();
});

test('a second copy without --replace stops and duplicates nothing', function (): void {
    $this->artisan('directus:copy', ['--family' => 'Verdi'])->assertSuccessful()->run();

    $this->artisan('directus:copy', ['--family' => 'Verdi'])->assertFailed()->run();

    expect(DB::table('families')->where('name', 'Verdi')->count())->toBe(1)
        ->and(verdiCounts())->toBe(VERDI_COUNTS);
});

test('with --replace the family is made again, with the same numbers and without its old files', function (): void {
    $this->artisan('directus:copy', ['--family' => 'Verdi'])->assertSuccessful()->run();
    $old = DB::table('families')->where('name', 'Verdi')->value('id');

    $this->artisan('directus:copy', ['--family' => 'Verdi', '--replace' => true])->assertSuccessful()->run();

    expect(DB::table('families')->where('name', 'Verdi')->count())->toBe(1)
        ->and(DB::table('families')->where('id', $old)->exists())->toBeFalse()
        ->and(Storage::disk('local')->directories('families'))->toHaveCount(1)
        ->and(verdiCounts())->toBe(VERDI_COUNTS);
});

test('with --without-files the rows of the files are there, without content', function (): void {
    $this->artisan('directus:copy', ['--family' => 'Verdi', '--without-files' => true])->assertSuccessful()->run();

    expect(ofVerdi('files'))->toBe(3)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

test('the copy stops when an email of the accounts is already in the app', function (): void {
    registerFamily('Rossi', 'laura.verdi@example.com');

    $this->artisan('directus:copy', ['--family' => 'Verdi'])
        ->expectsOutputToContain('laura.verdi@example.com')
        ->assertFailed()
        ->run();

    expect(DB::table('families')->where('name', 'Verdi')->exists())->toBeFalse();
});

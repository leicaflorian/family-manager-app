<?php

declare(strict_types=1);

use App\Enums\Channel;
use App\Enums\Role;
use App\Models\Reminder;
use App\Models\ReminderGroup;
use App\Models\User;
use App\Support\CurrentFamily;
use App\Support\Reminders\NotificationRule;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
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
    'files' => 5, 'users' => 1, 'people' => 3, 'organizations' => 2, 'document_categories' => 3, 'documents' => 5,
    'utilities' => 1, 'reminders' => 4, 'family_settings' => 1, 'documentables' => 2, 'remindables' => 3,
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
    expect(DB::table('remindables')->where('remindable_type', 'utility')->sole())->remindable_type->toBe('utility')->remindable_id->toBe($utility->id)
        // written in Directus with capitals and spaces: the app finds the address in lower case
        ->and(DB::table('family_settings')->sole()->mail_inbox_recipient)->toBe('documenti@example.com');
});

test('the mail settings come over with the accounts told by id, and the switch for the mails with attachments', function (): void {
    $this->artisan('directus:copy', ['--family' => 'Verdi'])->assertSuccessful()->run();

    $laura = User::query()->where('email', 'laura.verdi@example.com')->sole();
    $settings = DB::table('family_settings')->sole();

    // the email without an account is left out, the AI filter is not a setting any more
    expect(json_decode((string) $settings->mail_inbox_notify, true))->toBe([$laura->id])
        ->and((bool) $settings->mail_inbox_notify_attachments_only)->toBeFalse()
        ->and(Schema::hasColumn('family_settings', 'mail_inbox_ai_filter'))->toBeFalse();
});

test('the mails and the log of the calls of Directus are not copied', function (): void {
    $this->artisan('directus:copy', ['--family' => 'Verdi'])->assertSuccessful()->run();

    expect(DB::table('received_mails')->count())->toBe(0)
        ->and(DB::table('mail_inbox_calls')->count())->toBe(0);
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

it('fills the occurrence of the copied expenses', function (): void {
    $this->artisan('directus:copy', ['--family' => 'Verdi'])->assertSuccessful()->run();

    $expense = DB::table('expenses')->where('title', 'Retta danza di ottobre')->sole();

    expect($expense->planned_expense_id)->not->toBeNull()
        ->and($expense->occurrence_date)->toBe('2026-10-05');
});

it('copies the card of a planned expense as a pair', function (): void {
    $this->artisan('directus:copy', ['--family' => 'Verdi'])->assertSuccessful()->run();

    $planned = DB::table('planned_expenses')->where('title', 'Bollo auto')->sole();

    expect($planned->card_type)->toBe('vehicle')
        ->and($planned->card_id)->toBe(DB::table('vehicles')->where('name', 'Panda')->value('id'));
});

it('keeps the first card of a planned expense with two', function (): void {
    $this->artisan('directus:copy', ['--family' => 'Verdi'])->assertSuccessful()->run();

    $planned = DB::table('planned_expenses')->where('title', 'Luce e auto')->sole();

    expect($planned->card_type)->toBe('utility')
        ->and($planned->card_id)->toBe(DB::table('utilities')->value('id'));
});

test('a second copy without --replace stops and duplicates nothing', function (): void {
    $this->artisan('directus:copy', ['--family' => 'Verdi'])->assertSuccessful()->run();

    $this->artisan('directus:copy', ['--family' => 'Verdi'])->expectsOutputToContain('already in the app')->assertFailed()->run();

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

test('a family of the same name that registered itself is never replaced', function (): void {
    $anna = registerFamily('Verdi', 'anna@example.com');

    $this->artisan('directus:copy', ['--family' => 'Verdi', '--replace' => true])
        ->expectsOutputToContain('was not copied from Directus')
        ->assertFailed()
        ->run();

    expect(DB::table('families')->where('id', $anna->family_id)->exists())->toBeTrue()
        ->and($anna->fresh())->not->toBeNull()
        ->and(DB::table('families')->where('name', 'Verdi')->count())->toBe(1);
});

test('with --without-files the rows of the files are there, without content', function (): void {
    $this->artisan('directus:copy', ['--family' => 'Verdi', '--without-files' => true])->assertSuccessful()->run();

    expect(ofVerdi('files'))->toBe(5)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

test('the copied files carry the checksum of their content, the source from the tags and no reading', function (): void {
    $this->artisan('directus:copy', ['--family' => 'Verdi'])->assertSuccessful()->run();

    $files = DB::table('files')->orderBy('original_name')->get()->keyBy('original_name');

    expect($files['bolletta-ottobre.pdf']->checksum)->toBe(hash('sha256', '%PDF-1.4 bolletta'))
        ->and($files['bolletta-ottobre.pdf']->source)->toBe('mail')
        ->and($files['contratto-luce.pdf']->source)->toBe('phone')
        ->and($files['tessera.jpg']->source)->toBe('upload')
        // Tags that are not JSON, or a list of things that are not names, say nothing: an upload
        ->and($files['ricevuta.pdf']->source)->toBe('upload')
        ->and($files['verbale.pdf']->source)->toBe('upload')
        // These two have no content in the uploads, so there is nothing to hash
        ->and($files['contratto-luce.pdf']->checksum)->toBeNull()
        ->and($files->pluck('reading_status')->filter()->all())->toBe([])
        ->and($files->pluck('reading')->filter()->all())->toBe([])
        ->and(DB::table('ai_readings')->count())->toBe(0);
});

test('with --without-files the copied files have no checksum', function (): void {
    $this->artisan('directus:copy', ['--family' => 'Verdi', '--without-files' => true])->assertSuccessful()->run();

    expect(DB::table('files')->whereNotNull('checksum')->count())->toBe(0)
        ->and(DB::table('files')->where('original_name', 'bolletta-ottobre.pdf')->value('source'))->toBe('mail');
});

test('the copy stops when an email of the accounts is already in the app', function (): void {
    registerFamily('Rossi', 'laura.verdi@example.com');

    $this->artisan('directus:copy', ['--family' => 'Verdi'])
        ->expectsOutputToContain('laura.verdi@example.com')
        ->assertFailed()
        ->run();

    expect(DB::table('families')->where('name', 'Verdi')->exists())->toBeFalse();
});

test('the groups, the notices sent and the links of the reminders are read by the app as Directus left them', function (): void {
    $this->artisan('directus:copy', ['--family' => 'Verdi'])->assertSuccessful()->run();
    $family = (int) DB::table('families')->where('name', 'Verdi')->value('id');
    $laura = (int) DB::table('people')->where('first_name', 'Laura')->value('id');
    $utility = (int) DB::table('utilities')->value('id');

    CurrentFamily::using($family, function () use ($laura, $utility): void {
        $group = ReminderGroup::query()->sole();
        $reminder = Reminder::query()->where('title', 'Leggere il contatore')->sole();
        $rules = NotificationRule::listOf($group->notifications);

        // The people of the recipients are those of the app, an id that was not copied is gone, nobody left is everybody, and an unknown channel is both
        expect($group->name)->toBe('Predefinito')
            ->and($group->is_default)->toBeTrue()
            ->and($rules)->toHaveCount(4)
            ->and(array_map(static fn (NotificationRule $rule): array => [$rule->offset, $rule->channels, $rule->people], $rules))
            ->toBe([[-7, Channel::Both, [$laura]], [0, Channel::App, [$laura]], [1, Channel::Both, null], [2, Channel::Email, null]])
            ->and($reminder->reminder_group_id)->toBe($group->id)
            ->and($reminder->notifications_sent)->toBe(['2026-11-10:-7'])
            ->and($reminder->link?->remindable_type)->toBe('utility')
            ->and($reminder->link?->remindable_id)->toBe($utility);
    });
});

test('after the copy a round does not send again the notices Directus marked, and sends the next one', function (): void {
    Mail::fake();
    $this->artisan('directus:copy', ['--family' => 'Verdi'])->assertSuccessful()->run();
    $family = DB::table('families')->where('name', 'Verdi')->value('id');

    // Seven days before: the notice Directus marked as sent stays quiet
    $this->artisan('reminders:run', ['--family' => $family, '--date' => '2026-11-03'])->expectsOutputToContain('Notices created: 0')->assertSuccessful()->run();
    Mail::assertNothingQueued();

    // The day itself: the notice of the day goes to Laura, who is the person of the group
    $this->artisan('reminders:run', ['--family' => $family, '--date' => '2026-11-10'])->expectsOutputToContain('Notices created: 1')->assertSuccessful()->run();
    $laura = User::query()->where('email', 'laura.verdi@example.com')->sole();

    expect(DB::table('reminder_notices')->where('user_id', $laura->id)->count())->toBe(1);
    expect(Reminder::query()->withoutGlobalScopes()->where('title', 'Leggere il contatore')->sole()->notifications_sent)->toBe(['2026-11-10:-7', '2026-11-10:0']);
});

test('after the copy the open automatic reminders take the new title and the done ones keep the Directus one', function (): void {
    $this->artisan('directus:copy', ['--family' => 'Verdi'])->assertSuccessful()->run();
    $family = (int) DB::table('families')->where('name', 'Verdi')->value('id');

    CurrentFamily::using($family, function (): void {
        $open = Reminder::query()->where('due_date', '2027-01-15')->sole();
        $done = Reminder::query()->where('due_date', '2026-06-01')->sole();

        expect($open->title)->toBe('Renew the document')
            ->and($open->person_id)->not->toBeNull()
            ->and(Reminder::query()->where('due_date', '2027-01-15')->count())->toBe(1)
            ->and($done->title)->toBe('Documento in scadenza · Tessera sanitaria · scade il 01/06/2026');
    });
});

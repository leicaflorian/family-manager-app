<?php

declare(strict_types=1);

use App\Actions\Calendar\ResetCalendarLink;
use App\Actions\Calendar\TurnOffCalendar;
use App\Actions\Calendar\TurnOnCalendar;
use App\Enums\DocumentStatus;
use App\Enums\ReminderStatus;
use App\Models\Document;
use App\Models\Expense;
use App\Models\InsurancePolicy;
use App\Models\PlannedExpense;
use App\Models\Reminder;
use App\Models\User;
use App\Support\CurrentFamily;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-10-26 10:00', 'Europe/Rome'));
});

afterEach(fn () => Date::setTestNow());

/**
 * Turns on the calendar of the family of an account.
 *
 * @param  User  $user  an account of the family
 * @param  string  $locale  the language of the page it is turned on in
 * @return string the secret
 */
function turnOnCalendar(User $user, string $locale = 'en'): string
{
    return (string) resolve(TurnOnCalendar::class)->handle($user->family, $locale);
}

/**
 * Reads the calendar of a secret, as a calendar app does: no account, no cookies.
 *
 * @param  string  $token  the secret
 */
function readCalendar(string $token): TestResponse
{
    return test()->get("/calendar/{$token}.ics");
}

/**
 * The events of a calendar text, by UID, with their lines unfolded and their texts unescaped.
 *
 * @param  string  $ics  the calendar text
 * @return array<string, array{date: string, summary: string, description: string}>
 */
function calendarEvents(string $ics): array
{
    $unfolded = str_replace("\r\n ", '', $ics);
    preg_match_all('/BEGIN:VEVENT\r\n(.*?)END:VEVENT/s', $unfolded, $blocks);
    $events = [];

    foreach ($blocks[1] as $block) {
        $fields = [];

        foreach (explode("\r\n", trim($block)) as $line) {
            [$name, $value] = explode(':', $line, 2);
            // One pass, so an escaped backslash before an «n» is not read as a line break
            $fields[$name] = (string) preg_replace_callback('/\\\\([\\\\,;nN])/', static fn (array $match): string => strtolower($match[1]) === 'n' ? "\n" : $match[1], $value);
        }

        $events[$fields['UID']] = [
            'date' => $fields['DTSTART;VALUE=DATE'],
            'summary' => $fields['SUMMARY'],
            'description' => $fields['DESCRIPTION'] ?? '',
        ];
    }

    return $events;
}

/**
 * The events of the calendar of a secret.
 *
 * @param  string  $token  the secret
 * @return array<string, array{date: string, summary: string, description: string}>
 */
function calendarEventsOf(string $token): array
{
    return calendarEvents((string) readCalendar($token)->assertOk()->getContent());
}

/**
 * The summaries of the calendar of a secret.
 *
 * @param  string  $token  the secret
 * @return list<string>
 */
function calendarSummaries(string $token): array
{
    return array_values(array_column(calendarEventsOf($token), 'summary'));
}

/**
 * An automatic reminder linked to a card.
 *
 * @param  Model  $card  the card
 * @param  string  $field  the kind of deadline
 * @param  string  $date  the deadline
 * @param  ReminderStatus  $status  open or done
 */
function automaticReminder(Model $card, string $field, string $date, ReminderStatus $status): Reminder
{
    $reminder = Reminder::factory()->create(['family_id' => CurrentFamily::id(), 'title' => 'Documento in scadenza · scade il 14/11/2026', 'is_automatic' => true, 'source_field' => $field, 'due_date' => $date, 'status' => $status]);
    DB::table('remindables')->insert(['reminder_id' => $reminder->id, 'remindable_type' => $card->getMorphClass(), 'remindable_id' => $card->getKey()]);

    return $reminder;
}

test('the five wrong cases answer 404 with the same body', function (): void {
    $marco = registerFamily();
    $token = turnOnCalendar($marco);

    // Italian in the browser: a 404 inside the route must read like one of an address that does not exist. Each
    // request starts from the default language, as a new request on the server does.
    $default = config()->string('app.locale');
    $notFound = function (string $url) use ($default): string {
        app()->setLocale($default);

        return (string) test()->withHeader('Accept-Language', 'it')->get($url)->assertNotFound()->getContent();
    };
    $wrongByOne = substr($token, 0, -1).(str_ends_with($token, 'a') ? 'b' : 'a');
    readCalendar($token)->assertOk();
    $bodies = [
        $notFound('/calendar/'.Str::random(40).'.ics'),
        $notFound("/calendar/{$wrongByOne}.ics"),
        $notFound("/calendar/{$token}"),
    ];

    $new = resolve(ResetCalendarLink::class)->handle($marco->family->refresh());
    $bodies[] = $notFound("/calendar/{$token}.ics");
    readCalendar($new)->assertOk();

    resolve(TurnOffCalendar::class)->handle($marco->family->refresh());
    $bodies[] = $notFound("/calendar/{$new}.ics");

    expect(array_unique($bodies))->toHaveCount(1);
});

test('it answers text/calendar with no-store and noindex', function (): void {
    $token = turnOnCalendar(registerFamily());

    $response = readCalendar($token)->assertOk();

    expect($response->headers->get('Content-Type'))->toBe('text/calendar; charset=utf-8')
        ->and($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($response->headers->get('X-Robots-Tag'))->toBe('noindex')
        ->and($response->getContent())->toStartWith("BEGIN:VCALENDAR\r\n");
});

test('a calendar poll leaves no session and no cookie', function (): void {
    $token = turnOnCalendar(registerFamily());

    $ok = readCalendar($token)->assertOk();
    $notFound = readCalendar(Str::random(40))->assertNotFound();

    expect($ok->headers->getCookies())->toBe([])
        ->and($ok->headers->has('Set-Cookie'))->toBeFalse()
        ->and($notFound->headers->getCookies())->toBe([])
        ->and($notFound->headers->has('Set-Cookie'))->toBeFalse();
});

test('commas, semicolons, backslashes and line breaks of a reminder come back as they were', function (): void {
    $marco = registerFamily();
    $reminder = insideFamilyOf($marco, fn (): Reminder => Reminder::factory()->create(['family_id' => CurrentFamily::id(), 'title' => 'a, b; c\\', 'due_date' => '2026-11-10', 'status' => ReminderStatus::Open, 'person_id' => null, 'notes' => "first, line; here\\\nsecond line"]));

    $event = calendarEventsOf(turnOnCalendar($marco))["reminder-{$reminder->id}@family-manager"];

    expect($event['summary'])->toBe('a, b; c\\')
        ->and($event['description'])->toBe("first, line; here\\\nsecond line\nOpen: ".route('agenda'));
});

test('the secret is never stored in clear', function (): void {
    $marco = registerFamily();
    $token = turnOnCalendar($marco);

    $row = (array) DB::table('families')->where('id', $marco->family_id)->first();

    expect($token)->toMatch('/^[A-Za-z0-9]{40}$/')
        ->and($row['calendar_token_hash'])->toBe(hash('sha256', $token))
        ->and($row['calendar_token'])->not->toBe($token)
        ->and(Crypt::decryptString($row['calendar_token']))->toBe($token)
        ->and(implode('|', array_map(strval(...), array_filter($row, is_scalar(...)))))->not->toContain($token)
        ->and($marco->family->refresh()->toArray())->not->toHaveKey('calendar_token');
});

test('an open reminder is an event, a done one is not', function (): void {
    $marco = registerFamily();
    [$open, $done] = insideFamilyOf($marco, fn (): array => [
        Reminder::factory()->create(['family_id' => CurrentFamily::id(), 'title' => 'Pagare il bollo · Panda · scade il 31/01/2027 · manca la spesa prevista', 'due_date' => '2027-01-31', 'status' => ReminderStatus::Open, 'person_id' => $marco->person->id, 'notes' => 'Bring the booklet']),
        Reminder::factory()->create(['family_id' => CurrentFamily::id(), 'title' => 'Call the plumber', 'due_date' => '2026-10-20', 'status' => ReminderStatus::Done]),
    ]);

    $events = calendarEventsOf(turnOnCalendar($marco));

    expect($events)->toHaveKey("reminder-{$open->id}@family-manager")
        ->not->toHaveKey("reminder-{$done->id}@family-manager")
        ->and($events["reminder-{$open->id}@family-manager"])->toBe([
            'date' => '20270131',
            'summary' => 'Pagare il bollo · Panda',
            'description' => "Expected expense missing.\nBring the booklet\nFor: Marco Bianchi\nOpen: ".route('agenda'),
        ]);
});

test('an open reminder of long ago is still an event', function (): void {
    $marco = registerFamily();
    $old = insideFamilyOf($marco, fn (): Reminder => Reminder::factory()->create(['family_id' => CurrentFamily::id(), 'title' => 'Call the plumber', 'due_date' => '2025-01-10', 'status' => ReminderStatus::Open, 'person_id' => null, 'notes' => null]));

    expect(calendarEventsOf(turnOnCalendar($marco))["reminder-{$old->id}@family-manager"])->toBe([
        'date' => '20250110',
        'summary' => 'Call the plumber',
        'description' => 'Open: '.route('agenda'),
    ]);
});

test('a payment of 30 days ago is there, one of 31 is not; a deadline 12 months ahead is there, one later is not', function (): void {
    $marco = registerFamily();
    $ids = insideFamilyOf($marco, function () use ($marco): array {
        $recent = agendaPlanned(null, 'Recent bill', 10, '2026-09-26', ['is_recurring' => false]);
        agendaPlanned(null, 'Old bill', 10, '2026-09-25', ['is_recurring' => false]);
        $near = Document::factory()->create(['family_id' => CurrentFamily::id(), 'title' => 'Passport in a year', 'status' => DocumentStatus::Active, 'person_id' => $marco->person->id, 'expiry_date' => '2027-10-26']);
        Document::factory()->create(['family_id' => CurrentFamily::id(), 'title' => 'Passport later', 'status' => DocumentStatus::Active, 'person_id' => $marco->person->id, 'expiry_date' => '2027-10-27']);

        return ['recent' => $recent->id, 'near' => $near->id];
    });

    $events = calendarEventsOf(turnOnCalendar($marco));

    expect(array_column($events, 'summary'))->toContain('Recent bill · €10.00', 'Passport in a year')
        ->not->toContain('Old bill · €10.00', 'Passport later')
        ->and($events)->toHaveKey("payment/{$ids['recent']}/2026-09-26@family-manager")
        ->toHaveKey("deadline/documents.expiry_date/{$ids['near']}/2027-10-26@family-manager")
        ->and($events["payment/{$ids['recent']}/2026-09-26@family-manager"]['description'])
        ->toEndWith(route('budget.deadlines.show', ['kind' => 'expense', 'id' => $ids['recent'], 'date' => '2026-09-26']).'?pay=1');
});

test('a deadline with its automatic reminder is only the reminder', function (): void {
    $marco = boardFamily();
    [$passport, $policy, $instalment] = insideFamilyOf($marco, function (): array {
        $passport = Document::factory()->create(['family_id' => CurrentFamily::id(), 'title' => 'Passaporto di Marco', 'status' => DocumentStatus::Active, 'expiry_date' => '2026-12-01']);
        $policy = InsurancePolicy::query()->where('expiry_date', '2026-11-21')->sole();

        return [
            automaticReminder($passport, 'documents.expiry_date', '2026-12-01', ReminderStatus::Open),
            automaticReminder($policy, 'insurance_policies.expiry_date', '2026-11-21', ReminderStatus::Open),
            PlannedExpense::query()->where('title', 'Rata RC auto')->sole(),
        ];
    });

    $events = calendarEventsOf(turnOnCalendar($marco));
    $summaries = array_column($events, 'summary');

    // The open reminders are the events of the passport and of the policy; the done one of the identity card leaves nothing
    expect($events)->toHaveKeys(["reminder-{$passport->id}@family-manager", "reminder-{$policy->id}@family-manager"])
        ->not->toHaveKey("payment/{$instalment->id}/2026-11-21@family-manager")
        ->and($summaries)->not->toContain('Passaporto di Marco', "Scade la carta d'identità")
        // Deadlines without a reminder are events of their own
        ->and($summaries)->toContain('Tessera sanitaria di Giulia');
});

test('a paid due date leaves the calendar', function (): void {
    $marco = boardFamily();
    $token = turnOnCalendar($marco);
    $gas = insideFamilyOf($marco, fn (): PlannedExpense => PlannedExpense::query()->where('title', 'Bolletta gas')->sole());
    $uid = "payment/{$gas->id}/2026-10-30@family-manager";

    expect(calendarEventsOf($token))->toHaveKey($uid);

    insideFamilyOf($marco, fn (): Expense => Expense::factory()->create(['family_id' => CurrentFamily::id(), 'planned_expense_id' => $gas->id, 'occurrence_date' => '2026-10-30', 'amount' => 84.20, 'date' => '2026-10-26']));

    expect(calendarEventsOf($token))->not->toHaveKey($uid);
});

test('the texts are in the language the calendar was turned on in', function (): void {
    $marco = boardFamily();
    $reminder = insideFamilyOf($marco, fn (): Reminder => Reminder::factory()->create(['family_id' => CurrentFamily::id(), 'title' => 'Pagare il bollo · Panda · scade il 31/01/2027 · manca la spesa prevista', 'due_date' => '2027-01-31', 'status' => ReminderStatus::Open, 'person_id' => $marco->person->id, 'notes' => null]));
    $token = turnOnCalendar($marco, 'it');

    // The browser of whoever reads it does not count
    $events = test()->withHeader('Accept-Language', 'en')->get("/calendar/{$token}.ics")->assertOk()->getContent();
    $events = calendarEvents((string) $events);

    expect($events["reminder-{$reminder->id}@family-manager"]['description'])->toBe("Manca la spesa prevista.\nPer: Marco Bianchi\nApri: ".route('agenda'))
        ->and(array_column($events, 'summary'))->toContain("Bolletta gas · 84,20\u{a0}€")
        ->and(app()->getLocale())->toBe('en');
});

test('the calendar of the Rossi has nothing of the Bianchi', function (): void {
    $marco = boardFamily();
    insideFamilyOf($marco, fn (): Reminder => Reminder::factory()->create(['family_id' => CurrentFamily::id(), 'title' => 'Pagare il bollo · Panda', 'due_date' => '2026-11-10', 'status' => ReminderStatus::Open]));
    $mario = registerFamily('Rossi', 'mario@example.com');
    insideFamilyOf($mario, fn (): Reminder => Reminder::factory()->create(['family_id' => CurrentFamily::id(), 'title' => "Chiamare l'idraulico", 'due_date' => '2026-11-10', 'status' => ReminderStatus::Open]));

    $rossi = calendarSummaries(turnOnCalendar($mario));
    $bianchi = calendarSummaries(turnOnCalendar($marco));

    expect($rossi)->toBe(["Chiamare l'idraulico"])
        ->and($bianchi)->toContain('Pagare il bollo · Panda', 'Tessera sanitaria di Giulia')
        ->not->toContain("Chiamare l'idraulico");
});

<?php

declare(strict_types=1);

use App\Data\AgendaEntryData;
use App\Mail\WeeklySummaryMail;
use App\Models\Family;
use App\Models\User;
use App\Support\Money;
use App\Support\Reminders\MorningItem;
use App\Support\Reminders\WeeklySummary;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * An entry of the Agenda for the mail.
 *
 * @param  string  $title  the title of the entry
 * @param  string  $date  the day, `yyyy-mm-dd`
 * @param  float|null  $amount  the amount, null for a task
 * @param  string|null  $person  the first name of the person
 * @param  string  $subtitle  the card or the document
 */
function weeklySummaryMailEntry(string $title, string $date, ?float $amount = null, ?string $person = null, string $subtitle = ''): AgendaEntryData
{
    return new AgendaEntryData(
        key: 'payment:1:'.$date,
        kind: $amount === null ? 'task' : 'payment',
        date: $date,
        title: $title,
        subtitle: $subtitle,
        name: $title,
        detail: '',
        area: 'banks',
        person_id: null,
        person: $person,
        amount: $amount,
        estimated: false,
        reminder_done: false,
        reminder: null,
        href: 'https://app.test/agenda/'.str_replace(' ', '-', $title),
    );
}

/**
 * The week of the tests: one late payment, two to pay and one task.
 *
 * @param  float  $total  the total of the week
 * @param  bool  $estimated  whether the total is an estimate
 */
function weeklySummaryMailWeek(float $total = 163.39, bool $estimated = false): WeeklySummary
{
    return new WeeklySummary(
        late: [weeklySummaryMailEntry('Prestito Divano', '2026-11-20', 34.90, 'Florian', 'Pagamento')],
        toPay: [weeklySummaryMailEntry('Netflix', '2026-11-23', 6.99), weeklySummaryMailEntry('Internet', '2026-11-25', 10.40)],
        tasks: [weeklySummaryMailEntry('Chiamare idraulico', '2026-11-24')],
        total: $total,
        estimated: $estimated,
    );
}

/**
 * A notice of the morning.
 */
function weeklySummaryMailNotice(): MorningItem
{
    return new MorningItem('Renew the policy', true, false, 'Panda', '2026-12-23', 'https://app.test/cards/vehicle/1');
}

/**
 * The mail for a new account.
 *
 * @param  list<MorningItem>  $notices  the notices of the day
 * @param  array<string, mixed>  $attributes  attributes of the account
 */
function weeklySummaryMailFor(WeeklySummary $summary, array $notices = [], array $attributes = []): WeeklySummaryMail
{
    $user = User::factory()->for(Family::factory())->create(['name' => 'Florian Leica', ...$attributes]);

    return new WeeklySummaryMail($user, $summary, $notices, '2026-11-23');
}

test('the subject says how much there is to pay', function (): void {
    weeklySummaryMailFor(weeklySummaryMailWeek())->assertHasSubject('This week: '.Money::euro(163.39).' to pay');
});

test('an estimated total says about', function (): void {
    weeklySummaryMailFor(weeklySummaryMailWeek(163.39, true))->assertHasSubject('This week: about '.Money::euro(163.39).' to pay');
});

test('a week with nothing to pay has the plain subject', function (): void {
    $summary = new WeeklySummary([], [], [weeklySummaryMailEntry('Call the plumber', '2026-11-24')], 0.0, false);

    weeklySummaryMailFor($summary)->assertHasSubject('Your week at a glance');
});

test('the sections show their rows', function (): void {
    $mail = weeklySummaryMailFor(weeklySummaryMailWeek());

    $mail->assertSeeInHtml('Good morning Florian')
        ->assertSeeInHtml('>Late<', false)
        ->assertSeeInHtml('>To pay this week<', false)
        ->assertSeeInHtml('>Reminders and things to do<', false)
        ->assertSeeInHtml('Prestito Divano')
        ->assertSeeInHtml('Pagamento')
        ->assertSeeInHtml('Florian')
        ->assertSeeInHtml(Money::euro(34.90))
        ->assertSeeInHtml('>20<', false)
        ->assertSeeInHtml('>Nov<', false)
        ->assertDontSeeInHtml('>Fri<', false)
        ->assertSeeInHtml('>23<', false)
        ->assertSeeInHtml('>Mon<', false)
        ->assertSeeInHtml(Money::euro(10.40))
        ->assertSeeInHtml('Chiamare idraulico')
        ->assertSeeInHtml(Money::euro(163.39))
        ->assertSeeInHtml('>To pay<', false)
        ->assertSeeInHtml('https://app.test/agenda/Netflix')
        ->assertSeeInHtml('Open the Agenda')
        ->assertSeeInHtml(route('agenda'));
    expect(substr_count($mail->render(), 'class="week-day-number due-late"'))->toBe(1);
});

test('the late row shows the month and the week rows the weekday', function (): void {
    $html = weeklySummaryMailFor(weeklySummaryMailWeek())->render();

    expect($html)->toContain('>Nov<')
        ->and(substr_count($html, '>Nov<'))->toBe(1)
        ->and($html)->toContain('>Mon<')
        ->and($html)->toContain('>Tue<')
        ->and($html)->toContain('>Wed<');
});

test('an estimated total says about, an exact one does not', function (): void {
    expect(weeklySummaryMailFor(weeklySummaryMailWeek(163.39, true))->render())->toContain('About '.Money::euro(163.39))
        ->and(weeklySummaryMailFor(weeklySummaryMailWeek(163.39, false))->render())->not->toContain('About ');
});

test('the colours of the day cells follow the board', function (): void {
    $css = (string) file_get_contents(resource_path('views/vendor/mail/html/themes/family-manager.css'));

    expect($css)->toMatch('/\.week-day-number\s*\{[^}]*color:\s*#18212f/')
        ->and($css)->toMatch('/\.week-day-number\.due-late,\s*\.week-day-name\.due-late\s*\{\s*color:\s*#c4283f/');
    expect(weeklySummaryMailFor(weeklySummaryMailWeek())->render())->toContain('class="week-day-number"');
});

test('an empty section is not shown', function (): void {
    $summary = new WeeklySummary([], [weeklySummaryMailEntry('Netflix', '2026-11-23', 6.99)], [], 6.99, false);
    $html = weeklySummaryMailFor($summary)->render();

    expect($html)->toContain('>To pay this week<')
        ->and($html)->not->toContain('>Late<')
        ->and($html)->not->toContain('>Reminders and things to do<')
        ->and($html)->not->toContain('>Today');
});

test('the notices of today are listed', function (): void {
    $mail = weeklySummaryMailFor(weeklySummaryMailWeek(), [weeklySummaryMailNotice()]);

    $mail->assertSeeInHtml('>Today', false)
        ->assertSeeInHtml('Renew the policy')
        ->assertSeeInHtml('Panda')
        ->assertSeeInHtml('Due in 30 days, on 23/12/2026')
        ->assertSeeInHtml('https://app.test/cards/vehicle/1');
});

test('without notices there is no notices section', function (): void {
    weeklySummaryMailFor(weeklySummaryMailWeek())->assertDontSeeInHtml('Today');
});

test('the footer says why it arrives', function (): void {
    weeklySummaryMailFor(weeklySummaryMailWeek())
        ->assertSeeInHtml('You get this email every Monday because you are an admin of the family.');
});

test('in Italian', function (): void {
    $mail = weeklySummaryMailFor(weeklySummaryMailWeek(), [weeklySummaryMailNotice()], ['locale' => 'it']);
    $mail->locale('it');

    $mail->assertHasSubject('Questa settimana: '.Money::euro(163.39).' da pagare')
        ->assertSeeInHtml('>23<', false)
        ->assertSeeInHtml('>lun<', false)
        ->assertSeeInHtml('>In ritardo<', false)
        ->assertSeeInHtml('>Da pagare in settimana<', false)
        ->assertSeeInHtml('>Promemoria e cose da fare<', false)
        ->assertSeeInHtml('>Avvisi di oggi<', false)
        ->assertSeeInHtml('Buongiorno Florian')
        ->assertSeeInHtml('Ricevi questa email ogni lunedì perché sei admin della famiglia.');
});

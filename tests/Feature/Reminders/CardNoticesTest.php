<?php

declare(strict_types=1);

use App\Cards\CardTypes;
use App\Data\CardNoticeData;
use App\Enums\NoticeTone;
use App\Enums\ReminderStatus;
use App\Enums\Role;
use App\Models\Document;
use App\Models\Enrollment;
use App\Models\Filing;
use App\Models\FinancialAccount;
use App\Models\InsurancePolicy;
use App\Models\Reminder;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\CardDetails;
use App\Services\Reminders\CardNotices;
use App\Support\CurrentFamily;
use Carbon\CarbonImmutable;
use Database\Seeders\BianchiFamilySeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-10-26 10:00', 'Europe/Rome'));
    $this->seed(BianchiFamilySeeder::class);
    $this->today = CarbonImmutable::today('Europe/Rome');
});

afterEach(fn () => Date::setTestNow());

/**
 * The notices of a card of the Bianchi.
 *
 * @param  Model  $card  the card
 * @return list<CardNoticeData>
 */
function cardNoticesOf(Model $card): array
{
    return insideBianchi(fn (): array => resolve(CardNotices::class)->for($card->fresh(), CarbonImmutable::today('Europe/Rome')));
}

/**
 * An open automatic reminder linked to a card.
 *
 * @param  Model  $card  the card
 * @param  string  $field  the `source_field`
 * @param  string  $due  the due date
 */
function noticeReminder(Model $card, string $field, string $due): Reminder
{
    return insideBianchi(function () use ($card, $field, $due): Reminder {
        $reminder = Reminder::factory()->create(['family_id' => CurrentFamily::id(), 'is_automatic' => true, 'source_field' => $field, 'due_date' => $due]);
        DB::table('remindables')->insert(['reminder_id' => $reminder->id, 'remindable_type' => $card->getMorphClass(), 'remindable_id' => $card->id]);

        return $reminder;
    });
}

/**
 * The Fiat Panda.
 */
function noticePanda(): Vehicle
{
    return insideBianchi(fn (): Vehicle => Vehicle::query()->where('name', 'Fiat Panda')->sole());
}

/**
 * The enrollment of Giulia to the dance, which has the sports certificate linked to it in the seeder.
 *
 * @param  string|null  $expiry  a new expiry date for the certificate
 */
function noticeDance(?string $expiry = null): Enrollment
{
    return insideBianchi(function () use ($expiry): Enrollment {
        $enrollment = Enrollment::query()->where('class_name', 'Danza')->sole();
        $document = Document::query()->where('title', 'Certificato medico sportivo')->sole();
        if ($expiry !== null) {
            $document->update(['expiry_date' => $expiry]);
        }

        return $enrollment;
    });
}

test('the policy that expires in 26 days says when, when the reminder went out and what to do if the company changes', function (): void {
    app()->setLocale('it');
    $policy = insideBianchi(fn (): InsurancePolicy => InsurancePolicy::query()->where('title', 'RC auto Panda')->sole());
    insideBianchi(fn () => Reminder::query()->where('source_field', 'insurance_policies.expiry_date')->get()->each->update(['notifications_sent' => ['2026-11-21:-45']]));

    $notice = cardNoticesOf($policy)[0];

    expect($notice->tone)->toBe(NoticeTone::Warning)
        ->and($notice->title)->toBe('Scade tra 26 giorni')
        ->and($notice->text)->toBe('Il promemoria è partito il 07/10/2026. Se cambi compagnia, segna questa come disdetta e crea la nuova sulla stessa auto.');
});

test('without a notice sent it says when the next one goes out', function (): void {
    $policy = insideBianchi(fn (): InsurancePolicy => InsurancePolicy::query()->where('title', 'RC auto Panda')->sole());

    $notice = cardNoticesOf($policy)[0];

    expect($notice->title)->toBe('Expires in 26 days')
        ->and($notice->text)->toStartWith('We will let you know on 14/11/2026.');
});

test('the title counts the days: one, today, overdue, and the overdue one is red', function (): void {
    $panda = noticePanda();
    $reminder = noticeReminder($panda, 'vehicles.road_tax_due_date', '2026-10-27');
    $first = fn (): CardNoticeData => cardNoticesOf($panda)[0];

    expect($first()->title)->toBe('Expires in 1 day');

    $reminder->update(['due_date' => '2026-10-26']);
    expect($first()->title)->toBe('Expires today');

    $reminder->update(['due_date' => '2026-10-20']);
    expect($first()->title)->toBe('Overdue by 6 days')
        ->and($first()->tone)->toBe(NoticeTone::Danger);
});

test('a deadline further than 60 days away and a done reminder give no notice', function (): void {
    $panda = noticePanda();
    $reminder = noticeReminder($panda, 'vehicles.road_tax_due_date', '2026-12-26');
    $titles = fn (): array => collect(cardNoticesOf($panda))->pluck('title')->all();

    expect($titles())->not->toContain('Expires in 61 days');

    $reminder->update(['due_date' => '2026-12-25']);
    expect($titles())->toContain('Expires in 60 days');

    $reminder->update(['status' => ReminderStatus::Done]);
    expect($titles())->not->toContain('Expires in 60 days');
});

test('a card with a reminder and no planned expense warns that the payment is not in the budget, with a link to add it', function (): void {
    $vehicle = insideBianchi(fn (): Vehicle => Vehicle::factory()->create(['family_id' => CurrentFamily::id(), 'name' => 'Golf']));
    noticeReminder($vehicle, 'vehicles.road_tax_due_date', '2027-03-01');

    $missing = collect(cardNoticesOf($vehicle))->firstWhere('title', 'The planned expense is missing');

    expect($missing)->toBeInstanceOf(CardNoticeData::class)
        ->and($missing->text)->toBe('This payment is not in the budget.')
        ->and($missing->action_label)->toBe('Add')
        ->and($missing->action_href)->toBe('/budget/planned/expense/create?card=vehicle%3A'.$vehicle->id);
});

test('the Panda, which has its planned expense, does not warn', function (): void {
    expect(collect(cardNoticesOf(noticePanda()))->pluck('title')->all())->not->toContain('The planned expense is missing');
});

test('a member does not get Add on the missing planned expense, which only an admin creates', function (): void {
    $vehicle = insideBianchi(fn (): Vehicle => Vehicle::factory()->create(['family_id' => CurrentFamily::id(), 'name' => 'Golf']));
    noticeReminder($vehicle, 'vehicles.road_tax_due_date', '2027-03-01');
    $member = User::factory()->create(['family_id' => $vehicle->family_id, 'role' => Role::Member]);

    $notices = insideBianchi(fn (): array => resolve(CardNotices::class)->for($vehicle, $this->today, $member));
    $missing = collect($notices)->firstWhere('title', 'The planned expense is missing');

    expect($missing->action_label)->toBeNull()->and($missing->action_href)->toBeNull();
});

test('the enrollment of Giulia shows the sports certificate that expires on 11/12/2026, with Upload the new one', function (): void {
    app()->setLocale('it');
    $enrollment = noticeDance();

    $notice = collect(cardNoticesOf($enrollment))->firstWhere('action_label', 'Carica quello nuovo');

    expect($notice->title)->toBe('Certificato medico sportivo')
        ->and($notice->text)->toBe("Scade l'11/12/2026.")
        ->and($notice->tone)->toBe(NoticeTone::Warning)
        ->and($notice->action_href)->toBe('/upload?link=enrollment%3A'.$enrollment->id);
});

test('a linked document that already expired is red and says it expired', function (): void {
    $notice = collect(cardNoticesOf(noticeDance('2026-10-20')))->firstWhere('action_label', 'Upload the new one');

    expect($notice->title)->toBe('Certificato medico sportivo')
        ->and($notice->text)->toBe('Expired on 20/10/2026.')
        ->and($notice->tone)->toBe(NoticeTone::Danger);
});

test('a document that expires on the day of the reminder of the same card is not said twice', function (): void {
    $policy = insideBianchi(fn (): InsurancePolicy => InsurancePolicy::query()->where('title', 'RC auto Panda')->sole());

    expect(collect(cardNoticesOf($policy))->pluck('action_label')->filter()->all())->not->toContain('Upload the new one');
});

test('the Panda without its registration document says what is missing', function (): void {
    app()->setLocale('it');
    $panda = noticePanda();

    $missing = collect(cardNoticesOf($panda))->firstWhere('title', 'Manca · Libretto di circolazione');

    expect($missing)->not->toBeNull()
        ->and($missing->action_label)->toBe('Caricalo')
        ->and($missing->action_href)->toBe('/upload?link=vehicle%3A'.$panda->id);
});

test('the ISEE 2027 of Laura, as the board shows it, has 3 documents of 4 and misses the average balance', function (): void {
    app()->setLocale('it');
    $filing = insideBianchi(fn (): Filing => Filing::query()->where('title', 'ISEE 2027')->sole());

    $notices = cardNoticesOf($filing);
    $titles = collect($notices)->pluck('title')->all();

    expect($titles)->toContain('Documenti 3 di 4 raccolti')
        ->toContain('Manca · Giacenza media 2025 di Conto BPER')
        ->not->toContain('Manca · CU 2026 di Marco')
        ->and(collect($notices)->firstWhere('title', 'Manca · Giacenza media 2025 di Conto BPER')->action_label)->toBe('Caricalo');
});

test('the days that start with a vowel sound take l\' in Italian, in the date of the notices', function (): void {
    app()->setLocale('it');
    $policy = insideBianchi(fn (): InsurancePolicy => InsurancePolicy::query()->where('title', 'RC auto Panda')->sole());
    insideBianchi(fn () => Reminder::query()->where('source_field', 'insurance_policies.expiry_date')->get()->each->update(['notifications_sent' => ['2026-11-21:-10']]));

    expect(cardNoticesOf($policy)[0]->text)->toStartWith("Il promemoria è partito l'11/11/2026.");
});

test('every notice has a key of its own, which does not depend on its words', function (): void {
    $keys = collect(cardNoticesOf(noticePanda()))->pluck('key');

    expect($keys->all())->not->toBe([])->and($keys->unique()->count())->toBe($keys->count());
});

test('the notices that the card types already showed are still there, as warnings, on the page of the card', function (): void {
    app()->setLocale('it');
    $account = insideBianchi(fn (): FinancialAccount => FinancialAccount::query()->where('name', 'Conto BPER')->sole());
    $own = CardTypes::for($account)->notices($account);
    $marco = User::query()->where('email', 'marco.bianchi@example.com')->sole();

    $details = insideBianchi(fn () => resolve(CardDetails::class)->of($account, $marco, $this->today));

    expect($own)->toHaveCount(1)
        ->and($own[0])->toBeInstanceOf(CardNoticeData::class)
        ->and($own[0]->tone)->toBe(NoticeTone::Warning)
        ->and($own[0]->text)->toStartWith('È il conto predefinito')
        ->and($details->notices[0]->text)->toStartWith('È il conto predefinito');
});

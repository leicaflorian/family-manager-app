<?php

declare(strict_types=1);

use App\Actions\Families\CreateFamily;
use App\Enums\DocumentStatus;
use App\Enums\InsurancePolicyStatus;
use App\Enums\IntervalUnit;
use App\Enums\ReminderStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\UtilityType;
use App\Models\Document;
use App\Models\Employment;
use App\Models\Family;
use App\Models\FamilySetting;
use App\Models\InsurancePolicy;
use App\Models\Loan;
use App\Models\Organization;
use App\Models\Person;
use App\Models\PlannedExpense;
use App\Models\PlannedIncome;
use App\Models\Reminder;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Utility;
use App\Support\CurrentFamily;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** The user agents of the phones the device tests pretend to be, shared so each test file runs alone. */
const IPHONE_AGENT = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1';
const ANDROID_AGENT = 'Mozilla/5.0 (Linux; Android 15; Pixel 9) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Mobile Safari/537.36';

/*
 * The Pest tests in Feature start from an empty database and use the app's
 * TestCase. The class-based tests of the starter kit declare what they need
 * on their own. No test calls a real service: a request without its
 * `Http::fake()` fails (the reading of documents talks to an AI service). No
 * test writes to the real disk: the `local` disk, where the files of the
 * families go, is faked (the seeder of the Bianchi stores files too).
 */
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function (): void {
        Http::preventStrayRequests();
        Storage::fake('local');
    })
    ->in('Feature');

/*
 * The tests in Unit have the app booted too, with the same fakes: those that
 * use the database add RefreshDatabase in their own file.
 */
pest()->extend(TestCase::class)
    ->beforeEach(function (): void {
        Http::preventStrayRequests();
        Storage::fake('local');
    })
    ->in('Unit');

/**
 * Registers a family through `CreateFamily`, with the email verified and the
 * *Welcome* done: its `admin` account is ready to use the app.
 *
 * @param  string  $lastName  the last name, which is also the name of the family
 * @param  string  $email  the email of the `admin` account
 */
function registerFamily(string $lastName = 'Bianchi', string $email = 'marco@example.com'): User
{
    $admin = resolve(CreateFamily::class)->handle($lastName, 'Marco', $lastName, $email, 'a-long-password');
    $admin->forceFill(['email_verified_at' => now()])->save();
    $admin->family->update(['welcomed_at' => now()]);

    return $admin;
}

/**
 * Runs some work inside the Bianchi family.
 *
 * @template T
 *
 * @param  callable(): T  $work
 * @return T
 */
function insideBianchi(callable $work): mixed
{
    return CurrentFamily::using(Family::query()->where('name', 'Bianchi')->sole()->id, $work);
}

/**
 * Runs some work inside the family of an account.
 *
 * @template T
 *
 * @param  User  $user  the account
 * @param  callable(): T  $work  the work
 * @return T
 */
function insideFamilyOf(User $user, callable $work): mixed
{
    return CurrentFamily::using($user->family_id, $work);
}

/**
 * A planned expense of a card, starting on a day.
 *
 * @param  Model|null  $card  the card it pays, null when none
 * @param  string  $title  the title
 * @param  float  $amount  the amount
 * @param  string  $start  the first due date
 * @param  array<string, mixed>  $state  more columns
 */
function agendaPlanned(?Model $card, string $title, float $amount, string $start, array $state = []): PlannedExpense
{
    $factory = PlannedExpense::factory();

    if ($card instanceof Model) {
        $factory = $factory->forCard($card);
    }

    return $factory->create(['family_id' => CurrentFamily::id(), 'title' => $title, 'amount' => $amount, 'start_date' => $start, ...$state]);
}

/**
 * The Bianchi of the board on 26/10/2026: Marco, Laura and Giulia, their cards,
 * documents, planned expenses and salaries.
 *
 * @return User Marco's account
 */
function boardFamily(): User
{
    $marco = registerFamily();

    insideFamilyOf($marco, function () use ($marco): void {
        $family = CurrentFamily::id();
        $me = $marco->person;
        $laura = Person::factory()->create(['family_id' => $family, 'first_name' => 'Laura', 'last_name' => 'Bianchi']);
        $giulia = Person::factory()->create(['family_id' => $family, 'first_name' => 'Giulia', 'last_name' => 'Bianchi']);

        // People and documents: the expired health card of Giulia and Marco's identity card with its reminder done
        Document::factory()->create(['family_id' => $family, 'title' => 'Tessera sanitaria di Giulia', 'status' => DocumentStatus::Expired, 'person_id' => $giulia->id, 'expiry_date' => '2026-09-30']);
        $identity = Document::factory()->create(['family_id' => $family, 'title' => "Scade la carta d'identità", 'status' => DocumentStatus::Active, 'person_id' => $me->id, 'expiry_date' => '2026-11-14']);
        $reminder = Reminder::factory()->create(['family_id' => $family, 'is_automatic' => true, 'source_field' => 'documents.expiry_date', 'due_date' => '2026-11-14', 'status' => ReminderStatus::Done]);
        DB::table('remindables')->insert(['reminder_id' => $reminder->id, 'remindable_type' => $identity->getMorphClass(), 'remindable_id' => $identity->id]);

        // Home: gas and water monthly, the electricity of September (late, on the Home) and of October
        $gas = Utility::factory()->create(['family_id' => $family, 'type' => UtilityType::Gas, 'organization_id' => Organization::factory()->create(['family_id' => $family, 'name' => 'Enel'])->id]);
        $water = Utility::factory()->create(['family_id' => $family, 'type' => UtilityType::Water, 'organization_id' => Organization::factory()->create(['family_id' => $family, 'name' => 'Hera'])->id]);
        $light = Utility::factory()->create(['family_id' => $family, 'type' => UtilityType::Electricity, 'organization_id' => Organization::factory()->create(['family_id' => $family, 'name' => 'Edison'])->id]);
        agendaPlanned($gas, 'Bolletta gas', 84.20, '2026-09-30', ['person_id' => $me->id]);
        agendaPlanned($water, 'Bolletta acqua', 37.90, '2026-08-31', ['person_id' => $me->id]);
        agendaPlanned($light, 'Bolletta luce di settembre', 71.30, '2026-10-23', ['is_recurring' => false, 'person_id' => $me->id]);
        agendaPlanned($light, 'Bolletta luce di ottobre', 62.40, '2026-11-06', ['is_recurring' => false, 'person_id' => $me->id]);

        // Subscriptions: the trial of Netflix ends the day of its first payment
        $netflix = Subscription::factory()->create(['family_id' => $family, 'name' => 'Netflix', 'status' => SubscriptionStatus::Active, 'person_id' => $laura->id, 'trial_end_date' => '2026-11-04', 'renewal_date' => null]);
        agendaPlanned($netflix, 'Primo pagamento', 13.99, '2026-11-04', ['person_id' => $laura->id]);

        // Banks: the loan of the Panda and the mortgage
        $loan = Loan::factory()->create(['family_id' => $family, 'name' => 'Finanziamento Panda', 'person_id' => $me->id, 'start_date' => null, 'end_date' => null]);
        $mortgage = Loan::factory()->create(['family_id' => $family, 'name' => 'Mutuo casa', 'person_id' => $me->id, 'start_date' => null, 'end_date' => null]);
        agendaPlanned($loan, 'Rata 33 di 48', 189.00, '2024-04-10', ['person_id' => $me->id]);
        agendaPlanned($mortgage, 'Rata mutuo', 612.00, '2020-01-15', ['person_id' => $me->id]);

        // Insurance: the renewal of the policy and its yearly instalment on the same day
        $policy = InsurancePolicy::factory()->create(['family_id' => $family, 'title' => 'RC auto Panda - UnipolSai', 'status' => InsurancePolicyStatus::Active, 'person_id' => $me->id, 'expiry_date' => '2026-11-21']);
        agendaPlanned($policy, 'Rata RC auto', 418.00, '2025-11-21', ['interval_unit' => IntervalUnit::Year, 'person_id' => $me->id]);

        // Work: the two salaries
        $rinaldi = Employment::factory()->create(['family_id' => $family, 'person_id' => $me->id, 'organization_id' => Organization::factory()->create(['family_id' => $family, 'name' => 'Studio Rinaldi'])->id]);
        $comune = Employment::factory()->create(['family_id' => $family, 'person_id' => $laura->id, 'organization_id' => Organization::factory()->create(['family_id' => $family, 'name' => 'Comune di Bologna'])->id]);
        PlannedIncome::factory()->create(['family_id' => $family, 'title' => 'Stipendio di Marco', 'amount' => 1850, 'start_date' => '2026-01-27', 'employment_id' => $rinaldi->id, 'person_id' => $me->id]);
        PlannedIncome::factory()->create(['family_id' => $family, 'title' => 'Stipendio di Laura', 'amount' => 1720, 'start_date' => '2026-01-23', 'employment_id' => $comune->id, 'person_id' => $laura->id]);
    });

    return $marco;
}

/**
 * A family registered through `CreateFamily` with its address for the bills, for the tests of the mail that
 * reaches it from Mailgun.
 *
 * @param  string  $lastName  the last name, which is also the name of the family
 * @param  string  $email  the email of the `admin` account
 * @param  string  $address  the address for the bills
 * @return User the `admin` account
 */
function familyWithMailAddress(string $lastName, string $email, string $address): User
{
    $admin = registerFamily($lastName, $email);

    insideFamilyOf($admin, function () use ($address): void {
        FamilySetting::query()->firstOrCreate([])->forceFill(['mail_inbox_recipient' => $address])->save();
    });

    return $admin;
}

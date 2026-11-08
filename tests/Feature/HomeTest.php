<?php

declare(strict_types=1);

use App\Enums\ExceptionType;
use App\Enums\FilingStatus;
use App\Enums\FilingType;
use App\Enums\MailOutcome;
use App\Enums\PersonRole;
use App\Enums\Role;
use App\Enums\UtilityType;
use App\Models\Document;
use App\Models\Expense;
use App\Models\File;
use App\Models\Filing;
use App\Models\InsurancePolicy;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\Person;
use App\Models\PlannedExpense;
use App\Models\PlannedExpenseException;
use App\Models\ReceivedMail;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Utility;
use App\Models\Vehicle;
use App\Support\CurrentFamily;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
    Date::setTestNow(CarbonImmutable::parse('2026-10-26 10:00', 'Europe/Rome'));
});

afterEach(fn () => Date::setTestNow());

/**
 * The `home` prop as an account sees it.
 *
 * @param  User  $user  the account
 * @return array<string, mixed>
 */
function homeFor(User $user): array
{
    return test()->actingAs($user)->get(route('dashboard'))->assertOk()->viewData('page')['props']['home'];
}

/**
 * The board family with the bills of September and the instalments before today
 * paid, as a family that uses the app keeps them.
 *
 * @return User Marco's account
 */
function boardFamilyUpToDate(): User
{
    $marco = boardFamily();

    insideFamilyOf($marco, function (): void {
        foreach (['Bolletta gas' => '2026-09-30', 'Bolletta acqua' => '2026-09-30', 'Rata 33 di 48' => '2026-10-10', 'Rata mutuo' => '2026-10-15'] as $title => $date) {
            $planned = PlannedExpense::query()->where('title', $title)->sole();
            Expense::factory()->create(['family_id' => CurrentFamily::id(), 'planned_expense_id' => $planned->id, 'occurrence_date' => $date, 'amount' => $planned->amount, 'date' => $date]);
        }
    });

    return $marco;
}

/**
 * The titles of some entries.
 *
 * @param  list<array<string, mixed>>  $entries  the entries
 * @return list<string>
 */
function homeTitles(array $entries): array
{
    return array_column($entries, 'title');
}

test('the total of the next 30 days is the one of the board', function (): void {
    $home = homeFor(boardFamilyUpToDate());

    expect($home['first_name'])->toBe('Marco')
        ->and($home['today'])->toBe('2026-10-26')
        ->and($home['total'])->toBe(1488.79)
        ->and($home['payments'])->toBe(8)
        ->and($home['late'])->toBe(1);
});

test('paying the RC instalment brings it to 1.070,79', function (): void {
    $marco = boardFamilyUpToDate();
    insideFamilyOf($marco, function (): void {
        $rc = PlannedExpense::query()->where('title', 'Rata RC auto')->sole();
        Expense::factory()->create(['family_id' => CurrentFamily::id(), 'planned_expense_id' => $rc->id, 'occurrence_date' => '2026-11-21', 'amount' => 418, 'date' => '2026-10-26']);
    });

    $home = homeFor($marco);

    expect($home['total'])->toBe(1070.79)
        ->and($home['payments'])->toBe(7)
        ->and($home['late'])->toBe(1);
});

test('a payment late by 30 days is there and one late by 31 is not', function (): void {
    $marco = registerFamily();
    insideFamilyOf($marco, function (): void {
        agendaPlanned(null, 'In ritardo di 30', 10.0, '2026-09-26', ['is_recurring' => false]);
        agendaPlanned(null, 'In ritardo di 31', 20.0, '2026-09-25', ['is_recurring' => false]);
    });

    $home = homeFor($marco);

    expect(homeTitles($home['entries']))->toBe(['In ritardo di 30'])
        ->and($home['total'])->toBe(10.0)
        ->and($home['late'])->toBe(1);
});

test('a family copied from Directus does not see years of late payments', function (): void {
    $marco = registerFamily();
    insideFamilyOf($marco, fn (): PlannedExpense => agendaPlanned(null, 'Abbonamento vecchio', 9.99, '2020-01-15'));

    $home = homeFor($marco);

    // Monthly since 2020 and never paid: only the instalment of 15/10, 11 days ago, and the one of 15/11 are in
    expect($home['late'])->toBe(1)
        ->and($home['payments'])->toBe(2)
        ->and($home['total'])->toBe(19.98);
});

test('a skipped due date is not late', function (): void {
    $marco = registerFamily();
    insideFamilyOf($marco, function (): void {
        $planned = agendaPlanned(null, 'Saltata', 10.0, '2026-10-20', ['is_recurring' => false]);
        PlannedExpenseException::factory()->create(['family_id' => CurrentFamily::id(), 'planned_expense_id' => $planned->id, 'type' => ExceptionType::Skip, 'occurrence_date' => '2026-10-20']);
    });

    expect(homeFor($marco)['late'])->toBe(0);
});

test('the expired health card and the identity card are in the same list without an amount', function (): void {
    $home = homeFor(boardFamilyUpToDate());

    $entries = collect($home['entries'])->keyBy('title');

    expect($entries['Tessera sanitaria di Giulia']['amount'])->toBeNull()
        ->and($entries['Tessera sanitaria di Giulia']['kind'])->toBe('deadline')
        ->and($entries["Scade la carta d'identità"]['amount'])->toBeNull()
        ->and($entries['Bolletta gas']['amount'])->toBe(84.2)
        ->and(homeTitles($home['entries']))->toBe([
            'Tessera sanitaria di Giulia',
            'Bolletta luce di settembre',
            'Bolletta gas',
            'Bolletta acqua',
            'End of the free trial',
            'Primo pagamento',
            'Bolletta luce di ottobre',
            'Rata 33 di 48',
            "Scade la carta d'identità",
            'Rata mutuo',
        ])
        ->and($home['more'])->toBeTrue();
});

test('ten entries at most, then more', function (): void {
    $marco = registerFamily();
    insideFamilyOf($marco, function (): void {
        foreach (range(1, 10) as $day) {
            agendaPlanned(null, sprintf('Voce %02d', $day), 1.0, '2026-11-'.sprintf('%02d', $day), ['is_recurring' => false]);
        }
    });

    $home = homeFor($marco);
    expect($home['entries'])->toHaveCount(10)
        ->and($home['more'])->toBeFalse();

    insideFamilyOf($marco, fn (): PlannedExpense => agendaPlanned(null, 'Voce 11', 1.0, '2026-11-11', ['is_recurring' => false]));
    $home = homeFor($marco);

    expect($home['entries'])->toHaveCount(10)
        ->and($home['more'])->toBeTrue()
        ->and($home['payments'])->toBe(11)
        ->and(last($home['entries'])['title'])->toBe('Voce 10');
});

test('the late entries come first even when there are many', function (): void {
    $marco = registerFamily();
    insideFamilyOf($marco, function (): void {
        foreach (range(1, 10) as $day) {
            agendaPlanned(null, sprintf('Prossima %02d', $day), 1.0, '2026-11-'.sprintf('%02d', $day), ['is_recurring' => false]);
        }
        agendaPlanned(null, 'Scaduta', 5.0, '2026-10-20', ['is_recurring' => false]);
    });

    $home = homeFor($marco);

    expect($home['entries'][0]['title'])->toBe('Scaduta')
        ->and($home['entries'])->toHaveCount(10)
        ->and($home['more'])->toBeTrue();
});

test('the incoming salaries are there and not in the total', function (): void {
    $home = homeFor(boardFamilyUpToDate());

    expect(array_map(fn (array $entry): array => [$entry['title'], $entry['date'], $entry['amount']], $home['incoming']))->toBe([
        ['Stipendio di Marco', '2026-10-27', 1850.0],
        ['Stipendio di Laura', '2026-11-23', 1720.0],
    ])->and($home['total'])->toBe(1488.79)
        ->and(homeTitles($home['entries']))->not->toContain('Stipendio di Marco');
});

test('the checklist of a new family', function (): void {
    $marco = registerFamily();

    $steps = fn (): array => array_column(homeFor($marco)['checklist'], 'done', 'key');

    expect($steps())->toBe(['utilities' => false, 'first_bill' => false, 'card' => false, 'invite' => true]);

    insideFamilyOf($marco, fn () => Utility::factory()->create(['family_id' => CurrentFamily::id()]));
    expect($steps()['utilities'])->toBeTrue();

    insideFamilyOf($marco, fn () => Document::factory()->create(['family_id' => CurrentFamily::id()]));
    expect($steps()['first_bill'])->toBeTrue();

    // Without a vehicle, a policy or a subscription the step is open; with a subscription it is done and, all done, the list is gone
    insideFamilyOf($marco, fn () => Subscription::factory()->create(['family_id' => CurrentFamily::id()]));
    expect($steps())->toBe([]);
});

test('a vehicle, a policy or a subscription each complete the card step', function (string $model): void {
    $marco = registerFamily();
    insideFamilyOf($marco, fn () => $model::factory()->create(['family_id' => CurrentFamily::id()]));

    expect(array_column(homeFor($marco)['checklist'], 'done', 'key')['card'])->toBeTrue();
})->with([Vehicle::class, InsurancePolicy::class, Subscription::class]);

test('invite names the first parent without an account', function (): void {
    $marco = registerFamily();
    insideFamilyOf($marco, function (): void {
        Person::factory()->create(['family_id' => CurrentFamily::id(), 'first_name' => 'Giulia', 'role' => PersonRole::Child]);
        Person::factory()->create(['family_id' => CurrentFamily::id(), 'first_name' => 'Laura', 'role' => PersonRole::Parent, 'user_id' => null]);
        Person::factory()->create(['family_id' => CurrentFamily::id(), 'first_name' => 'Anna', 'role' => PersonRole::Parent, 'user_id' => null]);
    });

    $invite = collect(homeFor($marco)['checklist'])->firstWhere('key', 'invite');

    expect($invite)->toBe(['key' => 'invite', 'done' => false, 'name' => 'Laura']);
});

test('a pending invitation counts, then the next parent is named', function (): void {
    $marco = registerFamily();
    insideFamilyOf($marco, function (): void {
        $laura = Person::factory()->create(['family_id' => CurrentFamily::id(), 'first_name' => 'Laura', 'role' => PersonRole::Parent, 'user_id' => null]);
        Person::factory()->create(['family_id' => CurrentFamily::id(), 'first_name' => 'Anna', 'role' => PersonRole::Parent, 'user_id' => null]);
        Invitation::factory()->create(['family_id' => CurrentFamily::id(), 'person_id' => $laura->id, 'expires_at' => now()->addDays(3), 'accepted_at' => null]);
    });

    expect(collect(homeFor($marco)['checklist'])->firstWhere('key', 'invite')['name'])->toBe('Anna');
});

test('invite is done once Laura accepts', function (): void {
    $marco = registerFamily();
    $laura = insideFamilyOf($marco, fn (): Person => Person::factory()->create(['family_id' => CurrentFamily::id(), 'first_name' => 'Laura', 'role' => PersonRole::Parent, 'user_id' => null]));

    expect(collect(homeFor($marco)['checklist'])->firstWhere('key', 'invite')['done'])->toBeFalse();

    $account = User::factory()->create(['family_id' => $marco->family_id, 'role' => Role::Admin, 'email_verified_at' => now()]);
    $laura->forceFill(['user_id' => $account->id])->save();

    expect(collect(homeFor($marco)['checklist'])->firstWhere('key', 'invite'))->toBe(['key' => 'invite', 'done' => true, 'name' => null]);
});

test('a member has no invite step and no card step', function (): void {
    $marco = registerFamily();
    insideFamilyOf($marco, fn () => Person::factory()->create(['family_id' => CurrentFamily::id(), 'first_name' => 'Laura', 'role' => PersonRole::Parent, 'user_id' => null]));
    $member = User::factory()->create(['family_id' => $marco->family_id, 'role' => Role::Member, 'email_verified_at' => now()]);

    expect(array_column(homeFor($member)['checklist'], 'key'))->toBe(['utilities', 'first_bill']);
});

test('the checklist of a member is gone when the steps he can do are done', function (): void {
    $marco = registerFamily();
    insideFamilyOf($marco, function (): void {
        Utility::factory()->create(['family_id' => CurrentFamily::id()]);
        Document::factory()->create(['family_id' => CurrentFamily::id()]);
    });
    $member = User::factory()->create(['family_id' => $marco->family_id, 'role' => Role::Member, 'email_verified_at' => now()]);

    expect(homeFor($member)['checklist'])->toBe([]);
});

test('the checklist is gone when every step is done', function (): void {
    $marco = registerFamily();
    insideFamilyOf($marco, function (): void {
        Utility::factory()->create(['family_id' => CurrentFamily::id()]);
        Document::factory()->create(['family_id' => CurrentFamily::id()]);
        Vehicle::factory()->create(['family_id' => CurrentFamily::id()]);
    });

    expect(homeFor($marco)['checklist'])->toBe([]);
});

test('the Home of a new family is empty', function (): void {
    $home = homeFor(registerFamily());

    expect($home['total'])->toBe(0.0)
        ->and($home['payments'])->toBe(0)
        ->and($home['late'])->toBe(0)
        ->and($home['entries'])->toBe([])
        ->and($home['more'])->toBeFalse()
        ->and($home['incoming'])->toBe([]);
});

test('pending files are on the Home only when there are some', function (): void {
    $marco = registerFamily();

    expect(homeFor($marco)['pending'])->toBe([])
        ->and(homeFor($marco)['pending_count'])->toBe(0);

    $files = collect(range(1, 6))->map(fn (int $number): File => File::factory()->for($marco->family)->create([
        'original_name' => "Bolletta {$number}.pdf", 'to_sort' => true,
    ]));
    File::factory()->for($marco->family)->create(['original_name' => 'Già archiviata.pdf', 'to_sort' => false]);
    File::factory()->create(['original_name' => 'Di un\'altra famiglia.pdf', 'to_sort' => true]);

    $home = homeFor($marco);

    expect($home['pending_count'])->toBe(6)
        ->and($home['pending'])->toHaveCount(5)
        ->and($home['pending'][0]['id'])->toBe($files[0]->id)
        ->and($home['pending'][4]['title'])->toBe('Bolletta 5');
});

test('a member sees the files waiting too', function (): void {
    $marco = registerFamily();
    File::factory()->for($marco->family)->create(['to_sort' => true]);
    $member = User::factory()->create(['family_id' => $marco->family_id, 'role' => Role::Member, 'email_verified_at' => now()]);

    $home = homeFor($member);

    expect($home['pending_count'])->toBe(1)
        ->and($home['pending'])->toHaveCount(1);
});

test('an account of the Rossi sees nothing of the Bianchi', function (): void {
    boardFamilyUpToDate();
    File::factory()->for(User::query()->where('email', 'marco@example.com')->sole()->family)->create(['to_sort' => true]);
    $rossi = registerFamily('Rossi', 'marco@rossi.example.com');

    $home = homeFor($rossi);

    expect($home['total'])->toBe(0.0)
        ->and($home['entries'])->toBe([])
        ->and($home['incoming'])->toBe([])
        ->and($home['pending_count'])->toBe(0)
        ->and(array_column($home['checklist'], 'done', 'key'))->toBe(['utilities' => false, 'first_bill' => false, 'card' => false, 'invite' => true]);
});

test('the Home says what is missing: the registration document of a vehicle and the file of an ISEE, and nothing once there is nothing', function (): void {
    $marco = boardFamilyUpToDate();
    [$panda, $isee] = insideFamilyOf($marco, fn (): array => [
        Vehicle::factory()->create(['family_id' => CurrentFamily::id(), 'name' => 'Fiat Panda']),
        Filing::factory()->create(['family_id' => CurrentFamily::id(), 'title' => 'ISEE 2027', 'type' => FilingType::Isee, 'status' => FilingStatus::Draft, 'reference_year' => 2027, 'person_id' => null]),
    ]);

    $missing = collect(homeFor($marco)['missing']);

    expect($missing->firstWhere('card', 'Fiat Panda'))->toBe(['key' => '/upload?link=vehicle%3A'.$panda->id.'#1', 'card' => 'Fiat Panda', 'href' => '/upload?link=vehicle%3A'.$panda->id, 'document' => 'Vehicle registration document'])
        ->and($missing->where('card', 'ISEE 2027')->pluck('key')->unique()->count())->toBe(2);
    expect($missing->where('card', 'ISEE 2027')->pluck('document')->all())->toBe(['Annual income statement 2026 of Marco', 'Annual income statement 2026 of Laura']);

    insideFamilyOf($marco, function () use ($panda, $isee): void {
        $document = Document::factory()->create(['family_id' => CurrentFamily::id(), 'title' => 'Libretto Panda']);
        $document->morphedByMany(Vehicle::class, 'documentable')->attach($panda);
        $isee->update(['status' => FilingStatus::Submitted]);
    });

    expect(homeFor($marco)['missing'])->toBe([]);
});

test('the Home shows the last three mails that brought files, with the card their document is linked to, and none without mail', function (): void {
    $marco = boardFamilyUpToDate();

    expect(homeFor($marco)['mails'])->toBe([]);

    insideFamilyOf($marco, function (): void {
        $enel = Organization::factory()->create(['family_id' => CurrentFamily::id(), 'name' => 'Enel Energia']);
        $gas = Utility::factory()->create(['family_id' => CurrentFamily::id(), 'type' => UtilityType::Gas, 'organization_id' => $enel->id]);
        $mails = [
            ['La tua bolletta', '2026-10-25 09:41', $enel->id, $gas],
            ['Fattura disponibile', '2026-10-25 09:12', null, null],
            ['Terza', '2026-10-24 17:30', null, null],
            ['Quarta, too old', '2026-10-23 11:05', null, null],
        ];

        foreach ($mails as [$subject, $at, $organization, $card]) {
            $mail = ReceivedMail::factory()->create(['family_id' => CurrentFamily::id(), 'subject' => $subject, 'received_at' => $at, 'organization_id' => $organization]);
            $file = File::factory()->create(['family_id' => CurrentFamily::id(), 'received_mail_id' => $mail->id, 'mail_key' => $mail->event_id.':0']);
            $mail->recordSaved($file);

            if ($card instanceof Utility) {
                Document::factory()->create(['family_id' => CurrentFamily::id(), 'file_id' => $file->id])->utilities()->attach($card);
            }
        }

        ReceivedMail::factory()->create(['family_id' => CurrentFamily::id(), 'subject' => 'Advert', 'received_at' => '2026-10-25 20:00', 'outcome' => MailOutcome::Discarded]);
    });

    $mails = homeFor($marco)['mails'];

    expect(array_column($mails, 'subject'))->toBe(['La tua bolletta', 'Fattura disponibile', 'Terza'])
        ->and($mails[0]['sender'])->toBe('Enel Energia')
        ->and($mails[0]['received_at'])->toBe('2026-10-25T09:41:00+01:00')
        ->and($mails[0]['links'])->toBe(['Gas - Enel Energia'])
        ->and($mails[1]['links'])->toBe([]);
});

test('the mail of another family is not on the Home', function (): void {
    $marco = boardFamilyUpToDate();
    $rossi = registerFamily('Rossi', 'rossi@example.com');
    insideFamilyOf($rossi, function (): void {
        $mail = ReceivedMail::factory()->create(['family_id' => CurrentFamily::id()]);
        $mail->recordSaved(File::factory()->create(['family_id' => CurrentFamily::id(), 'received_mail_id' => $mail->id, 'mail_key' => 'k:0']));
    });

    expect(homeFor($marco)['mails'])->toBe([]);
});

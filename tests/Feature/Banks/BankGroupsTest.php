<?php

declare(strict_types=1);

use App\Enums\BankTransactionStatus;
use App\Models\BankFilter;
use App\Models\BankTransaction;
use App\Models\FinancialAccount;
use App\Services\Banks\BankGroups;
use App\Services\Banks\FakeBankFeed;
use App\Support\Banks\BankGroup;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-11-27 10:00', 'Europe/Rome'));
    $this->marco = registerFamily();
    $this->actingAs($this->marco);
    $this->account = FinancialAccount::factory()->create(['family_id' => $this->marco->family_id]);
});

/**
 * A booked transaction of the family of Marco, on the first account unless the state says otherwise.
 *
 * @param  array<string, mixed>  $state  what to change in the transaction
 */
function bankGroupsRow(string $text, string $amount = '-10.00', string $day = '2026-11-20', array $state = []): BankTransaction
{
    return BankTransaction::factory()->create([
        'family_id' => test()->marco->family_id,
        'financial_account_id' => test()->account->id,
        'text' => $text,
        'counterparty' => null,
        'booked_on' => $day,
        'value_on' => $day,
        'amount' => $amount,
        ...$state,
    ]);
}

const BANK_GROUPS_SATISPAY = 'DOMICILIAZIONE (ADDEBITO DIRETTO SEPA) Satispay Europe S.A. CID.LU08HOO0000000000000001642 MAN.SAT0023101207360112900';

it('eleven direct debits of one mandate are one group, their commissions are not', function (): void {
    foreach (range(1, 11) as $i) {
        $day = CarbonImmutable::parse('2026-11-25')->subDays($i * 2);
        bankGroupsRow(BANK_GROUPS_SATISPAY, '-'.(10 + $i).'.00', $day->toDateString(), ['counterparty' => 'Satispay Europe S.A.']);
        bankGroupsRow('COMMISSIONI '.BANK_GROUPS_SATISPAY, '-0.40', $day->addDay()->toDateString(), ['counterparty' => 'Satispay Europe S.A.']);
    }

    $groups = resolve(BankGroups::class)->all();

    expect($groups)->toHaveCount(1)
        ->and($groups[0]->name)->toBe('Satispay Europe S.A.')
        ->and($groups[0]->kind)->toBe('expense')
        ->and($groups[0]->accountId)->toBe($this->account->id)
        ->and($groups[0]->transactions)->toHaveCount(11)
        ->and($groups[0]->transactions->every(fn (BankTransaction $row): bool => ! str_starts_with($row->text, 'COMMISSIONI')))->toBeTrue()
        ->and($groups[0]->key)->toHaveLength(16)
        ->and(resolve(BankGroups::class)->find($groups[0]->key)?->key)->toBe($groups[0]->key);
});

it('the same shop on two accounts makes two groups', function (): void {
    $other = FinancialAccount::factory()->create(['family_id' => $this->marco->family_id]);

    foreach ([$this->account->id, $other->id] as $account) {
        bankGroupsRow('PAGAMENTO POS ENI STATION', '-30.00', '2026-11-10', ['financial_account_id' => $account, 'counterparty' => 'ENI']);
        bankGroupsRow('PAGAMENTO POS ENI STATION', '-31.00', '2026-11-12', ['financial_account_id' => $account, 'counterparty' => 'ENI']);
    }

    $groups = resolve(BankGroups::class)->all();

    expect($groups)->toHaveCount(2)
        ->and($groups[0]->key)->not->toBe($groups[1]->key)
        ->and(collect($groups)->pluck('accountId')->sort()->values()->all())->toBe(collect([$this->account->id, $other->id])->sort()->values()->all());
});

it('the same text paid and received makes two groups', function (): void {
    bankGroupsRow('RIMBORSO AMAZON', '-5.00', '2026-11-10');
    bankGroupsRow('RIMBORSO AMAZON', '-6.00', '2026-11-11');
    bankGroupsRow('RIMBORSO AMAZON', '5.00', '2026-11-12');
    bankGroupsRow('RIMBORSO AMAZON', '6.00', '2026-11-13');

    expect(collect(resolve(BankGroups::class)->all())->pluck('kind')->sort()->values()->all())->toBe(['expense', 'income']);
});

it('a single movement is lone', function (): void {
    $single = bankGroupsRow('PRELIEVO BANCOMAT', '-100.00');

    $service = resolve(BankGroups::class);

    expect($service->all())->toBe([])
        ->and($service->lone()->pluck('id')->all())->toBe([$single->id]);
});

it('lone leaves out the movements of a group and the commission of a debit, and keeps an orphan commission', function (): void {
    bankGroupsRow(BANK_GROUPS_SATISPAY, '-10.00', '2026-11-20');
    bankGroupsRow(BANK_GROUPS_SATISPAY, '-10.00', '2026-10-20');
    $paired = bankGroupsRow('COMMISSIONI '.BANK_GROUPS_SATISPAY, '-0.40', '2026-11-21');
    $orphan = bankGroupsRow('COMMISSIONI DOMICILIAZIONE (ADDEBITO DIRETTO SEPA) Gym Club S.r.l. CID.IT12ZZZ0000012345678901 MAN.GYM000456', '-0.40', '2026-11-12');
    $single = bankGroupsRow('PRELIEVO BANCOMAT', '-100.00');

    $lone = resolve(BankGroups::class)->lone()->pluck('id')->all();

    expect($lone)->toContain($single->id, $orphan->id)->not->toContain($paired->id)->toHaveCount(2);
});

it('what a filter takes is in no group', function (): void {
    bankGroupsRow('PAGAMENTO POS ENI STATION', '-30.00', '2026-11-10');
    bankGroupsRow('PAGAMENTO POS ENI STATION', '-31.00', '2026-11-12');
    expect(resolve(BankGroups::class)->all())->toHaveCount(1);

    BankFilter::factory()->create(['family_id' => $this->marco->family_id, 'contains' => ['eni station']]);

    expect(resolve(BankGroups::class)->all())->toBe([]);
});

it('a movement taken out of a group stays out', function (): void {
    $first = bankGroupsRow('PAGAMENTO POS ENI STATION', '-30.00', '2026-11-10');
    bankGroupsRow('PAGAMENTO POS ENI STATION', '-31.00', '2026-11-12');
    bankGroupsRow('PAGAMENTO POS ENI STATION', '-32.00', '2026-11-14');
    $service = resolve(BankGroups::class);
    $key = $service->keyOf($first);

    $first->forceFill(['rejected_proposals' => ['group:'.$key]])->save();
    $groups = resolve(BankGroups::class)->all();

    expect($groups)->toHaveCount(1)
        ->and($groups[0]->transactions)->toHaveCount(2)
        ->and($groups[0]->transactions->pluck('id')->all())->not->toContain($first->id)
        ->and(resolve(BankGroups::class)->lone()->pluck('id')->all())->toBe([$first->id]);

    // The same, when the key is the key of the array
    $first->forceFill(['rejected_proposals' => ['group:'.$key => true]])->save();

    expect(resolve(BankGroups::class)->all()[0]->transactions)->toHaveCount(2);
});

it('groups come biggest first', function (): void {
    foreach ([1, 2] as $i) {
        bankGroupsRow('ADDEBITO SDD EDISON ENERGIA', '-5'.$i.'.00', '2026-11-1'.$i);
    }
    foreach ([1, 2, 3] as $i) {
        bankGroupsRow('PAGAMENTO POS ENI STATION', '-3'.$i.'.00', '2026-11-1'.$i);
    }

    $groups = resolve(BankGroups::class)->all();

    expect($groups)->toHaveCount(2)
        ->and($groups[0]->transactions)->toHaveCount(3)
        ->and($groups[1]->transactions)->toHaveCount(2);
});

it('gives the others of the group of a movement, even when it is sorted already', function (): void {
    $rows = collect([10, 11, 12])->map(fn (int $day): BankTransaction => bankGroupsRow('PAGAMENTO POS ENI STATION', '-30.00', '2026-11-'.$day));
    $rows[0]->forceFill(['ignored_at' => now()])->save();

    $siblings = resolve(BankGroups::class)->siblings($rows[0]);

    expect($siblings->pluck('id')->sort()->values()->all())->toBe([$rows[1]->id, $rows[2]->id]);
});

it('never asks the database once for each transaction', function (): void {
    foreach (range(1, 50) as $i) {
        bankGroupsRow($i % 2 === 0 ? 'PAGAMENTO POS ENI STATION' : BANK_GROUPS_SATISPAY, '-'.(10 + $i).'.00', CarbonImmutable::parse('2026-11-20')->subDays($i)->toDateString());
    }
    bankGroupsRow('COMMISSIONI '.BANK_GROUPS_SATISPAY, '-0.40', '2026-11-19');
    BankFilter::factory()->create(['family_id' => $this->marco->family_id, 'contains' => ['nothing here']]);
    $service = resolve(BankGroups::class);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $groups = $service->all();
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($groups)->toHaveCount(2)->and($queries)->toBeLessThanOrEqual(4);
});

it('finds the groups and the lone movements of the fake bank', function (): void {
    $this->account->forceFill(['bank_connection_id' => null])->save();
    $feed = new FakeBankFeed;
    $today = CarbonImmutable::now('Europe/Rome')->startOfDay();

    foreach ($feed->transactions('s', 'u', $today->subDays(90), $today) as $entry) {
        BankTransaction::factory()->create([
            'family_id' => $this->marco->family_id,
            'financial_account_id' => $this->account->id,
            'provider_id' => $entry->providerId,
            'booked_on' => $entry->bookedOn,
            'value_on' => $entry->valueOn,
            'amount' => $entry->amount,
            'text' => $entry->text,
            'counterparty' => $entry->counterparty,
            'status' => $entry->booked ? BankTransactionStatus::Booked : BankTransactionStatus::Pending,
        ]);
    }
    $service = resolve(BankGroups::class);
    $names = collect($service->all())->map(fn (BankGroup $group): string => mb_strtolower($group->name))->all();

    expect(collect($names)->contains(fn (string $name): bool => str_contains($name, 'satispay')))->toBeTrue()
        ->and($names)->toContain('inps', 'eni')
        ->and(collect($names)->contains(fn (string $name): bool => str_contains($name, 'cashback')))->toBeTrue()
        ->and($service->lone()->count())->toBeGreaterThanOrEqual(8);
});

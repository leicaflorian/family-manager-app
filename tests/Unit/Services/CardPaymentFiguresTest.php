<?php

declare(strict_types=1);

use App\Data\CardBudgetData;
use App\Models\Employment;
use App\Models\InsurancePolicy;
use App\Models\User;
use App\Models\Utility;
use App\Services\CardBudgets;
use App\Services\CardPaymentFigures;
use Carbon\CarbonImmutable;
use Database\Seeders\BianchiFamilySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-10-26 10:00', 'Europe/Rome'));
    $this->seed(BianchiFamilySeeder::class);
});

afterEach(fn () => Date::setTestNow());

it('tells what was paid this year and the average payment with its plan', function (): void {
    insideBianchi(function (): void {
        $today = CarbonImmutable::today('Europe/Rome');
        $luce = Utility::query()->whereRelation('organization', 'name', 'Edison')->sole();
        $user = User::query()->where('email', 'marco.bianchi@example.com')->sole();
        $budget = resolve(CardBudgets::class)->of($luce, $user, $today);
        assert($budget instanceof CardBudgetData);

        $numbers = CardPaymentFigures::of($luce, $budget, $today);

        expect($numbers)->toHaveCount(2)
            ->and($numbers[0]->label)->toBe('Paid in 2026')
            ->and($numbers[0]->amount)->toBe(642.1)
            ->and($numbers[0]->hint)->toBe('9 bills')
            ->and($numbers[1]->label)->toBe('On average')
            ->and($numbers[1]->amount)->toBe(71.34)
            ->and($numbers[1]->hint)->toBe('per bill, :amount planned')
            ->and($numbers[1]->hint_amount)->toBe(72.0);
    });
});

it('counts the payments with the words of the type', function (): void {
    expect(CardPaymentFigures::countText(new Utility, 1))->toBe('1 bill')
        ->and(CardPaymentFigures::countText(new InsurancePolicy, 2))->toBe('2 premiums')
        ->and(CardPaymentFigures::countText(new Employment, 3))->toBe('3 salaries received');
});

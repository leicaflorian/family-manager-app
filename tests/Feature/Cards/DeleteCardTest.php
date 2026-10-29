<?php

declare(strict_types=1);

use App\Cards\CardType;
use App\Cards\CardTypes;
use App\Models\Expense;
use App\Models\Family;
use App\Models\PlannedExpense;
use App\Models\User;
use App\Support\CurrentFamily;
use Database\Seeders\BianchiFamilySeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
    $this->seed(BianchiFamilySeeder::class);
    $this->marco = User::query()->where('email', 'marco.bianchi@example.com')->sole();
});

test('it unlinks the expenses of a deleted card', function (CardType $type): void {
    $this->actingAs($this->marco);
    $familyId = Family::query()->where('name', 'Bianchi')->sole()->id;
    $otherType = collect(CardTypes::all())->first(fn (CardType $other): bool => $other->type() !== $type->type());

    [$card, $expense, $planned, $other] = CurrentFamily::using($familyId, function () use ($type, $familyId, $otherType): array {
        $card = $type->model()::factory()->create(['family_id' => $familyId]);
        $morph = ['family_id' => $familyId, 'card_type' => $card->getMorphClass(), 'card_id' => $card->getKey()];

        return [
            $card,
            Expense::factory()->create($morph),
            PlannedExpense::factory()->create($morph),
            // Same id, but a card of another type: it must stay linked.
            Expense::factory()->create(['family_id' => $familyId, 'card_type' => (new ($otherType->model()))->getMorphClass(), 'card_id' => $card->getKey()]),
        ];
    });

    $this->delete(route('cards.destroy', ['type' => $type->type(), 'id' => $card->getKey()]))->assertRedirect();

    CurrentFamily::using($familyId, function () use ($expense, $planned, $other, $card): void {
        expect(Expense::query()->findOrFail($expense->id))->card_type->toBeNull()->card_id->toBeNull()
            ->and(PlannedExpense::query()->findOrFail($planned->id))->card_type->toBeNull()->card_id->toBeNull()
            ->and(Expense::query()->findOrFail($other->id))->card_id->toBe($card->getKey());
    });
})->with(fn (): array => collect(CardTypes::all())->mapWithKeys(fn (CardType $type): array => [$type->type() => $type])->all());

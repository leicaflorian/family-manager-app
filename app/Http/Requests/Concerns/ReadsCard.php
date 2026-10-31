<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Models\ExpenseCategory;
use App\Support\PresetLink;
use Closure;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * The card an expense or a planned expense refers to, sent as `card` (`<type>:<id>`): a card of the family and,
 * when the category of the expense (with its main category's) has a card type, a card of that type. Without a
 * category, or with one without a type, any card of the family is fine. The card already linked to the record, or
 * the one of the planned expense a payment pays, is fine whatever its type.
 */
trait ReadsCard
{
    /**
     * The category the expense keeps when the form does not send one: the one already saved, null on a new one.
     */
    abstract protected function savedCategoryId(): ?int;

    /**
     * The card already linked to the record being changed, as `<type>:<id>`: it stays valid even when it is not of
     * the type of the category (the category may have changed type after it was linked). Null on a new one.
     */
    abstract protected function savedCard(): ?string;

    /**
     * Another card that stays valid whatever the category: on a payment, the card of the planned expense it pays.
     * None by default.
     */
    protected function plannedCard(): ?string
    {
        return null;
    }

    /**
     * The rules of the field `card`.
     *
     * @return array{card: list<string|Closure>}
     */
    protected function cardRules(): array
    {
        return ['card' => ['nullable', 'string', $this->cardRule(...)]];
    }

    /**
     * The card sent as the columns of the pair, both empty without one.
     *
     * @return array{card_type: ?string, card_id: ?int}
     */
    public function cardColumns(): array
    {
        $card = PresetLink::from($this->input('card'));

        return ['card_type' => $card['type'] ?? null, 'card_id' => $card['id'] ?? null];
    }

    /**
     * Checks that the card is one of the family, of the type of the category when it has one, unless it is the card
     * already linked or the one of the planned expense paid.
     *
     * @param  string  $attribute  the field
     * @param  mixed  $value  the `type:id` sent
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail  reports the error
     */
    private function cardRule(string $attribute, mixed $value, Closure $fail): void
    {
        $card = PresetLink::from($value);

        if ($card === null) {
            $fail('validation.exists')->translate();

            return;
        }

        $sent = PresetLink::key($card['type'], $card['id']);

        if ($sent === $this->savedCard() || $sent === $this->plannedCard()) {
            return;
        }

        $category = $this->exists('category_id') ? $this->input('category_id') : $this->savedCategoryId();
        $type = is_numeric($category) ? ExpenseCategory::cardTypes()[(int) $category] ?? null : null;

        if ($type !== null && $type !== $card['type']) {
            $fail(__('Choose a card of the type of the category.'));
        }
    }
}

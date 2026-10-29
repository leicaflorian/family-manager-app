import { trans } from 'laravel-vue-i18n';
import { computed, toValue, watch } from 'vue';
import type { ComputedRef, MaybeRefOrGetter, Ref } from 'vue';

/** The choices of the card field, as `BudgetFormOptions` sends them. */
export interface CardChoiceOptions {
    /** Every card of the family, `type:id`. */
    cards: App.Data.OptionData[];
    /** The card type of each category that has one, by the id of the category (an empty list when none has). */
    category_card_types: Record<string, string>;
    /** The name of each card type, by type. */
    card_type_labels: Record<string, string>;
}

/**
 * The card an expense or a planned expense refers to, driven by its category:
 * a category with a card type names the field after the type and offers only
 * its cards; without one the field is «Refers to» with every card. The card
 * chosen stays among the choices even when it is of another type (a card
 * already linked, or the one of the planned expense paid). When the category
 * passes to another type, the card chosen before of a different type is
 * emptied; a card set together with the category, or a category without type,
 * keeps it.
 *
 * @param category - the id of the category chosen, empty or null for none
 * @param card - the card chosen, `type:id`, emptied when the category changes type
 * @param options - the cards, the types of the categories and the names of the types
 * @returns the label of the field and its choices
 */
export function useCardChoice(
    category: MaybeRefOrGetter<string | null | undefined>,
    card: Ref<string | null>,
    options: CardChoiceOptions,
): {
    label: ComputedRef<string>;
    choices: ComputedRef<App.Data.OptionData[]>;
} {
    /** The card type of the category chosen, or null. */
    const type = computed((): string | null => {
        const id = toValue(category);

        // PHP sends an empty map as a list
        if (!id || Array.isArray(options.category_card_types)) {
            return null;
        }

        return options.category_card_types[id] ?? null;
    });

    /** The name of the field: the type of the category, «Refers to» without one. */
    const label = computed((): string =>
        type.value === null
            ? trans('Refers to')
            : (options.card_type_labels[type.value] ?? trans('Refers to')),
    );

    /**
     * The cards offered: those of the type of the category (every card without
     * one), followed by the card chosen when it is of another type.
     */
    const choices = computed((): App.Data.OptionData[] => {
        if (type.value === null) {
            return options.cards;
        }

        const ofType = options.cards.filter((choice) =>
            choice.value.startsWith(`${type.value}:`),
        );
        const chosen = options.cards.find(
            (choice) => choice.value === card.value,
        );

        return chosen && !ofType.includes(chosen)
            ? [...ofType, chosen]
            : ofType;
    });

    // Sync, so that only a card chosen before the category changes is emptied:
    // a form that sets the category and then its card together keeps the card
    watch(
        type,
        (current) => {
            if (
                current !== null &&
                card.value &&
                !card.value.startsWith(`${current}:`)
            ) {
                card.value = null;
            }
        },
        { flush: 'sync' },
    );

    return { label, choices };
}

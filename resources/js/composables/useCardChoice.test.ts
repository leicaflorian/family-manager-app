import { describe, expect, it } from 'vite-plus/test';
import { nextTick, ref } from 'vue';
import { useCardChoice } from './useCardChoice';

/** The cards, categories and types as `BudgetFormOptions` sends them. */
const options = {
    cards: [
        { value: 'utility:3', label: 'Utility · Luce - Edison', help: null },
        { value: 'vehicle:1', label: 'Vehicle · Panda', help: null },
        { value: 'vehicle:2', label: 'Vehicle · Vespa', help: null },
    ],
    category_card_types: { '10': 'vehicle', '11': 'utility' },
    card_type_labels: { utility: 'Utility', vehicle: 'Vehicle' },
};

describe('useCardChoice', () => {
    it('names the field after the type of the category and offers only its cards', () => {
        const { label, choices } = useCardChoice(ref('10'), ref(null), options);

        expect(label.value).toBe('Vehicle');
        expect(choices.value.map((choice) => choice.value)).toEqual([
            'vehicle:1',
            'vehicle:2',
        ]);
    });

    it('is «Refers to» with every card for a category without type or no category', () => {
        const category = ref<string | null>('2');
        const { label, choices } = useCardChoice(category, ref(null), options);

        expect(label.value).toBe('Refers to');
        expect(choices.value).toHaveLength(3);

        category.value = null;

        expect(label.value).toBe('Refers to');
        expect(choices.value).toHaveLength(3);
    });

    it('takes the types the server sends as an empty list when no category has one', () => {
        const { label, choices } = useCardChoice(ref('10'), ref(null), {
            ...options,
            category_card_types: [] as unknown as Record<string, string>,
        });

        expect(label.value).toBe('Refers to');
        expect(choices.value).toHaveLength(3);
    });

    it('empties the card when the category passes to another type', async () => {
        const category = ref<string | null>('10');
        const card = ref<string | null>('vehicle:1');
        useCardChoice(category, card, options);

        category.value = '11';
        await nextTick();

        expect(card.value).toBeNull();
    });

    it('keeps the card when the category passes to one of the same type', async () => {
        const category = ref<string | null>(null);
        const card = ref<string | null>('vehicle:1');
        useCardChoice(category, card, options);

        category.value = '10';
        await nextTick();

        expect(card.value).toBe('vehicle:1');
    });

    it('keeps the card chosen when the category has no type', async () => {
        const category = ref<string | null>('10');
        const card = ref<string | null>('vehicle:1');
        useCardChoice(category, card, options);

        category.value = '2';
        await nextTick();

        expect(card.value).toBe('vehicle:1');
    });
});

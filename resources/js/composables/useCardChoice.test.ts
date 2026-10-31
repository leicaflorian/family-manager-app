import { describe, expect, it } from 'vite-plus/test';
import { nextTick, ref } from 'vue';
import type { Ref } from 'vue';
import { useCardChoice } from './useCardChoice';

/** The cards, categories and types as `BudgetFormOptions` sends them. */
const options = {
    cards: [
        {
            value: 'utility:3',
            label: 'Utility · Luce - Edison',
            person_id: '3',
            organization_id: '6',
            category_id: '11',
        },
        {
            value: 'vehicle:1',
            label: 'Vehicle · Panda',
            person_id: '4',
            organization_id: null,
            category_id: '10',
        },
        {
            value: 'vehicle:2',
            label: 'Vehicle · Vespa',
            person_id: null,
            organization_id: null,
            category_id: '10',
        },
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

    it('keeps the card chosen of another type among the choices', () => {
        const { choices } = useCardChoice(ref('11'), ref('vehicle:2'), options);

        expect(choices.value.map((choice) => choice.value)).toEqual([
            'utility:3',
            'vehicle:2',
        ]);
    });

    it('keeps a card of another type set together with the category', async () => {
        const category = ref<string | null>(null);
        const card = ref<string | null>(null);
        useCardChoice(category, card, options);

        category.value = '11';
        card.value = 'vehicle:2';
        await nextTick();

        expect(card.value).toBe('vehicle:2');
    });

    /**
     * The fields a card fills in, empty.
     *
     * @returns the category, the holder and the organization
     */
    function emptyFields(): {
        category_id: Ref<string | null>;
        person_id: Ref<string | null>;
        organization_id: Ref<string | null>;
    } {
        return {
            category_id: ref<string | null>(''),
            person_id: ref<string | null>(null),
            organization_id: ref<string | null>(''),
        };
    }

    it('fills the empty category, holder and organization from the card chosen', async () => {
        const card = ref<string | null>(null);
        const fields = emptyFields();
        useCardChoice(fields.category_id, card, options, fields);

        card.value = 'utility:3';
        await nextTick();

        expect(fields.category_id.value).toBe('11');
        expect(fields.person_id.value).toBe('3');
        expect(fields.organization_id.value).toBe('6');
        expect(card.value).toBe('utility:3');
    });

    it('leaves the fields already filled in as they are', async () => {
        const card = ref<string | null>(null);
        const fields = emptyFields();
        fields.person_id.value = '9';
        fields.organization_id.value = '8';
        useCardChoice(fields.category_id, card, options, fields);

        card.value = 'utility:3';
        await nextTick();

        expect(fields.person_id.value).toBe('9');
        expect(fields.organization_id.value).toBe('8');
        expect(fields.category_id.value).toBe('11');
    });

    it('leaves empty what the card does not know', async () => {
        const card = ref<string | null>(null);
        const fields = emptyFields();
        useCardChoice(fields.category_id, card, options, fields);

        card.value = 'vehicle:2';
        await nextTick();

        expect(fields.person_id.value).toBeNull();
        expect(fields.organization_id.value).toBe('');
    });

    it('fills nothing from the card the form starts with', async () => {
        const fields = emptyFields();
        useCardChoice(fields.category_id, ref('utility:3'), options, fields);
        await nextTick();

        expect(fields.category_id.value).toBe('');
        expect(fields.person_id.value).toBeNull();
    });
});

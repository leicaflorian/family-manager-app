import { describe, expect, it } from 'vite-plus/test';
import { reactive } from 'vue';
import type { DocumentFormValues } from '@/lib/documents';
import { newDocument } from '@/lib/documents';
import { useReadingFill } from './useReadingFill';

/**
 * A form like the one of the page, starting from the name of the file.
 */
function start(): { form: DocumentFormValues; initial: DocumentFormValues } {
    const initial = newDocument('bolletta-edison.pdf');

    return {
        initial,
        form: reactive({ ...initial, links: [...initial.links] }),
    };
}

describe('useReadingFill', () => {
    it('writes the reading in the fields the person has not touched', () => {
        const { form, initial } = start();
        const fill = useReadingFill(form, initial, 'untouched');

        fill.apply({
            title: 'Luce ottobre',
            category_id: '4',
            date: '2026-10-15',
        });

        expect(form.title).toBe('Luce ottobre');
        expect(form.category_id).toBe('4');
        expect(form.date).toBe('2026-10-15');
    });

    it('keeps a title the person wrote', () => {
        const { form, initial } = start();
        const fill = useReadingFill(form, initial, 'untouched');
        form.title = 'My own title';

        fill.apply({ title: 'Luce ottobre', person_id: '7' });

        expect(form.title).toBe('My own title');
        expect(form.person_id).toBe('7');
    });

    it('in empty mode fills only the empty fields', () => {
        const { form, initial } = start();
        const fill = useReadingFill(form, initial, 'empty');

        fill.apply({ title: 'Luce ottobre', person_id: '7' });

        expect(form.title).toBe('bolletta-edison.pdf');
        expect(form.person_id).toBe('7');
    });

    it('adds the links of the reading to the ones already chosen', () => {
        const { form, initial } = start();
        form.links = [{ type: 'vehicle', id: 1 }];
        const fill = useReadingFill(form, initial, 'untouched');

        fill.apply({
            links: [
                { type: 'vehicle', id: 1 },
                { type: 'home', id: 2 },
            ],
        });

        expect(form.links).toEqual([
            { type: 'vehicle', id: 1 },
            { type: 'home', id: 2 },
        ]);
    });

    it('restores the values read, and keeps the links added by hand', () => {
        const { form, initial } = start();
        const fill = useReadingFill(form, initial, 'untouched');
        fill.apply({ title: 'Luce ottobre', links: [{ type: 'home', id: 2 }] });
        form.title = 'Changed';
        form.links = [{ type: 'vehicle', id: 1 }];

        expect(fill.canRestore.value).toBe(true);
        fill.restore();

        expect(form.title).toBe('Luce ottobre');
        expect(form.links).toEqual([
            { type: 'vehicle', id: 1 },
            { type: 'home', id: 2 },
        ]);
        expect(fill.canRestore.value).toBe(false);
    });

    it('cannot restore before anything was read', () => {
        const { form, initial } = start();

        expect(
            useReadingFill(form, initial, 'untouched').canRestore.value,
        ).toBe(false);
    });

    it('does not refill a field the person emptied when the same reading arrives again', () => {
        const { form, initial } = start();
        const fill = useReadingFill(form, initial, 'untouched');
        const values = { category_id: '4', title: 'Luce ottobre' };
        fill.apply(values);
        form.category_id = '';
        form.title = 'bolletta-edison.pdf';

        fill.apply({ ...values });

        expect(form.category_id).toBe('');
        expect(form.title).toBe('bolletta-edison.pdf');
    });

    it('updates the fields still holding the first reading with a different second reading', () => {
        const { form, initial } = start();
        const fill = useReadingFill(form, initial, 'untouched');
        fill.apply({ title: 'Luce ottobre', person_id: '7' });
        form.person_id = '8';

        fill.apply({ title: 'Luce novembre', person_id: '9' });

        expect(form.title).toBe('Luce novembre');
        expect(form.person_id).toBe('8');
    });
});

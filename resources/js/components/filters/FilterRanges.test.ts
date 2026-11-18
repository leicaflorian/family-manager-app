import { fireEvent, screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vite-plus/test';
import { renderPage, settle } from '@/test/page';
import FilterRanges from './FilterRanges.vue';

const none = { min: null, max: null, from: null, to: null };

describe('FilterRanges', () => {
    it('names the days and says what happens without them', () => {
        renderPage(FilterRanges, {
            filters: { ...none, from: '2026-11-01' },
            dateLabel: 'Next due date',
            help: 'Without dates you see them all.',
        });

        expect(
            screen.getByRole('group', { name: 'Amount' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('group', { name: 'Next due date' }),
        ).toBeInTheDocument();
        expect(screen.getByLabelText('From')).toHaveValue('01/11/2026');
        expect(
            screen.getByText('Without dates you see them all.'),
        ).toBeInTheDocument();
    });

    it('sends nothing while the values stay those in use', async () => {
        const view = renderPage(FilterRanges, {
            filters: { min: 20, max: 100, from: '2026-11-01', to: null },
            dateLabel: 'Date',
            help: '',
        });
        await settle();

        expect(view.emitted('change')).toBeUndefined();
    });

    it('sends only the value that changed', async () => {
        const view = renderPage(FilterRanges, {
            filters: { min: 20, max: null, from: null, to: null },
            dateLabel: 'Date',
            help: '',
        });
        await settle();

        const from = screen.getByLabelText('From');
        await fireEvent.input(from, { target: { value: '05/11/2026' } });
        await fireEvent.blur(from);
        await settle();

        expect(view.emitted('change')?.at(-1)).toEqual([
            { from: '2026-11-05' },
        ]);
    });

    it('offers to remove the filters only when told', async () => {
        const hidden = renderPage(FilterRanges, {
            filters: none,
            dateLabel: 'Date',
            help: '',
        });
        expect(
            screen.queryByRole('button', { name: 'Remove the filters' }),
        ).not.toBeInTheDocument();
        hidden.unmount();

        const view = renderPage(FilterRanges, {
            filters: none,
            dateLabel: 'Date',
            help: '',
            removable: true,
        });
        await userEvent.click(
            screen.getByRole('button', { name: 'Remove the filters' }),
        );

        expect(view.emitted('remove')).toHaveLength(1);
    });
});

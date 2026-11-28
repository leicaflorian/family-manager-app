import { router } from '@inertiajs/vue3';
import { screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { bankFilters } from '@/test/fixtures';
import { renderPage } from '@/test/page';
import BankFilterBox from './BankFilterBox.vue';

const luce = { ...bankFilters[0]!, taken: 3, missing: null };

afterEach(() => {
    vi.restoreAllMocks();
});

describe('BankFilterBox', () => {
    it('shows the criteria, the action, how many movements of the last 90 days and Edit the filter', async () => {
        const visit = vi.spyOn(router, 'visit').mockImplementation(() => {});
        renderPage(BankFilterBox, {
            filter: luce,
            createUrl: '/budget/bank/filters/create?planned=expense:7',
        });

        expect(screen.getByText('Luce')).toBeInTheDocument();
        expect(
            screen.getByText('3 movements in the last 90 days'),
        ).toBeInTheDocument();
        expect(screen.getByText('Matches:')).toBeInTheDocument();
        expect(screen.getByText('EDISON')).toBeInTheDocument();
        expect(screen.getByText('Action:')).toBeInTheDocument();
        expect(screen.getByText('«Luce»').tagName).toBe('STRONG');

        const edit = screen.getByRole('link', { name: 'Edit the filter' });
        expect(edit).toHaveAttribute('href', '/budget/bank/filters/2/edit');
        await userEvent.click(edit);
        expect(visit).toHaveBeenCalled();
        expect(
            screen.queryByRole('link', { name: 'Create the filter' }),
        ).not.toBeInTheDocument();
    });

    it('without a filter offers Create the filter', () => {
        renderPage(BankFilterBox, {
            filter: null,
            createUrl: '/budget/bank/filters/create?planned=expense:7',
        });

        expect(
            screen.getByRole('link', { name: 'Create the filter' }),
        ).toHaveAttribute(
            'href',
            '/budget/bank/filters/create?planned=expense:7',
        );
        expect(screen.queryByText('Matches:')).not.toBeInTheDocument();
    });

    it('without a filter and without the right to make one offers nothing to press', () => {
        renderPage(BankFilterBox, { filter: null, createUrl: null });

        expect(screen.queryByRole('link')).not.toBeInTheDocument();
        expect(screen.getByText('No filter yet')).toBeInTheDocument();
    });

    it('a new planned item says the filter is made after saving', () => {
        renderPage(BankFilterBox, {
            filter: null,
            createUrl: null,
            isNew: true,
        });

        expect(
            screen.getByText('The filter is made after saving.'),
        ).toBeInTheDocument();
        expect(screen.queryByRole('link')).not.toBeInTheDocument();
    });
});

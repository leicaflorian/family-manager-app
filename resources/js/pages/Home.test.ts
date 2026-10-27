import { screen, within } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import { healthCard } from '@/test/fixtures';
import { renderPage } from '@/test/page';
import Home from './Home.vue';

const policy = {
    ...healthCard,
    id: 20,
    title: 'Polizza RC auto 2026',
    expiry_date: '2026-11-20',
};
const bill = {
    ...healthCard,
    id: 1,
    title: 'Bolletta luce di ottobre',
    date: '2026-10-26',
    expiry_date: null,
};

describe('Home', () => {
    it('greets with the day, then the documents expiring and the last uploaded', () => {
        renderPage(Home, {
            firstName: 'Marco',
            today: '2026-10-26',
            expiring: [healthCard, policy],
            recent: [bill],
            hasDocuments: true,
        });

        expect(screen.getByText('Monday 26 October 2026')).toBeInTheDocument();
        expect(
            screen.getByRole('heading', { name: 'Hi, Marco', level: 1 }),
        ).toBeInTheDocument();
        const expiring = screen
            .getByRole('heading', { name: 'Expiring' })
            .closest('section') as HTMLElement;
        const rows = within(expiring).getAllByRole('link');
        expect(rows[0]).toHaveTextContent('Tessera sanitaria');
        expect(within(rows[0]).getByText('Expired on 20/10/2026')).toHaveClass(
            'text-overdue',
        );
        expect(within(rows[1]).getByText('Expires on 20/11/2026')).toHaveClass(
            'text-warning',
        );
        const recent = screen
            .getByRole('heading', { name: 'Recently uploaded' })
            .closest('section') as HTMLElement;
        expect(
            within(recent).getByRole('link', {
                name: /Bolletta luce di ottobre/,
            }),
        ).toHaveAttribute('href', '/documents/1');
    });

    it('says so when nothing expires', () => {
        renderPage(Home, {
            firstName: 'Marco',
            today: '2026-10-26',
            expiring: [],
            recent: [bill],
            hasDocuments: true,
        });

        expect(
            screen.getByText('Nothing expires in the next 60 days.'),
        ).toBeInTheDocument();
    });

    it('without documents invites to upload the first', () => {
        renderPage(Home, {
            firstName: 'Marco',
            today: '2026-10-26',
            expiring: [],
            recent: [],
            hasDocuments: false,
        });

        expect(
            screen.getByRole('link', { name: 'Upload the first document' }),
        ).toHaveAttribute('href', '/upload');
        expect(
            screen.queryByRole('heading', { name: 'Expiring' }),
        ).not.toBeInTheDocument();
    });
});

import { screen, within } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import { healthCard, pendingPayslip } from '@/test/fixtures';
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

const photo = {
    ...pendingPayslip,
    id: 13,
    name: 'IMG_2041.heic',
    mime_type: 'image/heic',
    size: 1_258_291,
    url: '/files/13',
    title: 'IMG_2041',
};
const home = {
    firstName: 'Marco',
    today: '2026-10-26',
    expiring: [],
    recent: [bill],
    hasDocuments: true,
};

describe('Home', () => {
    it('greets with the day, then the documents expiring and the last uploaded', () => {
        renderPage(Home, {
            ...home,
            expiring: [healthCard, policy],
            pending: [],
            pendingCount: 0,
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
        renderPage(Home, { ...home, pending: [], pendingCount: 0 });

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
            pending: [],
            pendingCount: 0,
        });

        expect(
            screen.getByRole('link', { name: 'Upload the first document' }),
        ).toHaveAttribute('href', '/upload');
        expect(
            screen.queryByRole('heading', { name: 'Expiring' }),
        ).not.toBeInTheDocument();
    });

    it('shows the files waiting, with their size, and Finish opens Upload', () => {
        renderPage(Home, {
            ...home,
            pending: [pendingPayslip, photo],
            pendingCount: 2,
        });

        const waiting = screen
            .getByRole('heading', { name: 'Waiting' })
            .closest('section') as HTMLElement;
        expect(
            within(waiting).getByText('Busta paga novembre 2026'),
        ).toBeInTheDocument();
        expect(within(waiting).getByText('117 KB')).toBeInTheDocument();
        expect(within(waiting).getByText('IMG_2041')).toBeInTheDocument();
        expect(within(waiting).getByText('1,2 MB')).toBeInTheDocument();
        expect(
            within(waiting).getByRole('link', { name: 'Finish' }),
        ).toHaveAttribute('href', '/upload');
        expect(within(waiting).queryByText(/more/)).not.toBeInTheDocument();
    });

    it('says how many more files wait beyond the ones shown', () => {
        renderPage(Home, {
            ...home,
            pending: [pendingPayslip, photo],
            pendingCount: 7,
        });

        expect(screen.getByText('+5 more')).toBeInTheDocument();
    });

    it('shows the files waiting even before the first document', () => {
        renderPage(Home, {
            ...home,
            recent: [],
            hasDocuments: false,
            pending: [pendingPayslip],
            pendingCount: 1,
        });

        expect(
            screen.getByRole('heading', { name: 'Waiting' }),
        ).toBeInTheDocument();
    });

    it('without files waiting has no panel for them', () => {
        renderPage(Home, { ...home, pending: [], pendingCount: 0 });

        expect(
            screen.queryByRole('heading', { name: 'Waiting' }),
        ).not.toBeInTheDocument();
    });
});

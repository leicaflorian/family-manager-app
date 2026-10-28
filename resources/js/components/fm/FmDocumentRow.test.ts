import { render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import FmDocumentRow from './FmDocumentRow.vue';

const document: App.Data.DocumentData = {
    id: 5,
    title: 'Tessera sanitaria',
    category: 'Anagrafica e pratiche',
    person: 'Giulia',
    organization: null,
    date: null,
    expiry_date: '2026-10-20',
    status: 'active',
    mime_type: 'application/pdf',
};

describe('FmDocumentRow', () => {
    it('leads to the document, with what it is and its date', () => {
        render(FmDocumentRow, {
            props: {
                document: { ...document, date: '2020-10-20' },
                href: '/documents/5',
            },
        });

        const link = screen.getByRole('link');
        expect(link).toHaveAttribute('href', '/documents/5');
        expect(link).toHaveTextContent('Tessera sanitaria');
        expect(link).toHaveTextContent('Anagrafica e pratiche · Giulia');
        expect(link).toHaveTextContent('20/10/2020');
    });

    it('without a date shows the expiry', () => {
        render(FmDocumentRow, { props: { document, href: '/documents/5' } });

        expect(screen.getByRole('link')).toHaveTextContent(
            'expires 20/10/2026',
        );
    });

    it('with expiry shows the expiry in its colour: red once passed', () => {
        render(FmDocumentRow, {
            props: {
                document,
                href: '/documents/5',
                expiry: true,
                today: '2026-10-27',
            },
        });

        const subtitle = screen.getByText('Expired on 20/10/2026');
        expect(subtitle).toHaveClass('text-overdue');
    });

    it('with expiry, a date still ahead is orange', () => {
        render(FmDocumentRow, {
            props: {
                document: { ...document, expiry_date: '2026-12-11' },
                href: '/documents/5',
                expiry: true,
                today: '2026-10-27',
            },
        });

        expect(screen.getByText('Expires on 11/12/2026')).toHaveClass(
            'text-warning',
        );
    });

    it('without href shows the document without leading anywhere', () => {
        render(FmDocumentRow, { props: { document } });

        expect(screen.queryByRole('link')).not.toBeInTheDocument();
        expect(screen.getByText('Tessera sanitaria')).toBeInTheDocument();
    });
});

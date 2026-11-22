import { userEvent } from '@testing-library/user-event';
import { render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import FmPinnedDocument from './FmPinnedDocument.vue';

const pinned = (
    overrides: Partial<App.Data.DocumentData> = {},
    rest: Partial<App.Data.PinnedDocumentData> = {},
): App.Data.PinnedDocumentData => ({
    document: {
        id: 7,
        title: 'Patente B',
        category: null,
        person: null,
        organization: null,
        date: null,
        expiry_date: null,
        status: 'active',
        mime_type: 'application/pdf',
        file_url: '/files/7',
        ...overrides,
    },
    area: null,
    pinned_by: 'Laura',
    ...rest,
});

describe('FmPinnedDocument', () => {
    it('leads to the document with its title', () => {
        render(FmPinnedDocument, {
            props: { pinned: pinned(), href: '/documents/7' },
        });

        const link = screen.getByRole('link', { name: /Patente B/ });
        expect(link).toHaveAttribute('href', '/documents/7');
    });

    it('shows an expiry passed in red', () => {
        render(FmPinnedDocument, {
            props: {
                pinned: pinned({ expiry_date: '2026-11-03' }),
                href: '/documents/7',
                today: '2026-11-22',
            },
        });

        expect(screen.getByText('Expired on 03/11/2026')).toHaveClass(
            'text-overdue',
        );
    });

    it('shows an expiry ahead in grey', () => {
        render(FmPinnedDocument, {
            props: {
                pinned: pinned({ expiry_date: '2029-05-24' }),
                href: '/documents/7',
                today: '2026-11-22',
            },
        });

        const line = screen.getByText('Expires on 24/05/2029');
        expect(line).toHaveClass('text-ink-soft');
        expect(line).not.toHaveClass('text-warning');
    });

    it('without an expiry shows what it is and its date', () => {
        render(FmPinnedDocument, {
            props: {
                pinned: pinned({
                    organization: 'Rossi Impianti',
                    date: '2024-02-02',
                }),
                href: '/documents/7',
            },
        });

        expect(
            screen.getByText('Rossi Impianti · 02/02/2024'),
        ).toBeInTheDocument();
    });

    it('colours the icon with its area, neutral without one', () => {
        const first = render(FmPinnedDocument, {
            props: { pinned: pinned({}, { area: 'work' }), href: '/d' },
        });
        expect(screen.getByTestId('pinned-icon')).toHaveClass(
            'bg-work-soft',
            'text-work-text',
        );
        first.unmount();

        render(FmPinnedDocument, {
            props: { pinned: pinned(), href: '/d' },
        });
        expect(screen.getByTestId('pinned-icon')).toHaveClass(
            'bg-canvas',
            'text-ink-soft',
        );
    });

    it('with canUnpin has a pressed button that emits unpin', async () => {
        const view = render(FmPinnedDocument, {
            props: { pinned: pinned(), href: '/d', canUnpin: true },
        });

        const button = screen.getByRole('button', {
            name: 'Unpin «Patente B»',
        });
        expect(button).toHaveAttribute('aria-pressed', 'true');
        expect(button).toHaveAttribute('title', 'Pinned by Laura · unpin');

        await userEvent.click(button);
        expect(view.emitted().unpin).toHaveLength(1);
    });

    it('without the name of who pinned it the title says only pinned', () => {
        render(FmPinnedDocument, {
            props: {
                pinned: pinned({}, { pinned_by: null }),
                href: '/d',
                canUnpin: true,
            },
        });

        expect(
            screen.getByRole('button', { name: 'Unpin «Patente B»' }),
        ).toHaveAttribute('title', 'Pinned · unpin');
    });

    it('without canUnpin has no button', () => {
        render(FmPinnedDocument, {
            props: { pinned: pinned(), href: '/d' },
        });

        expect(screen.queryByRole('button')).not.toBeInTheDocument();
    });
});

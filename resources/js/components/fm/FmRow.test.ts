import { fireEvent, render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import FmRow from './FmRow.vue';

describe('FmRow', () => {
    it('is a link covering the whole row with href', () => {
        render(FmRow, {
            props: {
                title: 'Electricity bill',
                subtitle: 'Electricity - Edison · in 3 days',
                href: '/documents/1',
            },
            slots: { after: '71,30 €' },
        });

        const link = screen.getByRole('link');
        expect(link).toHaveAttribute('href', '/documents/1');
        expect(link).toHaveTextContent('Electricity bill');
        expect(link).toHaveTextContent('Electricity - Edison · in 3 days');
        expect(link).toHaveTextContent('71,30 €');
    });

    it('marks the row that is open, and only that one', () => {
        render({
            components: { FmRow },
            template: `
                <FmRow title="Electricity bill" href="/documents?document=1" current />
                <FmRow title="Water bill" href="/documents?document=2" />
            `,
        });

        expect(screen.getByRole('link', { current: 'page' })).toHaveTextContent(
            'Electricity bill',
        );
        expect(
            screen.getByRole('link', { name: /Water bill/ }),
        ).not.toHaveAttribute('aria-current');
    });

    it('shows the open row on the light blue background, not a transparent one', () => {
        render(FmRow, {
            props: {
                title: 'Electricity bill',
                href: '/documents?document=1',
                current: true,
            },
        });

        // Both classes set the background: in the CSS bg-transparent comes later and would win.
        const link = screen.getByRole('link');
        expect(link).toHaveClass('bg-action-soft');
        expect(link).not.toHaveClass('bg-transparent');
    });

    it('turns light blue under the pointer, as the open row', () => {
        render(FmRow, {
            props: { title: 'Water bill', href: '/documents?document=2' },
        });

        expect(screen.getByRole('link')).toHaveClass('hover:bg-action-soft');
    });

    it('is a button that sends click with button', async () => {
        const result = render(FmRow, {
            props: { title: 'Electricity', button: true },
        });

        await fireEvent.click(
            screen.getByRole('button', { name: /Electricity/ }),
        );

        expect(result.emitted('click')).toHaveLength(1);
    });

    it('is not a link without href', () => {
        render(FmRow, { props: { title: 'Marco Bianchi' } });

        expect(screen.queryByRole('link')).toBeNull();
        expect(screen.getByText('Marco Bianchi')).toBeInTheDocument();
    });

    it('gives the dot the colour of the area', () => {
        const result = render(FmRow, {
            props: { title: 'Electricity bill', area: 'home' },
        });

        expect(result.container.querySelector('[data-dot]')).toHaveClass(
            'bg-home',
        );
    });

    it('writes the overdue subtitle in red', () => {
        render(FmRow, {
            props: {
                title: 'Health card',
                subtitle: 'Expired on 20/10/2026',
                tone: 'overdue',
            },
        });

        expect(screen.getByText('Expired on 20/10/2026')).toHaveClass(
            'text-overdue',
        );
    });
});

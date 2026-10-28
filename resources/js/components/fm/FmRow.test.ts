import { fireEvent, render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import FmRow from './FmRow.vue';

describe('FmRow', () => {
    it('keeps the title on one line, with the ellipsis in the middle so its end shows', () => {
        render(FmRow, {
            props: {
                title: 'Polizza RC auto 2026',
                subtitle: 'Assicurazioni e fondi · Marco · UnipolSai',
            },
        });

        // Read whole, seen in two pieces
        expect(screen.getByText('Polizza RC auto 2026')).toHaveClass('sr-only');
        const title = screen.getByTitle('Polizza RC auto 2026');
        expect(title).toHaveClass('flex', 'whitespace-nowrap');
        const [start, end] = title.querySelectorAll('[data-piece]');
        expect(start).toHaveTextContent('Polizza RC a');
        expect(start).toHaveClass('text-ellipsis');
        expect(start).toHaveAttribute('aria-hidden', 'true');
        expect(end).toHaveTextContent('uto 2026');
        expect(end).toHaveClass('shrink-0');
        // A space at the edge of a piece stays: "luce di| ottobre"
        expect(start).toHaveClass('whitespace-pre');
        expect(end).toHaveClass('whitespace-pre');
    });

    it('keeps the subtitle on one line, cut at its end', () => {
        render(FmRow, {
            props: {
                title: 'Polizza RC auto 2026',
                subtitle: 'Assicurazioni e fondi · Marco · UnipolSai',
            },
        });

        expect(
            screen.getByText('Assicurazioni e fondi · Marco · UnipolSai'),
        ).toHaveClass('truncate');
    });

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

    it('with external opens the address in a new tab', () => {
        render(FmRow, {
            props: {
                title: 'Libretto.pdf',
                href: 'blob:chosen',
                external: true,
            },
        });

        const link = screen.getByRole('link', { name: /Libretto.pdf/ });
        expect(link).toHaveAttribute('href', 'blob:chosen');
        expect(link).toHaveAttribute('target', '_blank');
        expect(link).toHaveAttribute('rel', 'noopener');
    });

    it('without arrow leaves out the arrow of a link with nothing on the right', () => {
        render(FmRow, {
            props: {
                title: 'Libretto.pdf',
                href: '/documents/1',
                arrow: false,
            },
        });

        expect(screen.getByRole('link').querySelector('svg')).toBeNull();
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

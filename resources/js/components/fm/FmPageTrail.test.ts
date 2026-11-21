import { router } from '@inertiajs/vue3';
import { fireEvent, screen, within } from '@testing-library/vue';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import type { TrailPiece } from '@/lib/pageTrail';
import { renderPage } from '@/test/page';
import FmPageTrail from './FmPageTrail.vue';

const trail: TrailPiece[] = [
    { label: 'Famiglia', href: '/family' },
    { label: 'Persone', href: '/people' },
    { label: 'Marco Bianchi' },
];

afterEach(() => {
    vi.restoreAllMocks();
});

describe('FmPageTrail', () => {
    it('on the phone is the link to the page above, without the trail', () => {
        renderPage(FmPageTrail, { mode: 'phone', trail, section: false });

        expect(screen.getByRole('link', { name: 'Persone' })).toHaveAttribute(
            'href',
            '/people',
        );
        expect(
            screen.queryByRole('navigation', { name: 'Where you are' }),
        ).not.toBeInTheDocument();
    });

    it('on the phone, opened without history, the link visits the page above once', async () => {
        const visit = vi.spyOn(router, 'visit').mockImplementation(() => {});
        renderPage(FmPageTrail, { mode: 'phone', trail, section: false });

        await fireEvent.click(screen.getByRole('link', { name: 'Persone' }));

        expect(visit).toHaveBeenCalledTimes(1);
        expect(visit).toHaveBeenCalledWith('/people');
    });

    it('on the phone shows nothing when no piece has a link', () => {
        const { container } = renderPage(FmPageTrail, {
            mode: 'phone',
            trail: [{ label: 'A' }, { label: 'B' }],
            section: false,
        });

        expect(screen.queryByRole('link')).not.toBeInTheDocument();
        expect(container).toHaveTextContent('');
    });

    it('on the tablet is Back and the trail in a row', () => {
        renderPage(FmPageTrail, { mode: 'tablet', trail, section: false });

        expect(screen.getByRole('link', { name: 'Back' })).toBeInTheDocument();
        const nav = screen.getByRole('navigation', { name: 'Where you are' });
        expect(
            within(nav).getByRole('link', { name: 'Famiglia' }),
        ).toBeInTheDocument();
        expect(
            within(nav).getByRole('link', { name: 'Persone' }),
        ).toBeInTheDocument();
        expect(within(nav).getByText('Marco Bianchi')).toHaveAttribute(
            'aria-current',
            'page',
        );
    });

    it('on the tablet, opened without history, Back visits the page above', async () => {
        const visit = vi.spyOn(router, 'visit').mockImplementation(() => {});
        renderPage(FmPageTrail, { mode: 'tablet', trail, section: false });

        await fireEvent.click(screen.getByRole('link', { name: 'Back' }));

        expect(visit).toHaveBeenCalledWith('/people');
    });

    it.each(['phone', 'tablet'] as const)(
        'on a section page on the %s draws nothing',
        (mode) => {
            const { container } = renderPage(FmPageTrail, {
                mode,
                trail,
                section: true,
            });

            expect(container).toBeEmptyDOMElement();
        },
    );

    it('with fewer than 2 pieces draws nothing', () => {
        const { container } = renderPage(FmPageTrail, {
            mode: 'tablet',
            trail: [{ label: 'Alone' }],
            section: false,
        });

        expect(container).toBeEmptyDOMElement();
    });
});

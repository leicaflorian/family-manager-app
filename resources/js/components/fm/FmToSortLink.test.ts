import { render, screen } from '@testing-library/vue';
import { beforeEach, describe, expect, it, vi } from 'vite-plus/test';

const state = vi.hoisted(() => ({ mode: 'tablet' }));

vi.mock('@/composables/useLayoutMode', async (original) => {
    const { ref } = await import('vue');

    return {
        ...(await original<typeof import('@/composables/useLayoutMode')>()),
        useLayoutMode: () => ref(state.mode),
    };
});

beforeEach(() => {
    state.mode = 'tablet';
});

import FmToSortLink from './FmToSortLink.vue';

describe('FmToSortLink', () => {
    it('leads to the files to sort and says how many there are', () => {
        render(FmToSortLink, { props: { count: 3 } });

        expect(
            screen.getByRole('link', { name: '3 files to sort' }),
        ).toHaveAttribute('href', '/to-sort');
    });

    it('says one file in the singular', () => {
        render(FmToSortLink, { props: { count: 1 } });

        expect(
            screen.getByRole('link', { name: '1 file to sort' }),
        ).toBeInTheDocument();
    });

    it('is not there when nothing waits', () => {
        render(FmToSortLink, { props: { count: 0 } });

        expect(screen.queryByRole('link')).not.toBeInTheDocument();
    });

    it('on the phone is a round button named in full, with the number as a badge', () => {
        state.mode = 'phone';
        render(FmToSortLink, { props: { count: 3 } });

        const link = screen.getByRole('link', { name: '3 files to sort' });

        expect(link).toHaveAttribute('href', '/to-sort');
        expect(link).toHaveTextContent(/^3$/);
        expect(screen.queryByText('3 files to sort')).not.toBeInTheDocument();
    });

    it('on the phone the badge reads 99+ above 99', () => {
        state.mode = 'phone';
        render(FmToSortLink, { props: { count: 120 } });

        expect(
            screen.getByRole('link', { name: '120 files to sort' }),
        ).toHaveTextContent(/^99\+$/);
    });

    it('on the computer keeps the text button', () => {
        state.mode = 'computer';
        render(FmToSortLink, { props: { count: 3 } });

        expect(screen.getByRole('link')).toHaveTextContent('3 files to sort');
    });
});

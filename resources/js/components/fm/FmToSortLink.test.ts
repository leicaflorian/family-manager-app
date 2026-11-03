import { render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
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
});

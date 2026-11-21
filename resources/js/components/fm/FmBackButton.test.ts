import { fireEvent, render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import FmBackButton from './FmBackButton.vue';

describe('FmBackButton', () => {
    it('is a link named Back when there is a page above', () => {
        render(FmBackButton, { props: { href: '/areas/1' } });

        expect(screen.getByRole('link', { name: 'Back' })).toHaveAttribute(
            'href',
            '/areas/1',
        );
    });

    it('is a button named Back without a page above, and tells the page when pressed', async () => {
        const view = render(FmBackButton);

        await fireEvent.click(screen.getByRole('button', { name: 'Back' }));

        expect(view.emitted().click).toHaveLength(1);
    });

    it('is 44 px by default and 36 px when small', () => {
        const view = render(FmBackButton);
        expect(screen.getByRole('button', { name: 'Back' })).toHaveClass(
            'size-11',
        );
        view.unmount();

        render(FmBackButton, { props: { small: true } });
        const small = screen.getByRole('button', { name: 'Back' });
        expect(small).toHaveClass('size-9');
        expect(small).not.toHaveClass('size-11');
    });
});

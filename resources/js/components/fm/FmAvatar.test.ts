import { render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import FmAvatar from './FmAvatar.vue';

describe('FmAvatar', () => {
    it('shows the initials without a photo', () => {
        render(FmAvatar, { props: { initials: 'MB' } });

        expect(screen.getByText('MB')).toBeInTheDocument();
        expect(screen.queryByRole('img')).toBeNull();
    });

    it('shows the photo when there is one', () => {
        render(FmAvatar, {
            props: { initials: 'MB', photo: '/files/3', name: 'Marco Bianchi' },
        });

        expect(
            screen.getByRole('img', { name: 'Marco Bianchi' }),
        ).toHaveAttribute('src', '/files/3');
    });
});

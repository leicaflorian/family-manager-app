import { render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import FmAvatar from './FmAvatar.vue';

describe('FmAvatar', () => {
    it('senza foto mostra le iniziali', () => {
        render(FmAvatar, { props: { iniziali: 'MB' } });

        expect(screen.getByText('MB')).toBeInTheDocument();
        expect(screen.queryByRole('img')).toBeNull();
    });

    it('con la foto mostra la foto', () => {
        render(FmAvatar, {
            props: { iniziali: 'MB', foto: '/file/3', nome: 'Marco Bianchi' },
        });

        expect(
            screen.getByRole('img', { name: 'Marco Bianchi' }),
        ).toHaveAttribute('src', '/file/3');
    });
});

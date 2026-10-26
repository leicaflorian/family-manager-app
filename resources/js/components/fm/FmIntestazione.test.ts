import { render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import FmIntestazione from './FmIntestazione.vue';

describe('FmIntestazione', () => {
    it('scrive il titolo e la frase sotto', () => {
        render(FmIntestazione, {
            props: {
                titolo: 'Ciao, Rosa',
                sottotitolo: 'Marco ti ha invitato.',
            },
        });

        expect(
            screen.getByRole('heading', { level: 1, name: 'Ciao, Rosa' }),
        ).toBeInTheDocument();
        expect(screen.getByText('Marco ti ha invitato.')).toBeInTheDocument();
    });
});

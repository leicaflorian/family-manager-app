import { render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import FmTitolo from './FmTitolo.vue';

describe('FmTitolo', () => {
    it('è il titolo della pagina con la frase sotto e le azioni a destra', () => {
        render(FmTitolo, {
            props: { titolo: 'Documenti', sottotitolo: '18 documenti' },
            slots: { azioni: '<button>Carica</button>' },
        });

        expect(
            screen.getByRole('heading', { level: 1, name: 'Documenti' }),
        ).toBeInTheDocument();
        expect(screen.getByText('18 documenti')).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Carica' }),
        ).toBeInTheDocument();
    });
});

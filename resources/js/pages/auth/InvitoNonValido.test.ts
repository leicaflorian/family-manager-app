import { screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import { renderPagina } from '@/test/pagina';
import InvitoNonValido from './InvitoNonValido.vue';

describe('Invito non valido', () => {
    it("dice che l'invito non vale più e porta ad Accedi", () => {
        renderPagina(InvitoNonValido);

        expect(
            screen.getByRole('heading', { name: 'Questo invito non vale più' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: 'Vai ad Accedi' }),
        ).toHaveAttribute('href', '/login');
    });
});

import { screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import { account } from '@/test/dati';
import { renderPagina } from '@/test/pagina';
import Impostazioni from './Impostazioni.vue';

describe('Impostazioni', () => {
    it('porta al mio account e alle categorie', () => {
        renderPagina(Impostazioni, { account, categoriePrincipali: 10 });

        expect(
            screen.getByRole('heading', { name: 'Impostazioni' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: /Il mio account/ }),
        ).toHaveAttribute('href', '/impostazioni/account');
        expect(
            screen.getByText('Marco Bianchi · Amministratore'),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: /Categorie dei documenti/ }),
        ).toHaveAttribute('href', '/impostazioni/categorie');
        expect(screen.getByText('10 categorie principali')).toBeInTheDocument();
    });
});

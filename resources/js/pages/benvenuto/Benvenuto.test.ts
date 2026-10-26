import { fireEvent, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import { renderPagina } from '@/test/pagina';
import Benvenuto from './Benvenuto.vue';

const props = {
    persone: [
        { id: 1, first_name: 'Marco', last_name: 'Bianchi', initials: 'MB' },
    ],
    ruoli: [
        { value: 'parent', label: 'Genitore', help: null },
        { value: 'child', label: 'Figlio/a', help: null },
    ],
};

describe('Benvenuto', () => {
    it("al primo passo mostra chi c'è già e aggiunge una persona", async () => {
        renderPagina(Benvenuto, props);

        expect(screen.getByText('Passo 1 di 2')).toBeInTheDocument();
        expect(
            screen.getByRole('heading', { name: 'Chi siete' }),
        ).toBeInTheDocument();
        expect(screen.getByText('Marco Bianchi')).toBeInTheDocument();

        await fireEvent.click(
            screen.getByRole('button', { name: 'Aggiungi una persona' }),
        );

        expect(screen.getByLabelText('Nome')).toBeInTheDocument();
        expect(screen.getByLabelText('Cognome')).toHaveValue('Bianchi');
        expect(screen.getByLabelText('Nella famiglia è')).toBeInTheDocument();

        await fireEvent.click(screen.getByRole('button', { name: 'Togli' }));

        expect(screen.queryByLabelText('Nome')).not.toBeInTheDocument();
    });

    it('al secondo passo chiede la casa, che si può lasciare per dopo', async () => {
        renderPagina(Benvenuto, props);

        await fireEvent.click(screen.getByRole('button', { name: 'Avanti' }));

        expect(screen.getByText('Passo 2 di 2')).toBeInTheDocument();
        expect(
            screen.getByRole('heading', { name: 'La casa' }),
        ).toBeInTheDocument();
        expect(screen.getByLabelText('Come la chiamate')).toHaveValue('Casa');
        expect(screen.getByLabelText('Indirizzo')).toBeInTheDocument();
        expect(screen.getByLabelText('Città')).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Fatto, entriamo' }),
        ).toHaveAttribute('type', 'submit');
        expect(
            screen.getByRole('button', { name: 'La aggiungo dopo' }),
        ).toBeInTheDocument();

        await fireEvent.click(screen.getByRole('button', { name: 'Indietro' }));

        expect(
            screen.getByRole('heading', { name: 'Chi siete' }),
        ).toBeInTheDocument();
    });
});

import { fireEvent, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import { account } from '@/test/dati';
import { renderPagina } from '@/test/pagina';
import Account from './Account.vue';

describe('Il mio account', () => {
    it('mostra nome, cognome ed email da cambiare, e la foto', () => {
        renderPagina(Account, { account });

        expect(screen.getByLabelText('Nome')).toHaveValue('Marco');
        expect(screen.getByLabelText('Cognome')).toHaveValue('Bianchi');
        expect(screen.getByLabelText('Email')).toHaveValue('marco@example.com');
        expect(screen.getByText('MB')).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Cambia la foto' }),
        ).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Salva' })).toHaveAttribute(
            'type',
            'submit',
        );
        expect(
            screen.getByRole('button', { name: 'Esci' }),
        ).toBeInTheDocument();
    });

    it('la password si cambia nel pannello, con quella di adesso', async () => {
        renderPagina(Account, { account });

        await fireEvent.click(screen.getByRole('button', { name: /Password/ }));

        expect(
            await screen.findByLabelText('Password di adesso'),
        ).toBeInTheDocument();
        expect(screen.getByLabelText('Password nuova')).toHaveAttribute(
            'type',
            'password',
        );
    });

    it("l'ultimo admin legge perché non può eliminarsi", () => {
        renderPagina(Account, { account });

        expect(
            screen.queryByRole('button', { name: 'Elimina il mio account' }),
        ).toBeNull();
        expect(
            screen.getByText(/Sei l'unico amministratore/),
        ).toBeInTheDocument();
    });

    it('gli altri eliminano il loro account con la password', async () => {
        renderPagina(Account, {
            account: {
                ...account,
                ruolo: 'Familiare',
                admin: false,
                ultimoAdmin: false,
            },
        });

        expect(
            screen.getByText(/Vedi tutto e carichi i documenti/),
        ).toBeInTheDocument();

        await fireEvent.click(
            screen.getByRole('button', { name: 'Elimina il mio account' }),
        );

        expect(
            await screen.findByLabelText('La tua password'),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Elimina per sempre' }),
        ).toBeInTheDocument();
    });
});

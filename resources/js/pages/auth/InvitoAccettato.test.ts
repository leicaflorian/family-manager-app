import { screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import { renderPagina } from '@/test/pagina';
import InvitoAccettato from './InvitoAccettato.vue';

const invito: App.Data.InvitoData = {
    nome: 'Rosa',
    famiglia: 'Bianchi',
    chiInvita: 'Marco',
    email: 'rosa.bianchi@example.com',
    scade: '02/11/2026',
    persone: [
        { id: 1, first_name: 'Marco', last_name: 'Bianchi', initials: 'MB' },
        { id: 2, first_name: 'Laura', last_name: 'Bianchi', initials: 'LB' },
    ],
};

describe('Invito accettato', () => {
    it('saluta per nome, mostra la famiglia e chiede solo la password', () => {
        renderPagina(InvitoAccettato, { token: 'abc', invito });

        expect(
            screen.getByRole('heading', { name: 'Ciao, Rosa' }),
        ).toBeInTheDocument();
        expect(
            screen.getByText(/Marco ti ha invitato nella famiglia Bianchi/),
        ).toBeInTheDocument();
        expect(screen.getByText('MB')).toBeInTheDocument();
        expect(screen.getByLabelText('La tua email')).toHaveValue(
            'rosa.bianchi@example.com',
        );
        expect(screen.getByLabelText('La tua email')).toBeDisabled();
        expect(screen.getByLabelText('Scegli una password')).toHaveAttribute(
            'type',
            'password',
        );
        expect(
            screen.getByRole('button', { name: 'Entra nella famiglia' }),
        ).toHaveAttribute('type', 'submit');
        expect(
            screen.getByText("L'invito vale fino al 02/11/2026"),
        ).toBeInTheDocument();
    });
});

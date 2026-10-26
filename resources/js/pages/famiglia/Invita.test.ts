import { fireEvent, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import { renderPagina } from '@/test/pagina';
import Invita from './Invita.vue';

const props = {
    persone: [
        { id: 7, first_name: 'Laura', last_name: 'Bianchi', initials: 'LB' },
    ],
    livelli: [
        {
            value: 'admin',
            label: 'Tutto, come te',
            help: "Anche le impostazioni, gli inviti e l'abbonamento",
        },
        {
            value: 'member',
            label: 'Vede tutto e carica i documenti',
            help: 'Paga, registra e carica, ma non cambia le impostazioni',
        },
    ],
    ruoli: [
        { value: 'parent', label: 'Genitore', help: null },
        { value: 'other', label: 'Altro', help: null },
    ],
};

describe('Invita un familiare', () => {
    it("chiede l'email, chi è e cosa può fare, come la tavola", () => {
        renderPagina(Invita, props);

        expect(screen.getByLabelText('La sua email')).toHaveAttribute(
            'type',
            'email',
        );
        expect(
            screen.getByRole('option', { name: 'Laura Bianchi' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('option', { name: 'Una persona nuova' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('radio', { name: /Tutto, come te/ }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('radio', {
                name: /Vede tutto e carica i documenti/,
            }),
        ).toBeChecked();
        expect(
            screen.getByText(
                'Paga, registra e carica, ma non cambia le impostazioni',
            ),
        ).toBeInTheDocument();
        expect(screen.getByText(/vale 7 giorni/)).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: "Manda l'invito" }),
        ).toHaveAttribute('type', 'submit');
    });

    it('per una persona nuova chiede nome, cognome e chi è nella famiglia', async () => {
        renderPagina(Invita, props);
        expect(screen.queryByLabelText('Nome')).not.toBeInTheDocument();

        const chi = screen.getByLabelText('Chi è');
        (chi as HTMLSelectElement).value = 'nuova';
        await fireEvent.input(chi);

        expect(await screen.findByLabelText('Nome')).toBeInTheDocument();
        expect(screen.getByLabelText('Cognome')).toBeInTheDocument();
        expect(screen.getByLabelText('Nella famiglia è')).toBeInTheDocument();
    });

    it('senza persone da invitare parte da una persona nuova', () => {
        renderPagina(Invita, { ...props, persone: [] });

        expect(screen.getByLabelText('Nome')).toBeInTheDocument();
    });
});

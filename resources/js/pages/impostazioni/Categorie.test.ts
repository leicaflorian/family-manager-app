import { fireEvent, screen, within } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import { renderPagina } from '@/test/pagina';
import Categorie from './Categorie.vue';

const categoria = (
    id: number,
    name: string,
    documenti: number,
    figlie: App.Data.CategoriaData[] = [],
    parent_id: number | null = null,
): App.Data.CategoriaData => ({
    id,
    name,
    parent_id,
    icon: parent_id ? null : 'house',
    color: 'casa',
    documenti,
    figlie,
});

const categorie = [
    categoria(1, 'Casa e utenze', 4, [
        categoria(2, 'Luce', 2, [], 1),
        categoria(3, 'TARI', 0, [], 1),
    ]),
    categoria(4, 'Lavoro', 5),
];

describe('Categorie dei documenti', () => {
    it("mostra l'albero con il numero di documenti, e apre le sottocategorie", async () => {
        renderPagina(Categorie, { categorie, puoModificare: true });

        expect(screen.getByText('Casa e utenze')).toBeInTheDocument();
        expect(screen.queryByText('Luce')).toBeNull();

        await fireEvent.click(
            screen.getByRole('button', { name: 'Apri Casa e utenze' }),
        );

        expect(screen.getByText('Luce')).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Chiudi Casa e utenze' }),
        ).toHaveAttribute('aria-expanded', 'true');
    });

    it('una categoria si modifica nel pannello, e non va sotto sé stessa', async () => {
        renderPagina(Categorie, { categorie, puoModificare: true });

        await fireEvent.click(
            screen.getByRole('button', { name: /^Casa e utenze/ }),
        );

        const pannello = await screen.findByRole('dialog');
        expect(within(pannello).getByLabelText('Nome')).toHaveValue(
            'Casa e utenze',
        );
        const dentro = within(pannello).getByLabelText('Dentro');
        expect(
            within(dentro).getByRole('option', {
                name: 'Nessuna: è una principale',
            }),
        ).toBeInTheDocument();
        expect(
            within(dentro).getByRole('option', { name: 'Lavoro' }),
        ).toBeInTheDocument();
        expect(
            within(dentro).queryByRole('option', { name: 'Casa e utenze' }),
        ).toBeNull();
        expect(
            within(dentro).queryByRole('option', { name: /Luce/ }),
        ).toBeNull();
        expect(
            within(pannello).getByText(/4 documenti in questa categoria/),
        ).toBeInTheDocument();
        expect(
            within(pannello).queryByRole('button', { name: 'Elimina' }),
        ).toBeNull();
    });

    it('una categoria vuota si può eliminare', async () => {
        renderPagina(Categorie, { categorie, puoModificare: true });
        await fireEvent.click(
            screen.getByRole('button', { name: 'Apri Casa e utenze' }),
        );

        await fireEvent.click(screen.getByRole('button', { name: /^TARI/ }));

        const pannello = await screen.findByRole('dialog');
        expect(
            within(pannello).getByText(
                /Nessun documento è in questa categoria/,
            ),
        ).toBeInTheDocument();
        expect(
            within(pannello).getByRole('button', { name: 'Elimina' }),
        ).toBeInTheDocument();
    });

    it('un familiare vede le categorie ma non le cambia', () => {
        renderPagina(Categorie, { categorie, puoModificare: false });

        expect(
            screen.queryByRole('button', { name: 'Nuova categoria' }),
        ).toBeNull();
        expect(screen.queryByRole('button', { name: /^Lavoro/ })).toBeNull();
        expect(screen.getByText('Lavoro')).toBeInTheDocument();
    });
});

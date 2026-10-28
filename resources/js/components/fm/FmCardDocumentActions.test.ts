import { router } from '@inertiajs/vue3';
import { screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { healthCard } from '@/test/fixtures';
import { renderPage } from '@/test/page';
import FmCardDocumentActions from './FmCardDocumentActions.vue';

const card = { type: 'insurance_policy', id: 1 };

afterEach(() => {
    vi.restoreAllMocks();
});

/**
 * Opens the ⋯ menu of the document.
 */
async function openMenu(): Promise<void> {
    await userEvent.click(
        screen.getByRole('button', {
            name: `More actions on ${healthCard.title}`,
        }),
    );
}

describe('FmCardDocumentActions', () => {
    it('opens the file in a new tab, always in sight', () => {
        renderPage(FmCardDocumentActions, {
            document: healthCard,
            card,
            canDelete: false,
        });

        const open = screen.getByRole('link', {
            name: `Open ${healthCard.title} in a new tab`,
        });
        expect(open).toHaveAttribute('href', healthCard.file_url);
        expect(open).toHaveAttribute('target', '_blank');
    });

    it('has Edit in the menu, that opens the document as its row does', async () => {
        renderPage(FmCardDocumentActions, {
            document: healthCard,
            card,
            canDelete: false,
        });

        await openMenu();

        expect(
            await screen.findByRole('menuitem', { name: 'Edit' }),
        ).toHaveAttribute('href', `/documents/${healthCard.id}`);
    });

    it('unlinks only after asking', async () => {
        const destroy = vi.spyOn(router, 'delete').mockImplementation(() => {});
        renderPage(FmCardDocumentActions, {
            document: healthCard,
            card,
            canDelete: false,
        });

        await openMenu();
        await userEvent.click(
            await screen.findByRole('menuitem', { name: 'Unlink' }),
        );
        expect(destroy).not.toHaveBeenCalled();
        expect(
            await screen.findByText(`Unlink ${healthCard.title}?`),
        ).toBeInTheDocument();

        await userEvent.click(screen.getByRole('button', { name: 'Unlink' }));

        expect(destroy).toHaveBeenCalledWith(
            `/cards/insurance_policy/1/documents/${healthCard.id}`,
            expect.anything(),
        );
    });

    it('deletes the document after asking, and stays on the card', async () => {
        const destroy = vi.spyOn(router, 'delete').mockImplementation(() => {});
        renderPage(FmCardDocumentActions, {
            document: healthCard,
            card,
            canDelete: true,
        });

        await openMenu();
        await userEvent.click(
            await screen.findByRole('menuitem', { name: 'Delete' }),
        );
        expect(destroy).not.toHaveBeenCalled();

        await userEvent.click(
            await screen.findByRole('button', { name: 'Delete the document' }),
        );

        expect(destroy).toHaveBeenCalledWith(
            `/documents/${healthCard.id}?stay=1`,
            expect.anything(),
        );
    });

    it('does not offer Delete to whoever may not delete', async () => {
        renderPage(FmCardDocumentActions, {
            document: healthCard,
            card,
            canDelete: false,
        });

        await openMenu();

        expect(
            await screen.findByRole('menuitem', { name: 'Unlink' }),
        ).toBeInTheDocument();
        expect(
            screen.queryByRole('menuitem', { name: 'Delete' }),
        ).not.toBeInTheDocument();
    });
});

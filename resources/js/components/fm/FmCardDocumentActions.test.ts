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

    it('has buttons without a background, as they sit on the row', () => {
        renderPage(FmCardDocumentActions, {
            document: healthCard,
            card,
            canDelete: false,
        });

        for (const button of [
            screen.getByRole('link', {
                name: `Open ${healthCard.title} in a new tab`,
            }),
            screen.getByRole('button', {
                name: `More actions on ${healthCard.title}`,
            }),
        ]) {
            expect(button).toHaveClass('bg-transparent');
            expect(button).not.toHaveClass('bg-canvas');
        }
    });

    it('has Edit in the menu, that opens the document as its row does', async () => {
        renderPage(FmCardDocumentActions, {
            document: healthCard,
            card,
            canDelete: false,
        });

        await openMenu();

        const edit = await screen.findByRole('menuitem', { name: 'Edit' });
        expect(edit).toHaveAttribute('href', `/documents/${healthCard.id}`);
        expect(edit).not.toHaveAttribute('target');
        expect(
            screen.queryByRole('menuitem', { name: 'Open the file' }),
        ).not.toBeInTheDocument();
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

        // The state stays: from the form of the card, what is written is not lost
        expect(destroy).toHaveBeenCalledWith(
            `/cards/insurance_policy/1/documents/${healthCard.id}`,
            expect.objectContaining({ preserveState: true }),
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
            expect.objectContaining({ preserveState: true }),
        );
    });

    describe('in the form of the card', () => {
        it('keeps only the menu, with Open the file first', async () => {
            renderPage(FmCardDocumentActions, {
                document: healthCard,
                card,
                canDelete: false,
                inForm: true,
            });

            expect(
                screen.queryByRole('link', {
                    name: `Open ${healthCard.title} in a new tab`,
                }),
            ).not.toBeInTheDocument();

            await openMenu();

            const items = await screen.findAllByRole('menuitem');
            expect(items[0]).toHaveTextContent('Open the file');
            expect(items[0]).toHaveAttribute('href', healthCard.file_url);
            expect(items[0]).toHaveAttribute('target', '_blank');
        });

        it('unlinks without asking and sends nothing, as the form does it when saved', async () => {
            const destroy = vi
                .spyOn(router, 'delete')
                .mockImplementation(() => {});
            const onUnlink = vi.fn();
            renderPage(FmCardDocumentActions, {
                document: healthCard,
                card,
                canDelete: false,
                inForm: true,
                onUnlink,
            });

            await openMenu();
            await userEvent.click(
                await screen.findByRole('menuitem', { name: 'Unlink' }),
            );

            expect(onUnlink).toHaveBeenCalled();
            expect(destroy).not.toHaveBeenCalled();
            expect(
                screen.queryByText(`Unlink ${healthCard.title}?`),
            ).not.toBeInTheDocument();
        });

        it('opens Edit in a new tab, so the form stays', async () => {
            renderPage(FmCardDocumentActions, {
                document: healthCard,
                card,
                canDelete: false,
                inForm: true,
            });

            await openMenu();

            const edit = await screen.findByRole('menuitem', { name: 'Edit' });
            expect(edit).toHaveAttribute('href', `/documents/${healthCard.id}`);
            expect(edit).toHaveAttribute('target', '_blank');
        });
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

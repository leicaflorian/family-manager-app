import { router } from '@inertiajs/vue3';
import { Download, ExternalLink, Pencil, Share2, Trash2 } from '@lucide/vue';
import { fireEvent, screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vite-plus/test';
import type { Action } from '@/lib/actions';
import { layOut, watchLayout } from '@/test/layout';
import { renderPage } from '@/test/page';
import FmActions from './FmActions.vue';

/** The actions of a document: three buttons and two only in the menu. */
function documentActions(share = vi.fn()): Action[] {
    return [
        {
            label: 'Download',
            icon: Download,
            variant: 'outline',
            href: '/files/12/download',
            download: true,
        },
        {
            label: 'Edit',
            icon: Pencil,
            variant: 'soft',
            href: '/documents/5/edit',
        },
        {
            label: 'Delete',
            icon: Trash2,
            variant: 'danger',
            confirm: {
                url: '/documents/5',
                question: 'Delete this document?',
                text: 'The file goes too.',
                confirmLabel: 'Delete the document',
            },
        },
        {
            label: 'Open in a new tab',
            icon: ExternalLink,
            href: '/files/12',
            external: true,
            menuOnly: true,
        },
        { label: 'Share', icon: Share2, onSelect: share, menuOnly: true },
    ];
}

/** The texts of the buttons in view, without the ⋯. */
function buttons(): string[] {
    return [
        ...screen.queryAllByRole('link'),
        ...screen.queryAllByRole('button'),
    ]
        .map((element) => element.textContent?.trim() ?? '')
        .filter((text) => text !== '');
}

/** The texts of the entries of the ⋯ menu, after opening it. */
async function menuEntries(): Promise<string[]> {
    await userEvent.click(screen.getByRole('button', { name: 'More actions' }));
    await screen.findAllByRole('menuitem');

    return screen
        .getAllByRole('menuitem')
        .map((entry) => entry.textContent?.trim() ?? '');
}

/**
 * Renders the actions, with the room told by `layOut`.
 *
 * @param actions - the actions of the page
 */
function renderActions(actions: Action[]): void {
    watchLayout();
    renderPage(FmActions, { actions });
}

describe('FmActions', () => {
    it('shows the first three actions as buttons and the others in the menu', async () => {
        renderActions(documentActions());

        expect(buttons()).toEqual(['Download', 'Edit', 'Delete']);
        expect(await menuEntries()).toEqual(['Open in a new tab', 'Share']);
    });

    it('shows no more than three buttons, the fourth goes to the top of the menu', async () => {
        const [download, edit, remove, newTab] = documentActions();
        renderActions([
            download!,
            edit!,
            remove!,
            { ...newTab!, menuOnly: false },
        ]);

        expect(buttons()).toEqual(['Download', 'Edit', 'Delete']);
        expect(await menuEntries()).toEqual(['Open in a new tab']);
    });

    it('without anything for the menu has no ⋯', () => {
        renderActions(documentActions().slice(0, 2));

        expect(buttons()).toEqual(['Download', 'Edit']);
        expect(
            screen.queryByRole('button', { name: 'More actions' }),
        ).not.toBeInTheDocument();
    });

    it('shows fewer buttons as the room shrinks, the others go in the menu', async () => {
        renderActions(documentActions());

        // Two buttons of 100 px, the ⋯ of 100 px and two gaps of 10 px
        await layOut(320);
        expect(buttons()).toEqual(['Download', 'Edit']);

        await layOut(210);
        expect(buttons()).toEqual(['Download']);
        expect(await menuEntries()).toEqual([
            'Edit',
            'Delete',
            'Open in a new tab',
            'Share',
        ]);
    });

    it('with no room left puts every action in the menu', async () => {
        renderActions(documentActions());

        await layOut(100);

        expect(buttons()).toEqual([]);
        expect(await menuEntries()).toEqual([
            'Download',
            'Edit',
            'Delete',
            'Open in a new tab',
            'Share',
        ]);
    });

    it('in the menu keeps what each action does: a download, a page, a new tab', async () => {
        renderActions(documentActions());
        await layOut(100);

        await menuEntries();

        expect(
            screen.getByRole('menuitem', { name: 'Download' }),
        ).toHaveAttribute('download');
        expect(screen.getByRole('menuitem', { name: 'Edit' })).toHaveAttribute(
            'href',
            '/documents/5/edit',
        );
        expect(
            screen.getByRole('menuitem', { name: 'Open in a new tab' }),
        ).toHaveAttribute('target', '_blank');
    });

    it('asks before deleting from the button', async () => {
        const destroy = vi.spyOn(router, 'delete').mockImplementation(() => {});
        renderActions(documentActions());

        await fireEvent.click(screen.getByRole('button', { name: 'Delete' }));
        expect(destroy).not.toHaveBeenCalled();
        await fireEvent.click(
            await screen.findByRole('button', { name: 'Delete the document' }),
        );

        expect(destroy).toHaveBeenCalledWith('/documents/5', expect.anything());
    });

    it('asks before deleting from the menu', async () => {
        renderActions(documentActions());
        await layOut(100);

        await menuEntries();
        await userEvent.click(screen.getByRole('menuitem', { name: 'Delete' }));

        expect(
            await screen.findByRole('heading', {
                name: 'Delete this document?',
            }),
        ).toBeInTheDocument();
    });

    it('does what the action says from the menu', async () => {
        const share = vi.fn();
        renderActions(documentActions(share));

        await menuEntries();
        await userEvent.click(screen.getByRole('menuitem', { name: 'Share' }));

        expect(share).toHaveBeenCalled();
    });

    it('makes Delete red in the menu', async () => {
        renderActions(documentActions());
        await layOut(100);

        await menuEntries();

        expect(screen.getByRole('menuitem', { name: 'Delete' })).toHaveClass(
            'text-overdue',
        );
    });
});

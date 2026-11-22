import { screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { router } from '@inertiajs/vue3';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { documentDetail } from '@/test/fixtures';
import { layOut, watchLayout } from '@/test/layout';
import { renderPage } from '@/test/page';
import FmDocumentActions from './FmDocumentActions.vue';

describe('FmDocumentActions', () => {
    it('on its own page has Download and Edit with their text', () => {
        renderPage(FmDocumentActions, { document: documentDetail });

        expect(
            screen.getByRole('link', { name: 'Download' }),
        ).toHaveTextContent('Download');
        expect(screen.getByRole('link', { name: 'Edit' })).toHaveTextContent(
            'Edit',
        );
    });

    it('on its own page Edit is the light blue button, as the page of a person', () => {
        renderPage(FmDocumentActions, { document: documentDetail });

        expect(screen.getByRole('link', { name: 'Edit' })).toHaveClass(
            'bg-action-soft',
        );
    });

    it('in the panel beside the list Delete stays in the menu', async () => {
        renderPage(FmDocumentActions, {
            document: documentDetail,
            compact: true,
        });

        expect(
            screen.queryByRole('button', { name: 'Delete' }),
        ).not.toBeInTheDocument();
        await userEvent.click(
            screen.getByRole('button', { name: 'More actions' }),
        );
        expect(
            await screen.findByRole('menuitem', { name: 'Delete' }),
        ).toBeInTheDocument();
    });

    it('in the panel beside the list has Download as an icon and Edit in the menu', async () => {
        renderPage(FmDocumentActions, {
            document: documentDetail,
            compact: true,
        });

        expect(
            screen.getByRole('link', { name: 'Download' }),
        ).not.toHaveTextContent('Download');
        expect(
            screen.queryByRole('link', { name: 'Edit' }),
        ).not.toBeInTheDocument();
        await userEvent.click(
            screen.getByRole('button', { name: 'More actions' }),
        );
        expect(
            await screen.findByRole('menuitem', { name: 'Edit' }),
        ).toHaveAttribute('href', '/documents/5/edit');
    });

    it('in the panel beside the list the icons are the small squares of 40 px', () => {
        renderPage(FmDocumentActions, {
            document: documentDetail,
            compact: true,
        });

        expect(screen.getByRole('link', { name: 'Download' })).toHaveClass(
            'size-10',
        );
        expect(
            screen.getByRole('button', { name: 'More actions' }),
        ).toHaveClass('size-10');
    });

    it('on its own page the ⋯ button is as tall as the others', () => {
        renderPage(FmDocumentActions, { document: documentDetail });

        expect(
            screen.getByRole('button', { name: 'More actions' }),
        ).toHaveClass('size-control-md');
    });

    it('on its own page puts in the menu the buttons that do not fit', async () => {
        watchLayout();
        renderPage(FmDocumentActions, { document: documentDetail });

        await layOut(100);

        expect(
            screen.queryByRole('link', { name: 'Download' }),
        ).not.toBeInTheDocument();
        await userEvent.click(
            screen.getByRole('button', { name: 'More actions' }),
        );
        expect(
            await screen.findByRole('menuitem', { name: 'Download' }),
        ).toHaveAttribute('download');
        expect(
            screen.getByRole('menuitem', { name: 'Open in a new tab' }),
        ).toBeInTheDocument();
    });
    describe('pinning', () => {
        afterEach(() => {
            vi.restoreAllMocks();
            vi.unstubAllGlobals();
        });

        const open = async (): Promise<void> => {
            await userEvent.click(
                screen.getByRole('button', { name: 'More actions' }),
            );
        };

        it('has no pin item without can_pin', async () => {
            renderPage(FmDocumentActions, { document: documentDetail });
            await open();

            expect(
                screen.queryByRole('menuitem', { name: 'Mark as favourite' }),
            ).not.toBeInTheDocument();
            expect(
                screen.queryByRole('menuitem', {
                    name: 'Remove from favourites',
                }),
            ).not.toBeInTheDocument();
        });

        it('the page menu reads Open, Share, Pin and shows only Unpin when pinned', async () => {
            vi.stubGlobal('navigator', {
                share: () => {},
                canShare: () => true,
            });
            renderPage(FmDocumentActions, {
                document: { ...documentDetail, can_pin: true },
            });
            await open();
            await screen.findByRole('menuitem', { name: 'Mark as favourite' });

            expect(
                screen
                    .getAllByRole('menuitem')
                    .map((item) => item.textContent?.trim()),
            ).toEqual(['Open in a new tab', 'Share', 'Mark as favourite']);
        });

        it('shows only Unpin when it is pinned', async () => {
            renderPage(FmDocumentActions, {
                document: { ...documentDetail, can_pin: true, pinned: true },
            });
            await open();

            expect(
                await screen.findByRole('menuitem', {
                    name: 'Remove from favourites',
                }),
            ).toBeInTheDocument();
            expect(
                screen.queryByRole('menuitem', { name: 'Mark as favourite' }),
            ).not.toBeInTheDocument();
        });

        it.each([false, true])(
            'on its own page pins with a PUT (compact: %s)',
            async (compact) => {
                const put = vi
                    .spyOn(router, 'put')
                    .mockImplementation(() => {});
                renderPage(FmDocumentActions, {
                    document: { ...documentDetail, can_pin: true },
                    compact,
                });
                await open();
                await userEvent.click(
                    await screen.findByRole('menuitem', {
                        name: 'Mark as favourite',
                    }),
                );

                expect(put).toHaveBeenCalledWith(
                    '/documents/5/pin',
                    {},
                    { preserveScroll: true },
                );
            },
        );

        it.each([false, true])(
            'unpins with a DELETE when it is pinned (compact: %s)',
            async (compact) => {
                const del = vi
                    .spyOn(router, 'delete')
                    .mockImplementation(() => {});
                renderPage(FmDocumentActions, {
                    document: {
                        ...documentDetail,
                        can_pin: true,
                        pinned: true,
                    },
                    compact,
                });
                await open();
                await userEvent.click(
                    await screen.findByRole('menuitem', {
                        name: 'Remove from favourites',
                    }),
                );

                expect(del).toHaveBeenCalledWith('/documents/5/pin', {
                    preserveScroll: true,
                });
            },
        );
    });
});

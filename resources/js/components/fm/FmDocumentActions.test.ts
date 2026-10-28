import { screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vite-plus/test';
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
        ).toHaveClass('size-11.5');
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
});

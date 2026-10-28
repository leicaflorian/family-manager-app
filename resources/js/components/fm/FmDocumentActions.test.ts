import { screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vite-plus/test';
import { documentDetail } from '@/test/fixtures';
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
});

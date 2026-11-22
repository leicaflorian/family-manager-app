import userEvent from '@testing-library/user-event';
import { fireEvent, render, screen } from '@testing-library/vue';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import FmDrawingViewer from './FmDrawingViewer.vue';

/** A drawing as the server sends it, with what the test changes. */
function drawingViewerFixture(
    changes: Partial<App.Data.DrawingData> = {},
): App.Data.DrawingData {
    return {
        id: 7,
        title: 'The dragon',
        notes: 'Made at school.',
        person_id: 1,
        person_name: 'Giulia',
        age: 10,
        uploaded_on: '2026-11-14',
        pages: 1,
        is_favorite: false,
        image_url: '/drawings/7/image',
        file_url: '/files/7',
        download_url: '/files/7/download',
        document_url: '/documents/7',
        can_favorite: true,
        ...changes,
    };
}

/** Renders the viewer on the phone with the given drawing. */
function viewerOf(
    changes: Partial<App.Data.DrawingData> = {},
    variant: 'screen' | 'panel' = 'screen',
): ReturnType<typeof render> {
    return render(FmDrawingViewer, {
        props: {
            drawing: drawingViewerFixture(changes),
            position: 12,
            total: 48,
            variant,
        },
    });
}

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('FmDrawingViewer', () => {
    it('writes child, age and date', () => {
        viewerOf();

        expect(
            screen.getByText('Giulia, 10 years · uploaded on 14/11/2026'),
        ).toBeInTheDocument();
        expect(screen.getByText('12 of 48')).toBeInTheDocument();
        expect(screen.getByText('Made at school.')).toBeInTheDocument();
    });

    it('without birth date only the name', () => {
        viewerOf({ age: null });

        expect(
            screen.getByText('Giulia · uploaded on 14/11/2026'),
        ).toBeInTheDocument();
    });

    it('without person only the date', () => {
        viewerOf({ person_id: null, person_name: null, age: null });

        expect(screen.getByText('Uploaded on 14/11/2026')).toBeInTheDocument();
    });

    it('links the PDF when there are more pages', async () => {
        const view = viewerOf({ pages: 1 });
        expect(
            screen.queryByRole('link', { name: /open the PDF/ }),
        ).not.toBeInTheDocument();

        await view.rerender({
            drawing: drawingViewerFixture({ pages: 2 }),
            position: 12,
            total: 48,
            variant: 'screen',
        });

        const link = screen.getByRole('link', {
            name: '2 pages · open the PDF to see them',
        });
        expect(link).toHaveAttribute('href', '/files/7');
        expect(link).toHaveAttribute('target', '_blank');
    });

    it('emits previous and next with the arrow keys', async () => {
        const view = viewerOf();

        await fireEvent.keyDown(window, { key: 'ArrowLeft' });
        await fireEvent.keyDown(window, { key: 'ArrowRight' });

        expect(view.emitted().previous).toHaveLength(1);
        expect(view.emitted().next).toHaveLength(1);
    });

    it('hides Share without navigator.share', () => {
        viewerOf();

        expect(
            screen.queryByRole('button', { name: 'Share' }),
        ).not.toBeInTheDocument();
    });

    it('shows Share when the browser can share files', async () => {
        const share = vi.fn().mockResolvedValue(undefined);
        vi.stubGlobal('navigator', { canShare: () => true, share });
        vi.stubGlobal(
            'fetch',
            vi.fn().mockResolvedValue({
                blob: async () => new Blob(['x'], { type: 'image/png' }),
            }),
        );
        viewerOf();

        await userEvent.click(
            await screen.findByRole('button', { name: 'Share' }),
        );

        await vi.waitFor(() => expect(share).toHaveBeenCalledTimes(1));
    });

    it('emits favorite with the new value', async () => {
        const view = viewerOf({ is_favorite: false });

        await userEvent.click(
            screen.getByRole('button', { name: 'Add to favourites' }),
        );

        expect(view.emitted().favorite).toEqual([[true]]);
    });

    it('hides the heart when the person cannot favourite', () => {
        viewerOf({ can_favorite: false });

        expect(
            screen.queryByRole('button', { name: 'Add to favourites' }),
        ).not.toBeInTheDocument();
    });

    it('emits close and links download and the document', async () => {
        const view = viewerOf();

        await userEvent.click(screen.getByRole('button', { name: 'Close' }));

        expect(view.emitted().close).toHaveLength(1);
        expect(screen.getByRole('link', { name: 'Download' })).toHaveAttribute(
            'href',
            '/files/7/download',
        );
        expect(
            screen.getByRole('link', { name: 'Open as a document' }),
        ).toHaveAttribute('href', '/documents/7');
    });

    it('in the panel does not listen to the arrow keys of the whole window', async () => {
        const view = viewerOf({}, 'panel');

        await fireEvent.keyDown(window, { key: 'ArrowRight' });

        expect(view.emitted().next).toBeUndefined();
    });
});

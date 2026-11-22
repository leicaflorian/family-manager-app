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

    it('fetches the file when the drawing opens and shares it named after the title', async () => {
        const share = vi.fn().mockResolvedValue(undefined);
        vi.stubGlobal('navigator', { canShare: () => true, share });
        const fetch = vi.fn().mockResolvedValue({
            blob: async () => new Blob(['x'], { type: 'image/png' }),
        });
        vi.stubGlobal('fetch', fetch);
        viewerOf();

        const button = await screen.findByRole('button', { name: 'Share' });
        // The file is ready before the click, so the share sheet opens within the tap.
        expect(fetch).toHaveBeenCalledWith('/files/7/download');

        await userEvent.click(button);

        await vi.waitFor(() => expect(share).toHaveBeenCalledTimes(1));
        const [{ files, title }] = share.mock.calls[0]! as [
            { files: File[]; title: string },
        ];
        expect(files[0]!.name).toBe('The dragon.png');
        expect(title).toBe('The dragon');
        expect(fetch).toHaveBeenCalledTimes(1);
    });

    it('names a shared PDF with its extension', async () => {
        const share = vi.fn().mockResolvedValue(undefined);
        vi.stubGlobal('navigator', { canShare: () => true, share });
        vi.stubGlobal(
            'fetch',
            vi.fn().mockResolvedValue({
                blob: async () => new Blob(['x'], { type: 'application/pdf' }),
            }),
        );
        viewerOf();

        await userEvent.click(
            await screen.findByRole('button', { name: 'Share' }),
        );

        await vi.waitFor(() => expect(share).toHaveBeenCalledTimes(1));
        expect(
            (share.mock.calls[0]![0] as { files: File[] }).files[0]!.name,
        ).toBe('The dragon.pdf');
    });

    it('a file that cannot be fetched shows no Share', async () => {
        vi.stubGlobal('navigator', { canShare: () => true, share: vi.fn() });
        vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('offline')));
        viewerOf();
        await new Promise((resolve) => setTimeout(resolve, 0));

        expect(
            screen.queryByRole('button', { name: 'Share' }),
        ).not.toBeInTheDocument();
    });

    it('shows the place in the gallery in the on-hero colour on the phone', () => {
        viewerOf();

        expect(screen.getByText('12 of 48')).toHaveClass('text-on-hero');
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

    it('draws a window of at most five dots, hidden from screen readers', () => {
        const { container } = viewerOf();

        const dots = container.querySelectorAll('[aria-hidden="true"] > span');
        expect(dots).toHaveLength(5);
        expect(dots[2]).toHaveClass('w-[18px]');
    });

    it('moves the focus to the close button of the phone', async () => {
        viewerOf();

        await vi.waitFor(() =>
            expect(screen.getByRole('button', { name: 'Close' })).toHaveFocus(),
        );
        expect(screen.getByRole('dialog')).toHaveAttribute(
            'aria-modal',
            'true',
        );
    });

    it('emits close and links download and the document', async () => {
        const view = viewerOf();

        await userEvent.click(screen.getByRole('button', { name: 'Close' }));

        expect(view.emitted().close).toHaveLength(1);
        expect(screen.getByRole('link', { name: 'Download' })).toHaveAttribute(
            'href',
            '/files/7/download',
        );
        expect(screen.getByRole('link', { name: 'Document' })).toHaveAttribute(
            'href',
            '/documents/7',
        );
    });

    it('in the panel does not listen to the arrow keys of the whole window', async () => {
        const view = viewerOf({}, 'panel');

        await fireEvent.keyDown(window, { key: 'ArrowRight' });

        expect(view.emitted().next).toBeUndefined();
    });

    it('dims and disables the arrow at the first and the last drawing', async () => {
        const first = render(FmDrawingViewer, {
            props: {
                drawing: drawingViewerFixture(),
                position: 1,
                total: 3,
                variant: 'screen',
            },
        });
        expect(
            screen.getByRole('button', { name: 'Previous drawing' }),
        ).toBeDisabled();
        expect(
            screen.getByRole('button', { name: 'Next drawing' }),
        ).toBeEnabled();
        await fireEvent.keyDown(window, { key: 'ArrowLeft' });
        expect(first.emitted().previous).toBeUndefined();
        first.unmount();

        const last = render(FmDrawingViewer, {
            props: {
                drawing: drawingViewerFixture(),
                position: 3,
                total: 3,
                variant: 'screen',
            },
        });
        expect(
            screen.getByRole('button', { name: 'Next drawing' }),
        ).toBeDisabled();
        await fireEvent.keyDown(window, { key: 'ArrowRight' });
        expect(last.emitted().next).toBeUndefined();
    });

    it('in the panel puts the arrows left of the place and the close on the right, as the column of a document', () => {
        viewerOf({}, 'panel');

        const previous = screen.getByRole('button', {
            name: 'Previous drawing',
        });
        const next = screen.getByRole('button', { name: 'Next drawing' });
        const place = screen.getByText(/ of /);
        const close = screen.getByRole('button', { name: 'Close' });
        const follows = (a: Element, b: Element): boolean =>
            Boolean(
                a.compareDocumentPosition(b) & Node.DOCUMENT_POSITION_FOLLOWING,
            );
        expect(follows(previous, next)).toBe(true);
        expect(follows(next, place)).toBe(true);
        expect(follows(place, close)).toBe(true);
        expect(previous).toHaveClass('size-10', 'bg-canvas');
    });

    it('in the panel the picture opens the file in a new tab', () => {
        viewerOf({}, 'panel');

        const link = screen.getByRole('img', { name: /./ }).closest('a');
        expect(link).toHaveAttribute('href', '/files/7');
        expect(link).toHaveAttribute('target', '_blank');
    });

    it('on the phone the picture is not a link: a drag moves to the next one', () => {
        viewerOf({}, 'screen');

        expect(screen.getByRole('img', { name: /./ }).closest('a')).toBeNull();
    });

    it('without a place shows no position and no working arrows', () => {
        render(FmDrawingViewer, {
            props: {
                drawing: drawingViewerFixture(),
                position: 0,
                total: 3,
                variant: 'panel',
            },
        });

        expect(screen.queryByText(/ of 3/)).toBeNull();
        expect(
            screen.getByRole('button', { name: 'Previous drawing' }),
        ).toBeDisabled();
        expect(
            screen.getByRole('button', { name: 'Next drawing' }),
        ).toBeDisabled();
    });
});

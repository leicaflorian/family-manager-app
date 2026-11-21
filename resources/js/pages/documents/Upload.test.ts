import { router } from '@inertiajs/vue3';
import { fireEvent, screen } from '@testing-library/vue';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { renderPage, screenWidth, withTrail } from '@/test/page';
import Upload from './Upload.vue';

const shared = vi.hoisted(() => ({ to_sort_count: 0 }));

vi.mock('@inertiajs/vue3', async (original) => ({
    ...(await original<typeof import('@inertiajs/vue3')>()),
    Head: { render: () => null },
    usePage: () => ({ url: '/upload', props: { ...shared } }),
}));

/** The props of the server: only the card the person comes from. */
const props = { presetLinks: [] };

afterEach(() => {
    shared.to_sort_count = 0;
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
});

describe('Upload', () => {
    it('writes the trail To sort > Upload a document into the top bar', () => {
        const { state, provide } = withTrail();
        renderPage(Upload, { ...props }, provide);

        expect(state.pieces.value).toEqual([
            { label: 'To sort', href: '/to-sort' },
            { label: 'Upload a document' },
        ]);
    });

    it('asks to choose the files, with the accepted types', () => {
        renderPage(Upload, { ...props });

        expect(
            screen.getByRole('heading', { name: 'Upload a document' }),
        ).toBeInTheDocument();
        expect(screen.getByLabelText('Choose the files')).toHaveAttribute(
            'accept',
            expect.stringContaining('.heic'),
        );
    });

    it('sends the chosen files', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        renderPage(Upload, { ...props });

        const file = new File(['%PDF-1.4'], 'Busta paga.pdf', {
            type: 'application/pdf',
        });
        await fireEvent.change(screen.getByLabelText('Choose the files'), {
            target: { files: [file] },
        });

        expect(post).toHaveBeenCalledOnce();
        expect(post.mock.calls[0]?.[0]).toBe('/upload');
        expect(post.mock.calls[0]?.[1]).toMatchObject({ files: [file] });
    });

    it('leads to the files to sort when there are some', () => {
        shared.to_sort_count = 1;
        renderPage(Upload, { ...props });

        expect(
            screen.getByRole('link', { name: '1 file to sort' }),
        ).toHaveAttribute('href', '/to-sort');
    });

    it('does not mention the files to sort when there are none', () => {
        renderPage(Upload, { ...props });

        expect(
            screen.queryByRole('link', { name: /to sort/ }),
        ).not.toBeInTheDocument();
    });

    it('keeps the card it comes from when it sends the files', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        renderPage(Upload, {
            ...props,
            presetLinks: [{ type: 'vehicle', id: 1 }],
        });

        const file = new File(['%PDF-1.4'], 'Libretto.pdf', {
            type: 'application/pdf',
        });
        await fireEvent.change(screen.getByLabelText('Choose the files'), {
            target: { files: [file] },
        });

        expect(decodeURIComponent(`${post.mock.calls[0]?.[0] as string}`)).toBe(
            '/upload?link=vehicle:1',
        );
    });

    it('only takes the files: there is no form, the files are checked in Import', () => {
        renderPage(Upload, { ...props });

        expect(screen.queryByLabelText('What it is')).not.toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: 'Save' }),
        ).not.toBeInTheDocument();
    });
});

describe('Upload by layout', () => {
    it('on the phone puts the photo card before the drop zone, with the phone wording', () => {
        shared.to_sort_count = 5;
        renderPage(Upload, { ...props });

        expect(
            screen.getByRole('link', { name: '5 files to sort' }),
        ).toBeInTheDocument();
        const photo = screen.getByRole('link', {
            name: /Take a photo of a sheet/,
        });
        expect(photo).toHaveAttribute('href', '/scan');
        const files = screen.getByLabelText('Choose the files');
        expect(
            photo.compareDocumentPosition(files) &
                Node.DOCUMENT_POSITION_FOLLOWING,
        ).toBeTruthy();
        expect(
            screen.getByText(
                "From the gallery or the phone's files. PDF, JPG, PNG or HEIC, up to 25 MB each",
            ),
        ).toBeInTheDocument();
        expect(
            screen.queryByText('Drag the files here, or choose them'),
        ).not.toBeInTheDocument();
    });

    it('on the phone puts the link to the files to sort on its own row under the title, and on the tablet beside it', () => {
        shared.to_sort_count = 5;
        for (const [width, below] of [
            [390, true],
            [820, false],
        ] as const) {
            screenWidth(width);
            const view = renderPage(Upload, { ...props });

            const link = screen.getByRole('link', { name: '5 files to sort' });
            // the row of the title holds the title and its actions: on the phone the link is below it
            const block = screen
                .getByRole('heading', { name: 'Upload a document' })
                .closest('.justify-between');
            expect(block?.contains(link)).toBe(!below);
            expect(
                screen
                    .getByRole('heading', { name: 'Upload a document' })
                    .compareDocumentPosition(link) &
                    Node.DOCUMENT_POSITION_FOLLOWING,
            ).toBeTruthy();
            view.unmount();
        }
    });

    it('on the tablet shows the photo card beside the drop zone, with the default wording', () => {
        screenWidth(820);
        renderPage(Upload, { ...props });

        expect(
            screen.getByRole('link', { name: /Take a photo of a sheet/ }),
        ).toBeInTheDocument();
        expect(
            screen.getByText('Drag the files here, or choose them'),
        ).toBeInTheDocument();
        expect(
            screen.getByText('PDF, JPG, PNG or HEIC, up to 25 MB each'),
        ).toBeInTheDocument();
    });

    it('on the computer has no photo card', () => {
        screenWidth(1280);
        renderPage(Upload, { ...props });

        expect(
            screen.queryByRole('link', { name: /Take a photo of a sheet/ }),
        ).not.toBeInTheDocument();
        expect(
            screen.getByText('Drag the files here, or choose them'),
        ).toBeInTheDocument();
    });

    it('on the computer keeps the trail To sort > Upload a document', () => {
        screenWidth(1280);
        const { state, provide } = withTrail();
        renderPage(Upload, { ...props }, provide);

        expect(state.pieces.value).toEqual([
            { label: 'To sort', href: '/to-sort' },
            { label: 'Upload a document' },
        ]);
    });
});

describe('Upload on the computer', () => {
    it('on a Mac or a Windows PC offers the home scanner beside the drop zone', () => {
        for (const agent of [
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        ]) {
            screenWidth(1280);
            vi.spyOn(navigator, 'userAgent', 'get').mockReturnValue(agent);
            const view = renderPage(Upload, { ...props });

            expect(
                screen.getByRole('link', {
                    name: /Scan the sheets with the scanner/,
                }),
            ).toHaveAttribute('href', '/scanner');
            expect(
                screen.getByText('Drag the files here, or choose them'),
            ).toBeInTheDocument();
            view.unmount();
            vi.restoreAllMocks();
        }
    });

    it('on a computer the scanner agent is not built for has only the drop zone', () => {
        screenWidth(1280);
        vi.spyOn(navigator, 'userAgent', 'get').mockReturnValue(
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36',
        );
        renderPage(Upload, { ...props });

        expect(
            screen.queryByRole('link', {
                name: /Scan the sheets with the scanner/,
            }),
        ).not.toBeInTheDocument();
    });

    it('on the tablet keeps the photo and does not offer the scanner', () => {
        screenWidth(820);
        vi.spyOn(navigator, 'userAgent', 'get').mockReturnValue(
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        );
        renderPage(Upload, { ...props });

        expect(
            screen.getByRole('link', { name: /Take a photo of a sheet/ }),
        ).toBeInTheDocument();
        expect(
            screen.queryByRole('link', {
                name: /Scan the sheets with the scanner/,
            }),
        ).not.toBeInTheDocument();
    });

    it('has no link back of its own and no ×, the layout draws the way back', () => {
        for (const width of [1280, 820, 390]) {
            screenWidth(width);
            const view = renderPage(Upload, { ...props });

            expect(
                screen.queryByRole('link', { name: 'To sort' }),
            ).not.toBeInTheDocument();
            expect(
                screen.queryByRole('link', { name: 'Close' }),
            ).not.toBeInTheDocument();
            view.unmount();
        }
    });
});

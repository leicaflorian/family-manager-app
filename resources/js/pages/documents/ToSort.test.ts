import { router } from '@inertiajs/vue3';
import { fireEvent, screen, within } from '@testing-library/vue';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import { nextTick } from 'vue';
import { pendingPayslip } from '@/test/fixtures';
import { renderPage, screenWidth } from '@/test/page';
import ToSort from './ToSort.vue';

/** A file as the server sends it, over the payslip of the fixtures. */
function file(
    attributes: Partial<App.Data.PendingFileData>,
): App.Data.PendingFileData {
    return { ...pendingPayslip, ...attributes };
}

const phone = file({
    id: 1,
    name: 'Telefono 2026-10-25 19.04.pdf',
    source: 'phone',
    source_label: 'From the phone',
    note: 'Uploaded from the phone on 25/10/2026 by Laura',
    created_on: '2026-10-25',
    reading_status: 'done',
    can_delete: false,
});
const copy = file({
    id: 2,
    name: 'Telefono 2026-10-25 19.06.pdf',
    source: 'phone',
    source_label: 'From the phone',
    copy_of: 'Telefono 2026-10-25 19.04.pdf',
    created_on: '2026-10-25',
    can_delete: true,
});
const hera = file({
    id: 3,
    name: 'Avviso - La tua fattura è disponibile.txt',
    mime_type: 'text/plain',
    source: 'mail',
    source_label: 'By mail',
    note: null,
    size: 2048,
    reading_status: 'done',
    can_delete: false,
});
const tari = file({
    id: 4,
    name: 'Avviso TARI 2026.pdf',
    reading_status: 'failed',
    reading_error: 'The file could not be read.',
});
const warranty = file({
    id: 5,
    name: 'Garanzia lavatrice.jpg',
    mime_type: 'image/jpeg',
    pages: 1,
    reading_status: 'reading',
});

/** The filters as the server sends them, with these numbers. */
function filters(
    all: number,
    mail: number,
    phone: number,
    upload: number,
): App.Data.ToSortPageData['filters'] {
    return [
        { value: null, label: 'All', count: all },
        { value: 'mail', label: 'By mail', count: mail },
        { value: 'phone', label: 'From the phone', count: phone },
        { value: 'upload', label: 'Uploaded here', count: upload },
    ];
}

const page = {
    files: [warranty, tari, hera, copy, phone],
    source: null,
    filters: filters(5, 1, 2, 2),
    can_delete_many: true,
};

const empty = {
    files: [],
    source: null,
    filters: filters(0, 0, 0, 0),
    can_delete_many: true,
};

/** The phone layout: the default of jsdom, with the user agent of a phone. */
function onPhone(): void {
    screenWidth(390);
    vi.spyOn(navigator, 'userAgent', 'get').mockReturnValue(
        'Mozilla/5.0 (Linux; Android 15; Pixel 9) Mobile',
    );
}

/** The tablet layout, as an iPad. */
function onTablet(): void {
    screenWidth(820, 1180);
}

/** The computer layout. */
function onComputer(): void {
    screenWidth(1280);
}

/**
 * The checkbox of a file in the list of the tablet and the computer.
 *
 * @param name - the name of the file
 * @returns the checkbox
 */
function box(name: string): HTMLElement {
    return screen.getByRole('checkbox', { name });
}

beforeEach(() => {
    vi.useFakeTimers({ shouldAdvanceTime: true });
});

afterEach(() => {
    vi.useRealTimers();
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
});

describe('ToSort on the phone', () => {
    beforeEach(onPhone);

    it('says how many files wait, in short', () => {
        renderPage(ToSort, page);

        expect(screen.getByText('5 files waiting')).toBeInTheDocument();
    });

    it('offers Take a photo and Choose a file', () => {
        renderPage(ToSort, page);

        expect(
            screen.getByRole('link', { name: 'Take a photo' }),
        ).toHaveAttribute('href', '/scan');
        expect(
            screen.getByRole('link', { name: 'Choose a file' }),
        ).toHaveAttribute('href', '/upload');
    });

    it('shows the filters as chips that are links, All current, and a filter with none is not a link', () => {
        renderPage(ToSort, { ...page, filters: filters(5, 0, 2, 3) });
        const chips = screen.getByRole('navigation', { name: 'Files to sort' });

        expect(
            within(chips).getByRole('link', { name: 'All 5' }),
        ).toHaveAttribute('aria-current', 'page');
        expect(
            within(chips).getByRole('link', { name: 'From the phone 2' }),
        ).toHaveAttribute('href', '/to-sort?source=phone');
        expect(
            within(chips).getByRole('link', { name: 'Uploaded here 3' }),
        ).toHaveAttribute('href', '/to-sort?source=upload');
        expect(
            within(chips).queryByRole('link', { name: /^By mail/ }),
        ).not.toBeInTheDocument();
        expect(within(chips).getByText('By mail')).toBeInTheDocument();
    });

    it('has no zone to drop files, no Received mail and no Choose all', () => {
        renderPage(ToSort, page);

        expect(
            screen.queryByLabelText('Choose the files'),
        ).not.toBeInTheDocument();
        expect(
            screen.queryByRole('link', { name: /Received mail/ }),
        ).not.toBeInTheDocument();
        expect(
            screen.queryByRole('checkbox', { name: 'Choose all' }),
        ).not.toBeInTheDocument();
    });

    it('chooses files by touching their rows, and checks them from the bar below', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        renderPage(ToSort, page);

        expect(screen.queryByText(/chosen/)).not.toBeInTheDocument();
        await fireEvent.click(
            screen.getByRole('button', { name: 'Avviso TARI 2026.pdf' }),
        );
        await fireEvent.click(
            screen.getByRole('button', { name: 'Garanzia lavatrice.jpg' }),
        );

        expect(
            screen.getByRole('button', { name: 'Avviso TARI 2026.pdf' }),
        ).toHaveAttribute('aria-pressed', 'true');
        expect(screen.getByText('2 chosen')).toBeInTheDocument();
        await fireEvent.click(
            screen.getByRole('button', { name: 'Check 2 files' }),
        );

        expect(post).toHaveBeenCalledWith('/to-sort/read', { ids: [5, 4] });
    });

    it('deletes the chosen files from the bin of the bar, after asking', async () => {
        const remove = vi.spyOn(router, 'delete').mockImplementation(() => {});
        renderPage(ToSort, page);

        await fireEvent.click(
            screen.getByRole('button', { name: 'Avviso TARI 2026.pdf' }),
        );
        await fireEvent.click(
            screen.getByRole('button', { name: 'Delete the chosen file' }),
        );
        expect(remove).not.toHaveBeenCalled();
        expect(
            await screen.findByText('Delete the chosen file?'),
        ).toBeInTheDocument();
        await fireEvent.click(
            within(screen.getByRole('dialog')).getByRole('button', {
                name: 'Delete',
            }),
        );

        expect(remove).toHaveBeenCalledWith(
            '/to-sort',
            expect.objectContaining({
                data: { ids: [4] },
                preserveScroll: true,
            }),
        );
    });

    it('says there is nothing to sort when the list is empty', () => {
        renderPage(ToSort, empty);

        expect(screen.getByText('Nothing to sort')).toBeInTheDocument();
        expect(
            screen.getByText(
                'The files you upload, or that arrive by mail or from the phone, wait here until they become a document.',
            ),
        ).toBeInTheDocument();
    });
});

describe('ToSort on the tablet', () => {
    beforeEach(onTablet);

    it('says how many files wait, at length', () => {
        renderPage(ToSort, page);

        expect(
            screen.getByText('5 files waiting to be imported'),
        ).toBeInTheDocument();
    });

    it('offers Choose the files and Take a photo beside the title, and the chips', () => {
        renderPage(ToSort, page);

        expect(
            screen.getByRole('link', { name: 'Choose the files' }),
        ).toHaveAttribute('href', '/upload');
        expect(
            screen.getByRole('link', { name: 'Take a photo' }),
        ).toHaveAttribute('href', '/scan');
        expect(
            screen.getByRole('navigation', { name: 'Files to sort' }),
        ).toBeInTheDocument();
    });

    it('has no zone to drop files, no Link the phone and no Scan', () => {
        vi.spyOn(navigator, 'userAgent', 'get').mockReturnValue(
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15',
        );
        renderPage(ToSort, page);

        expect(
            screen.queryByLabelText('Choose the files'),
        ).not.toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: 'Link the phone' }),
        ).not.toBeInTheDocument();
        expect(
            screen.queryByRole('link', { name: 'Scan the sheets' }),
        ).not.toBeInTheDocument();
    });

    it('has the bar of the list with the count, Delete the chosen and Check', async () => {
        renderPage(ToSort, page);

        await fireEvent.click(box('Avviso TARI 2026.pdf'));

        expect(screen.getByText('1 file chosen of 5')).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Delete the chosen' }),
        ).toBeEnabled();
        expect(
            screen.getByRole('button', { name: 'Check 1 file' }),
        ).toBeEnabled();
    });
});

describe('ToSort on the computer', () => {
    beforeEach(onComputer);

    it('opens the panel to link the phone from the button at the top', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn().mockResolvedValue({
                ok: true,
                json: () =>
                    Promise.resolve({
                        code: 'K7QM-XPA9',
                        qr: '<svg xmlns="http://www.w3.org/2000/svg"/>',
                        address: 'familymanager.it/connect',
                        expires_at: '2999-01-01T00:00:00+00:00',
                        person: 'Marco',
                        family: 'Bianchi',
                        devices: [],
                    }),
            }),
        );
        renderPage(ToSort, page);

        await fireEvent.click(
            screen.getByRole('button', { name: 'Link the phone' }),
        );

        expect(await screen.findByText('K7QM-XPA9')).toBeInTheDocument();
    });

    it('offers Scan on a Mac or a Windows PC, which opens the scanner', () => {
        vi.spyOn(navigator, 'userAgent', 'get').mockReturnValue(
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15',
        );
        renderPage(ToSort, page);

        expect(
            screen.getByRole('link', { name: 'Scan the sheets' }),
        ).toHaveAttribute('href', '/scanner');
    });

    it('does not offer Scan on Linux, nor Take a photo', () => {
        vi.spyOn(navigator, 'userAgent', 'get').mockReturnValue(
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36',
        );
        renderPage(ToSort, page);

        expect(
            screen.queryByRole('link', { name: 'Scan the sheets' }),
        ).not.toBeInTheDocument();
        expect(
            screen.queryByRole('link', { name: 'Take a photo' }),
        ).not.toBeInTheDocument();
    });

    it('lists where the files come from in the box beside the list, without Received mail, and how long they stay', () => {
        renderPage(ToSort, { ...page, filters: filters(5, 0, 2, 3) });

        const panel = screen.getByRole('navigation', {
            name: 'Where they come from',
        });
        expect(
            within(panel).getByRole('link', { name: 'All 5' }),
        ).toHaveAttribute('aria-current', 'page');
        expect(
            within(panel).getByRole('link', {
                name: 'Uploaded from the phone 2',
            }),
        ).toHaveAttribute('href', '/to-sort?source=phone');
        expect(
            within(panel).getByRole('link', { name: 'Uploaded here 3' }),
        ).toHaveAttribute('href', '/to-sort?source=upload');
        expect(
            within(panel).queryByRole('link', { name: /Arrived by mail/ }),
        ).not.toBeInTheDocument();
        expect(within(panel).getByText('Arrived by mail')).toBeInTheDocument();
        expect(
            screen.queryByRole('link', { name: /Received mail/ }),
        ).not.toBeInTheDocument();
        expect(
            screen.getByText(
                'The files that arrived by mail, from the phone or uploaded here stay until you import or delete them.',
            ),
        ).toBeInTheDocument();
        expect(
            screen.queryByRole('navigation', { name: 'Files to sort' }),
        ).not.toBeInTheDocument();
    });

    it('keeps the files dropped in the zone among those to sort', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        renderPage(ToSort, page);

        expect(
            screen.getByText('choose from the computer'),
        ).toBeInTheDocument();
        await fireEvent.change(screen.getByLabelText('Choose the files'), {
            target: {
                files: [
                    new File(['%PDF'], 'Bolletta.pdf', {
                        type: 'application/pdf',
                    }),
                ],
            },
        });

        expect(post.mock.calls[0]?.[0]).toBe('/upload?stay=1');
    });

    it('lists each file with its pages and size, or what it is, and its short date', () => {
        renderPage(ToSort, { ...page, files: [warranty, tari, hera, phone] });

        const row = (name: string): HTMLElement =>
            screen.getByText(name, { selector: 'a' }).closest('li')!;
        expect(
            within(row('Avviso TARI 2026.pdf')).getByText('117 KB'),
        ).toBeInTheDocument();
        expect(
            within(row('Avviso TARI 2026.pdf')).getByText('22/10'),
        ).toBeInTheDocument();
        expect(
            within(row('Garanzia lavatrice.jpg')).getByText('Photo · 117 KB'),
        ).toBeInTheDocument();
        expect(
            within(row('Avviso - La tua fattura è disponibile.txt')).getByText(
                'Text of the email',
            ),
        ).toBeInTheDocument();
    });

    it('shows the note and the state of the reading of each file', () => {
        renderPage(ToSort, page);

        expect(
            screen.getByText('Uploaded from the phone on 25/10/2026 by Laura'),
        ).toBeInTheDocument();
        expect(
            screen.getByText('The file could not be read.'),
        ).toBeInTheDocument();
        expect(screen.getByText('Reading the document…')).toBeInTheDocument();
    });

    it('does not let a copy be chosen', () => {
        renderPage(ToSort, page);

        expect(box('Telefono 2026-10-25 19.06.pdf')).toBeDisabled();
        expect(box('Avviso TARI 2026.pdf')).toBeEnabled();
    });

    it('chooses every file but the copies with the box at the top, and lets them all go', async () => {
        renderPage(ToSort, page);

        const all = box('Choose all');
        expect(screen.getByText('0 files chosen of 5')).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Check files' }),
        ).toBeDisabled();
        expect(
            screen.queryByRole('button', { name: 'Delete the chosen' }),
        ).not.toBeInTheDocument();

        await fireEvent.click(all);
        expect(screen.getByText('4 files chosen of 5')).toBeInTheDocument();
        expect(all).toBeChecked();
        expect(
            screen.getByRole('button', { name: 'Delete the chosen' }),
        ).toBeEnabled();

        await fireEvent.click(all);
        expect(screen.getByText('0 files chosen of 5')).toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: 'Delete the chosen' }),
        ).not.toBeInTheDocument();
    });

    it('counts the files chosen and sends them to be read with Check', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        renderPage(ToSort, page);

        await fireEvent.click(box('Avviso TARI 2026.pdf'));
        await fireEvent.click(box('Garanzia lavatrice.jpg'));
        await fireEvent.click(box('Telefono 2026-10-25 19.04.pdf'));

        expect(screen.getByText('3 files chosen of 5')).toBeInTheDocument();
        await fireEvent.click(
            screen.getByRole('button', { name: 'Check 3 files' }),
        );

        expect(post).toHaveBeenCalledWith('/to-sort/read', {
            ids: [5, 4, 1],
        });
    });

    it('deletes the chosen files after asking', async () => {
        const remove = vi.spyOn(router, 'delete').mockImplementation(() => {});
        renderPage(ToSort, page);

        await fireEvent.click(box('Avviso TARI 2026.pdf'));
        await fireEvent.click(box('Garanzia lavatrice.jpg'));
        await fireEvent.click(box('Telefono 2026-10-25 19.04.pdf'));
        await fireEvent.click(
            screen.getByRole('button', { name: 'Delete the chosen' }),
        );

        expect(remove).not.toHaveBeenCalled();
        expect(await screen.findByText('Delete 3 files?')).toBeInTheDocument();
        expect(
            screen.getByText('Deleted files cannot be recovered.'),
        ).toBeInTheDocument();
        await fireEvent.click(
            within(screen.getByRole('dialog')).getByRole('button', {
                name: 'Delete',
            }),
        );

        expect(remove).toHaveBeenCalledWith(
            '/to-sort',
            expect.objectContaining({
                data: { ids: [5, 4, 1] },
                preserveScroll: true,
            }),
        );
    });

    it('deletes once on a double tap, and keeps Check off while it deletes', async () => {
        const remove = vi.spyOn(router, 'delete').mockImplementation(() => {});
        renderPage(ToSort, page);

        await fireEvent.click(box('Avviso TARI 2026.pdf'));
        await fireEvent.click(
            screen.getByRole('button', { name: 'Delete the chosen' }),
        );
        const confirm = within(await screen.findByRole('dialog')).getByRole(
            'button',
            { name: 'Delete' },
        );
        await fireEvent.click(confirm);
        await fireEvent.click(confirm);

        expect(remove).toHaveBeenCalledOnce();
        // The panel is still open over the page, which is hidden from the roles meanwhile
        expect(
            screen.getByRole('button', { name: 'Check 1 file', hidden: true }),
        ).toBeDisabled();
    });

    it('keeps the count of the question while the panel closes after deleting', async () => {
        const remove = vi.spyOn(router, 'delete').mockImplementation(() => {});
        renderPage(ToSort, page);

        await fireEvent.click(box('Avviso TARI 2026.pdf'));
        await fireEvent.click(box('Garanzia lavatrice.jpg'));
        await fireEvent.click(
            screen.getByRole('button', { name: 'Delete the chosen' }),
        );
        await fireEvent.click(
            within(await screen.findByRole('dialog')).getByRole('button', {
                name: 'Delete',
            }),
        );
        const options = remove.mock.calls[0]?.[1];
        options?.onSuccess?.({} as never);
        await nextTick();

        expect(screen.getByText('Delete 2 files?')).toBeInTheDocument();
    });

    it('has no Delete the chosen for who cannot delete many files', async () => {
        renderPage(ToSort, { ...page, can_delete_many: false });

        await fireEvent.click(box('Avviso TARI 2026.pdf'));

        expect(
            screen.queryByRole('button', { name: 'Delete the chosen' }),
        ).not.toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Check 1 file' }),
        ).toBeEnabled();
    });

    it('stops counting a chosen file that left the list', async () => {
        const view = renderPage(ToSort, page);

        await fireEvent.click(box('Avviso TARI 2026.pdf'));
        await fireEvent.click(box('Garanzia lavatrice.jpg'));
        await view.rerender({
            ...page,
            files: structuredClone([warranty, hera, copy, phone]),
        });

        expect(screen.getByText('1 file chosen of 4')).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Check 1 file' }),
        ).toBeEnabled();
    });

    it('offers Delete only where the server allows it, and Delete the copy on a copy', () => {
        renderPage(ToSort, page);

        const copyRow = screen
            .getByText('Telefono 2026-10-25 19.06.pdf')
            .closest('li') as HTMLElement;
        expect(
            within(copyRow).getByRole('button', { name: 'Delete the copy' }),
        ).toBeInTheDocument();

        const heraRow = screen
            .getByText('Avviso - La tua fattura è disponibile.txt')
            .closest('li') as HTMLElement;
        expect(
            within(heraRow).queryByRole('button', { name: /Delete/ }),
        ).not.toBeInTheDocument();
    });

    it('marks the filter chosen', () => {
        renderPage(ToSort, { ...page, source: 'mail', files: [hera] });

        expect(
            screen.getByRole('link', { name: 'Arrived by mail 1' }),
        ).toHaveAttribute('aria-current', 'page');
    });

    it('asks for the state of the files every 2 seconds while one is read, and stops after', async () => {
        const reload = vi.spyOn(router, 'visit').mockImplementation(() => {});
        const result = renderPage(ToSort, page);

        await vi.advanceTimersByTimeAsync(2000);
        expect(reload).toHaveBeenCalledOnce();
        expect(reload).toHaveBeenCalledWith(
            expect.anything(),
            expect.objectContaining({
                only: ['files', 'filters'],
                preserveUrl: true,
            }),
        );

        await result.rerender({
            ...page,
            files: page.files.map((item) => ({
                ...item,
                reading_status: 'done' as const,
            })),
        });
        await vi.advanceTimersByTimeAsync(6000);
        expect(reload).toHaveBeenCalledOnce();
    });

    it('does not ask when no file is being read', async () => {
        const reload = vi.spyOn(router, 'visit').mockImplementation(() => {});
        renderPage(ToSort, { ...page, files: [hera, phone] });

        await vi.advanceTimersByTimeAsync(6000);

        expect(reload).not.toHaveBeenCalled();
    });

    it('says there is nothing to sort when the list is empty, and keeps the zone to drop files', () => {
        renderPage(ToSort, empty);

        expect(screen.getByText('Nothing to sort')).toBeInTheDocument();
        expect(screen.getByLabelText('Choose the files')).toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: /Check/ }),
        ).not.toBeInTheDocument();
    });
});

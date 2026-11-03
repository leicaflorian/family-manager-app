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
import { pendingPayslip } from '@/test/fixtures';
import { renderPage } from '@/test/page';
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
    note: 'Uploaded from the phone on 25/10/2026 by Laura',
    created_on: '2026-10-25',
    reading_status: 'done',
    can_delete: false,
});
const copy = file({
    id: 2,
    name: 'Telefono 2026-10-25 19.06.pdf',
    source: 'phone',
    copy_of: 'Telefono 2026-10-25 19.04.pdf',
    created_on: '2026-10-25',
    can_delete: true,
});
const hera = file({
    id: 3,
    name: 'Avviso - La tua fattura è disponibile.txt',
    mime_type: 'text/plain',
    source: 'mail',
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

const page = {
    files: [warranty, tari, hera, copy, phone],
    source: null,
    counts: { all: 5, mail: 1, phone: 2, upload: 2 },
};

beforeEach(() => {
    vi.useFakeTimers({ shouldAdvanceTime: true });
});

afterEach(() => {
    vi.useRealTimers();
    vi.restoreAllMocks();
});

describe('ToSort', () => {
    it('lists each file with its date, its size or pages and where it came from', () => {
        renderPage(ToSort, page);

        const row = screen
            .getByText('Avviso - La tua fattura è disponibile.txt')
            .closest('li') as HTMLElement;
        expect(within(row).getByText(/22\/10\/2026/)).toBeInTheDocument();
        expect(within(row).getByText(/2 KB/)).toBeInTheDocument();
        expect(within(row).getByText(/By mail/)).toBeInTheDocument();

        const photo = screen
            .getByText('Garanzia lavatrice.jpg')
            .closest('li') as HTMLElement;
        expect(within(photo).getByText(/1 page/)).toBeInTheDocument();
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
        expect(screen.getAllByText('Read')).toHaveLength(2);
    });

    it('says a copy is a copy, and which file it is the same as', () => {
        renderPage(ToSort, page);

        expect(
            screen.getByText('It is a copy: you can delete it'),
        ).toBeInTheDocument();
        expect(
            screen.getByText('Same as Telefono 2026-10-25 19.04.pdf'),
        ).toBeInTheDocument();
    });

    it('does not let a copy be chosen', () => {
        renderPage(ToSort, page);

        expect(
            screen.getByRole('checkbox', {
                name: 'Telefono 2026-10-25 19.06.pdf',
            }),
        ).toBeDisabled();
        expect(
            screen.getByRole('checkbox', { name: 'Avviso TARI 2026.pdf' }),
        ).toBeEnabled();
    });

    it('shows the filters with their numbers, and a filter with none cannot be chosen', () => {
        renderPage(ToSort, {
            ...page,
            counts: { all: 5, mail: 0, phone: 2, upload: 3 },
        });

        expect(screen.getByRole('link', { name: 'All 5' })).toHaveAttribute(
            'href',
            '/to-sort',
        );
        expect(
            screen.getByRole('link', { name: 'From the phone 2' }),
        ).toHaveAttribute('href', '/to-sort?source=phone');
        expect(
            screen.getByRole('link', { name: 'Uploaded here 3' }),
        ).toHaveAttribute('href', '/to-sort?source=upload');
        expect(
            screen.queryByRole('link', { name: /^By mail/ }),
        ).not.toBeInTheDocument();
    });

    it('marks the filter chosen', () => {
        renderPage(ToSort, { ...page, source: 'mail', files: [hera] });

        expect(screen.getByRole('link', { name: 'By mail 1' })).toHaveAttribute(
            'aria-current',
            'page',
        );
    });

    it('offers Delete only where the server allows it, and Delete the copy on a copy', () => {
        renderPage(ToSort, page);

        const copyRow = screen
            .getByText('Telefono 2026-10-25 19.06.pdf')
            .closest('li') as HTMLElement;
        expect(
            within(copyRow).getByRole('button', { name: 'Delete the copy' }),
        ).toBeInTheDocument();

        const warrantyRow = screen
            .getByText('Garanzia lavatrice.jpg')
            .closest('li') as HTMLElement;
        expect(
            within(warrantyRow).getByRole('button', { name: 'Delete' }),
        ).toBeInTheDocument();

        const heraRow = screen
            .getByText('Avviso - La tua fattura è disponibile.txt')
            .closest('li') as HTMLElement;
        expect(
            within(heraRow).queryByRole('button', { name: /Delete/ }),
        ).not.toBeInTheDocument();
    });

    it('counts the files chosen on the button, and sends them to be read', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        renderPage(ToSort, page);

        expect(
            screen.getByRole('button', { name: 'Read and import files' }),
        ).toBeDisabled();

        await fireEvent.click(
            screen.getByRole('checkbox', { name: 'Avviso TARI 2026.pdf' }),
        );
        await fireEvent.click(
            screen.getByRole('checkbox', { name: 'Garanzia lavatrice.jpg' }),
        );
        const button = screen.getByRole('button', {
            name: 'Read and import 2 files',
        });
        await fireEvent.click(button);

        expect(post).toHaveBeenCalledWith('/to-sort/read', { ids: [5, 4] });
    });

    it('says one file in the singular', async () => {
        renderPage(ToSort, page);

        await fireEvent.click(
            screen.getByRole('checkbox', { name: 'Avviso TARI 2026.pdf' }),
        );

        expect(
            screen.getByRole('button', { name: 'Read and import 1 file' }),
        ).toBeEnabled();
    });

    it('asks for the state of the files every 2 seconds while one is read, and stops after', async () => {
        const reload = vi.spyOn(router, 'visit').mockImplementation(() => {});
        const result = renderPage(ToSort, page);

        await vi.advanceTimersByTimeAsync(2000);
        expect(reload).toHaveBeenCalledOnce();
        expect(reload).toHaveBeenCalledWith(
            expect.anything(),
            expect.objectContaining({
                only: ['files', 'counts'],
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

    it('says there is nothing to sort when the list is empty', () => {
        renderPage(ToSort, {
            files: [],
            source: null,
            counts: { all: 0, mail: 0, phone: 0, upload: 0 },
        });

        expect(screen.getByText('Nothing to sort')).toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: /Read and import/ }),
        ).not.toBeInTheDocument();
    });
});

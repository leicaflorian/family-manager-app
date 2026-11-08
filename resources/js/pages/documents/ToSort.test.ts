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
        { value: 'upload', label: 'Uploaded manually', count: upload },
    ];
}

const page = {
    files: [warranty, tari, hera, copy, phone],
    source: null,
    filters: filters(5, 1, 2, 2),
};

beforeEach(() => {
    vi.useFakeTimers({ shouldAdvanceTime: true });
});

afterEach(() => {
    vi.useRealTimers();
    vi.restoreAllMocks();
});

describe('ToSort', () => {
    it('says how many files wait, under the title', () => {
        renderPage(ToSort, page);

        expect(
            screen.getByText('5 files waiting to be imported'),
        ).toBeInTheDocument();
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
            within(row('Telefono 2026-10-25 19.04.pdf')).getByText('25/10'),
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

    it('lists the pages of a PDF beside its size', () => {
        renderPage(ToSort, {
            ...page,
            files: [file({ id: 9, name: 'Bolletta.pdf', pages: 3 })],
        });

        expect(screen.getByText('3 pages · 117 KB')).toBeInTheDocument();
    });

    it('opens the file from its name, in a new tab', () => {
        renderPage(ToSort, page);

        expect(
            screen.getByRole('link', { name: 'Avviso TARI 2026.pdf' }),
        ).toHaveAttribute('target', '_blank');
    });

    it('lights the whole row of a chosen file', async () => {
        renderPage(ToSort, page);

        await fireEvent.click(
            screen.getByRole('checkbox', { name: 'Avviso TARI 2026.pdf' }),
        );

        expect(
            screen
                .getByRole('checkbox', { name: 'Avviso TARI 2026.pdf' })
                .closest('li'),
        ).toHaveClass('bg-action-soft');
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
        // Read is the usual end of a reading: it is not written
        expect(screen.queryByText('Read')).not.toBeInTheDocument();
    });

    it('says a copy is a copy, and which file it is the same as', () => {
        renderPage(ToSort, page);

        expect(screen.getByText('Copy')).toBeInTheDocument();
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

    it('shows the filters as tabs with their numbers, and a filter with none cannot be chosen', () => {
        renderPage(ToSort, {
            ...page,
            filters: filters(5, 0, 2, 3),
        });
        const tabs = screen.getByRole('navigation', { name: 'Files to sort' });

        expect(
            within(tabs).getByRole('link', { name: 'All 5' }),
        ).toHaveAttribute('href', '/to-sort');
        expect(
            within(tabs).getByRole('link', { name: 'From the phone 2' }),
        ).toHaveAttribute('href', '/to-sort?source=phone');
        expect(
            within(tabs).getByRole('link', { name: 'Uploaded manually 3' }),
        ).toHaveAttribute('href', '/to-sort?source=upload');
        expect(
            within(tabs).queryByRole('link', { name: /^By mail/ }),
        ).not.toBeInTheDocument();
    });

    it('lists where the files come from in the panel beside the list, with their numbers', () => {
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
            within(panel).getByRole('link', { name: 'Uploaded manually 3' }),
        ).toHaveAttribute('href', '/to-sort?source=upload');
        // With no file, it is there but cannot be chosen
        expect(
            within(panel).queryByRole('link', { name: /Arrived by mail/ }),
        ).not.toBeInTheDocument();
        expect(within(panel).getByText('Arrived by mail')).toBeInTheDocument();
    });

    it('leads to Received mail from the icon beside the title and from the row under the filters', () => {
        renderPage(ToSort, { ...page, filters: filters(5, 0, 2, 3) });

        const panel = screen.getByRole('navigation', {
            name: 'Where they come from',
        }).parentElement as HTMLElement;
        const row = within(panel).getByRole('link', { name: /Received mail/ });
        expect(row).toHaveAttribute('href', '/mail');
        expect(
            within(row).getByText('The mails and what was done with them'),
        ).toBeInTheDocument();

        const icon = screen
            .getAllByRole('link', { name: 'Received mail' })
            .find((link) => !panel.contains(link)) as HTMLElement;
        expect(icon).toHaveAttribute('href', '/mail');
    });

    it('chooses every file but the copies with the box at the top, and lets them all go', async () => {
        renderPage(ToSort, page);

        const all = screen.getByRole('checkbox', { name: 'Choose all' });
        expect(screen.getByText('0 files chosen of 5')).toBeInTheDocument();

        await fireEvent.click(all);
        expect(screen.getByText('4 files chosen of 5')).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Read and review 4 files' }),
        ).toBeEnabled();
        expect(all).toBeChecked();

        await fireEvent.click(all);
        expect(screen.getByText('0 files chosen of 5')).toBeInTheDocument();
    });

    it('says nothing is saved yet in the tooltip of the button', async () => {
        renderPage(ToSort, page);

        await fireEvent.click(
            screen.getByRole('checkbox', { name: 'Avviso TARI 2026.pdf' }),
        );
        screen.getByRole('button', { name: 'Read and review 1 file' }).focus();

        expect(await screen.findByRole('tooltip')).toHaveTextContent(
            'Nothing is saved yet: you see what was read, and you can read it again or change it before importing.',
        );
    });

    it('keeps the files dropped here among those to sort', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        renderPage(ToSort, page);

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
            screen.getByRole('button', { name: 'Read and review files' }),
        ).toBeDisabled();

        await fireEvent.click(
            screen.getByRole('checkbox', { name: 'Avviso TARI 2026.pdf' }),
        );
        await fireEvent.click(
            screen.getByRole('checkbox', { name: 'Garanzia lavatrice.jpg' }),
        );
        const button = screen.getByRole('button', {
            name: 'Read and review 2 files',
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
            screen.getByRole('button', { name: 'Read and review 1 file' }),
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

    it('says there is nothing to sort when the list is empty', () => {
        renderPage(ToSort, {
            files: [],
            source: null,
            filters: filters(0, 0, 0, 0),
        });

        expect(screen.getByText('Nothing to sort')).toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: /Read and review/ }),
        ).not.toBeInTheDocument();
    });
});

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
import { renderPage, settle } from '@/test/page';
import Import from './Import.vue';

/** A file as the server sends it, over the payslip of the fixtures. */
function file(
    attributes: Partial<App.Data.PendingFileData>,
): App.Data.PendingFileData {
    return { ...pendingPayslip, ...attributes };
}

const proposal: App.Data.Reading.DocumentProposalData = {
    title: 'Luce ottobre',
    category_id: null,
    person_id: null,
    organization_id: null,
    date: '2026-10-25',
    expiry_date: null,
    links: [],
    headline: 'I found an electricity bill',
    found: [{ label: 'Date', value: '25/10/2026' }],
};

const light = file({
    id: 1,
    name: 'bolletta-luce.pdf',
    title: 'bolletta luce',
    reading_status: 'done',
    proposal,
});
const gas = file({
    id: 2,
    name: 'bolletta-gas.pdf',
    title: 'bolletta gas',
    reading_status: 'done',
    proposal: { ...proposal, title: 'Gas ottobre' },
});
const warranty = file({
    id: 3,
    name: 'garanzia.jpg',
    mime_type: 'image/jpeg',
    title: 'garanzia',
    reading_status: 'failed',
    reading_error: 'The file could not be read.',
});

const options = {
    categories: [],
    people: [],
    organizations: [],
    links: [],
    statuses: [{ value: 'active', label: 'Active', help: null }],
    tags: [],
};

/** The props the server sends for these files. */
function props(
    files: App.Data.PendingFileData[],
    failures: Record<number, string> = {},
): Record<string, unknown> {
    return { files, failures, ...options };
}

/** The row of a file in the list of the computer. */
function row(name: string): HTMLElement {
    return screen.getByRole('button', { name: new RegExp(name) });
}

beforeEach(() => {
    vi.useFakeTimers({ shouldAdvanceTime: true });
});

afterEach(() => {
    vi.useRealTimers();
    vi.restoreAllMocks();
});

describe('Import', () => {
    it('lists the files with their state: the first is being looked at, the others to check', () => {
        renderPage(Import, props([light, gas, warranty]));

        expect(
            screen.getByRole('heading', { name: 'Import more files' }),
        ).toBeInTheDocument();
        expect(
            within(row('bolletta-luce.pdf')).getByText('You are looking at it'),
        ).toBeInTheDocument();
        expect(
            within(row('bolletta-gas.pdf')).getByText('To check'),
        ).toBeInTheDocument();
        expect(
            within(row('garanzia.jpg')).getByText('To check'),
        ).toBeInTheDocument();
    });

    it('moves on with Next and Previous, and a file left behind is checked', async () => {
        renderPage(Import, props([light, gas, warranty]));
        const previous = screen.getByRole('button', { name: 'Previous' });
        const next = screen.getByRole('button', { name: 'Next' });
        expect(previous).toBeDisabled();

        await fireEvent.click(next);

        expect(
            within(row('bolletta-luce.pdf')).getByText('Checked'),
        ).toBeInTheDocument();
        expect(
            within(row('bolletta-gas.pdf')).getByText('You are looking at it'),
        ).toBeInTheDocument();
        expect(screen.getByText('2 of 3')).toBeInTheDocument();

        await fireEvent.click(next);
        expect(next).toBeDisabled();

        await fireEvent.click(previous);
        expect(
            within(row('bolletta-gas.pdf')).getByText('You are looking at it'),
        ).toBeInTheDocument();
        expect(
            within(row('garanzia.jpg')).getByText('Checked'),
        ).toBeInTheDocument();
    });

    it('opens a file by its row in the list', async () => {
        renderPage(Import, props([light, gas, warranty]));

        await fireEvent.click(row('garanzia.jpg'));

        expect(
            within(row('garanzia.jpg')).getByText('You are looking at it'),
        ).toBeInTheDocument();
        expect(
            screen.getByText('The file could not be read.'),
        ).toBeInTheDocument();
    });

    it('closes to the files to sort, asking first about changes not saved', () => {
        renderPage(Import, props([light]));

        const close = screen.getByRole('link', { name: 'Close' });
        expect(close).toHaveAttribute('href', '/to-sort');
        expect(close).toHaveAttribute('data-discard-changes');
    });

    it('counts the files on the button', () => {
        renderPage(Import, props([light, gas, warranty]));

        expect(
            screen.getByRole('button', { name: 'Import the 3 files' }),
        ).toBeEnabled();
    });

    it('counts one file in the singular', () => {
        renderPage(Import, props([light]));

        expect(
            screen.getByRole('button', { name: 'Import the file' }),
        ).toBeInTheDocument();
    });

    it('leaves a file out with Do not import this file, and takes it back', async () => {
        renderPage(Import, props([light, gas, warranty]));

        await fireEvent.click(
            screen.getByRole('button', { name: 'Do not import this file' }),
        );

        expect(
            within(row('bolletta-luce.pdf')).getByText('Left out'),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Import the 2 files' }),
        ).toBeInTheDocument();

        await fireEvent.click(
            screen.getByRole('button', { name: 'Import this file' }),
        );

        expect(
            screen.getByRole('button', { name: 'Import the 3 files' }),
        ).toBeInTheDocument();
    });

    it('cannot import with every file left out', async () => {
        renderPage(Import, props([light]));

        await fireEvent.click(
            screen.getByRole('button', { name: 'Do not import this file' }),
        );

        expect(
            screen.getByRole('button', { name: 'Import the file' }),
        ).toBeDisabled();
    });

    it('fills each form with its proposal and sends the three documents', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        renderPage(Import, props([light, gas, warranty]));
        await settle();

        await fireEvent.click(
            screen.getByRole('button', { name: 'Import the 3 files' }),
        );

        expect(post).toHaveBeenCalledWith(
            '/import',
            {
                files: [
                    {
                        file_id: 1,
                        document: expect.objectContaining({
                            title: 'Luce ottobre',
                            date: '2026-10-25',
                            status: 'active',
                        }),
                    },
                    {
                        file_id: 2,
                        document: expect.objectContaining({
                            title: 'Gas ottobre',
                        }),
                    },
                    {
                        file_id: 3,
                        document: expect.objectContaining({
                            title: 'garanzia',
                        }),
                    },
                ],
            },
            expect.anything(),
        );
    });

    it('sends what the person changed, and not the file left out', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        renderPage(Import, props([light, gas]));
        await settle();

        const [title] = screen.getAllByLabelText('What it is');
        await fireEvent.update(title!, 'Luce di ottobre');
        await settle();
        await fireEvent.click(screen.getByRole('button', { name: 'Next' }));
        await fireEvent.click(
            screen.getByRole('button', { name: 'Do not import this file' }),
        );
        await fireEvent.click(
            screen.getByRole('button', { name: 'Import the file' }),
        );

        const [, body] = post.mock.calls[0] as unknown as [
            string,
            { files: { file_id: number; document: { title: string } }[] },
        ];
        expect(body.files).toHaveLength(1);
        expect(body.files[0]).toMatchObject({
            file_id: 1,
            document: { title: 'Luce di ottobre' },
        });
    });

    it('waits for a file still being read, and asks for its state', () => {
        const reading = file({
            id: 4,
            name: 'in-lettura.pdf',
            reading_status: 'reading',
        });
        const visit = vi.spyOn(router, 'visit').mockImplementation(() => {});
        renderPage(Import, props([light, reading]));

        expect(
            screen.getByRole('button', { name: 'Import the 2 files' }),
        ).toBeDisabled();

        vi.advanceTimersByTime(2000);
        expect(visit).toHaveBeenCalledWith(
            expect.anything(),
            expect.objectContaining({ only: ['files'], preserveUrl: true }),
        );
    });

    it('does not ask when nothing is being read', () => {
        const visit = vi.spyOn(router, 'visit').mockImplementation(() => {});
        renderPage(Import, props([light, gas]));

        vi.advanceTimersByTime(6000);

        expect(visit).not.toHaveBeenCalled();
    });

    it('says on the row why a file did not save', () => {
        renderPage(
            Import,
            props([gas], { 2: 'This file could not be saved.' }),
        );

        expect(
            within(row('bolletta-gas.pdf')).getByText(
                'This file could not be saved.',
            ),
        ).toBeInTheDocument();
    });

    it('shows the errors of the server on the row of the file, under its fields, and opens the first', async () => {
        vi.spyOn(router, 'post').mockImplementation((_url, _data, visit) => {
            visit?.onError?.({
                'files.1.document.title': 'Write what the document is.',
                'files.1.document.notes': 'The notes are too long.',
            });
        });
        renderPage(Import, props([light, gas]));
        await settle();

        await fireEvent.click(
            screen.getByRole('button', { name: 'Import the 2 files' }),
        );

        expect(
            within(row('bolletta-gas.pdf')).getByText(
                'Write what the document is.',
            ),
        ).toBeInTheDocument();
        expect(
            within(row('bolletta-gas.pdf')).getByText('You are looking at it'),
        ).toBeInTheDocument();
        // Every error of the file is under its field
        expect(
            screen.getAllByText('Write what the document is.').length,
        ).toBeGreaterThan(1);
        expect(screen.getByText('The notes are too long.')).toBeInTheDocument();
        expect(
            screen.getByText('bolletta-gas.pdf needs a look'),
        ).toBeInTheDocument();
        expect(
            screen.queryByText('bolletta-luce.pdf needs a look'),
        ).not.toBeInTheDocument();
    });

    it('shows an error about the whole list of files on the page', async () => {
        vi.spyOn(router, 'post').mockImplementation((_url, _data, visit) => {
            visit?.onError?.({ files: 'Import at most 50 files at a time.' });
        });
        renderPage(Import, props([light, gas]));
        await settle();

        await fireEvent.click(
            screen.getByRole('button', { name: 'Import the 2 files' }),
        );

        expect(
            screen.getByText('Import at most 50 files at a time.'),
        ).toBeInTheDocument();
    });

    it('puts the errors on the right file when one was left out before', async () => {
        vi.spyOn(router, 'post').mockImplementation((_url, _data, visit) => {
            visit?.onError?.({
                'files.0.document.title': 'Write what the document is.',
            });
        });
        renderPage(Import, props([light, gas]));
        await settle();
        await fireEvent.click(
            screen.getByRole('button', { name: 'Do not import this file' }),
        );

        await fireEvent.click(
            screen.getByRole('button', { name: 'Import the file' }),
        );

        expect(
            within(row('bolletta-gas.pdf')).getByText(
                'Write what the document is.',
            ),
        ).toBeInTheDocument();
        expect(
            within(row('bolletta-luce.pdf')).queryByText(
                'Write what the document is.',
            ),
        ).not.toBeInTheDocument();
    });

    it('opens the file that failed when the page comes back with fewer files', async () => {
        const view = renderPage(Import, props([light, gas, warranty]));
        await fireEvent.click(row('garanzia.jpg'));

        await view.rerender(
            structuredClone(
                props([warranty], { 3: 'This file could not be saved.' }),
            ),
        );

        expect(
            within(row('garanzia.jpg')).getByText('You are looking at it'),
        ).toBeInTheDocument();
        expect(
            screen.getAllByText('This file could not be saved.').length,
        ).toBeGreaterThan(0);
    });

    it('stays on a file that is still there when others go', async () => {
        const view = renderPage(Import, props([light, gas, warranty]));
        await fireEvent.click(row('bolletta-gas.pdf'));

        await view.rerender(structuredClone(props([gas, warranty])));

        expect(
            within(row('bolletta-gas.pdf')).getByText('You are looking at it'),
        ).toBeInTheDocument();
    });

    it('keeps the person on the file open when a refresh only changes the state of the readings', async () => {
        const reading = file({
            ...gas,
            reading_status: 'reading',
            proposal: null,
        });
        const failures = { 3: 'This file could not be saved.' };
        const view = renderPage(
            Import,
            props([light, reading, warranty], failures),
        );
        await fireEvent.click(row('bolletta-luce.pdf'));

        // the poll: the same files, the second one read meanwhile
        await view.rerender(
            structuredClone(props([light, gas, warranty], failures)),
        );

        expect(
            within(row('bolletta-luce.pdf')).getByText('You are looking at it'),
        ).toBeInTheDocument();
    });
});

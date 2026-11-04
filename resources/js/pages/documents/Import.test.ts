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
import { renderPage, screenWidth, settle } from '@/test/page';
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
    presetLinks: { type: string; id: number }[] = [],
): Record<string, unknown> {
    return { files, failures, presetLinks, ...options };
}

/** The box that leaves the open file out of the import. */
function leaveOut(): HTMLElement {
    return screen.getByRole('checkbox', { name: 'Do not import this file' });
}

/** The row of a file in the list of the computer. */
function row(name: string): HTMLElement {
    return screen.getByRole('button', { name: new RegExp(name) });
}

beforeEach(() => {
    vi.useFakeTimers({ shouldAdvanceTime: true });
    // On a computer unless a test says otherwise: the list of the files and the button on top
    screenWidth(true);
});

afterEach(() => {
    vi.useRealTimers();
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
});

describe('Import', () => {
    it('lists the files with their state: the first is being looked at, the others to check', () => {
        renderPage(Import, props([light, gas, warranty]));

        expect(
            screen.getByRole('heading', { name: 'Import 3 files' }),
        ).toBeInTheDocument();
        expect(
            screen.getByText('Check them one by one, then import them together.'),
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

    it('moves on with Next file, and a file left behind is checked', async () => {
        renderPage(Import, props([light, gas, warranty]));

        await fireEvent.click(screen.getByRole('button', { name: 'Next file' }));

        expect(
            within(row('bolletta-luce.pdf')).getByText('Checked'),
        ).toBeInTheDocument();
        expect(
            within(row('bolletta-gas.pdf')).getByText('You are looking at it'),
        ).toBeInTheDocument();

        await fireEvent.click(screen.getByRole('button', { name: 'Next file' }));
        expect(
            screen.queryByRole('button', { name: 'Next file' }),
        ).not.toBeInTheDocument();
    });

    it('numbers the files in the row on top', () => {
        renderPage(Import, props([light, gas, warranty]));

        const list = screen.getByRole('list', { name: 'Files to import' });
        expect(within(list).getAllByRole('listitem')).toHaveLength(3);
        expect(
            within(row('bolletta-gas.pdf')).getByText('2'),
        ).toBeInTheDocument();
    });

    it.each(['failed', 'off'] as const)(
        'reads again a file whose reading was %s, keeping what was written',
        async (status) => {
            const post = vi.spyOn(router, 'post').mockImplementation(() => {});
            renderPage(
                Import,
                props([{ ...warranty, reading_status: status }, light]),
            );

            await fireEvent.click(
                screen.getByRole('button', { name: 'Read again' }),
            );

            expect(post).toHaveBeenCalledWith(
                `/files/${warranty.id}/read`,
                {},
                expect.objectContaining({
                    preserveScroll: true,
                    preserveState: true,
                }),
            );
        },
    );

    it('does not offer Read again for a file the reading skipped', () => {
        renderPage(
            Import,
            props([{ ...warranty, reading_status: 'skipped' }, light]),
        );

        expect(
            screen.queryByRole('button', { name: 'Read again' }),
        ).not.toBeInTheDocument();
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

    it('goes back to the files to sort, throwing away the changes', () => {
        renderPage(Import, props([light]));

        const back = screen.getByRole('link', { name: 'To sort' });
        expect(back).toHaveAttribute('href', '/to-sort');
        expect(back).toHaveAttribute('data-discard-changes');
    });

    it('from a card, closes to the card and imports going back to it', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        renderPage(Import, props([light], {}, [{ type: 'vehicle', id: 1 }]));
        await settle();

        const close = screen.getByRole('link', { name: 'Close' });
        expect(close).toHaveAttribute('href', '/cards/vehicle/1');
        expect(close).toHaveAttribute('data-discard-changes');

        await fireEvent.click(
            screen.getByRole('button', { name: 'Import the file' }),
        );
        expect(decodeURIComponent(`${post.mock.calls[0]?.[0] as string}`)).toBe(
            '/import?link=vehicle:1',
        );
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

        await fireEvent.click(leaveOut());

        expect(leaveOut()).toBeChecked();
        expect(
            within(row('bolletta-luce.pdf')).getByText('Left out'),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Import the 2 files' }),
        ).toBeInTheDocument();

        await fireEvent.click(leaveOut());

        expect(
            screen.getByRole('button', { name: 'Import the 3 files' }),
        ).toBeInTheDocument();
    });

    it('cannot import with every file left out', async () => {
        renderPage(Import, props([light]));

        await fireEvent.click(leaveOut());

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
        await fireEvent.click(screen.getByRole('button', { name: 'Next file' }));
        await fireEvent.click(leaveOut());
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
        await fireEvent.click(leaveOut());

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

    describe('on the phone', () => {
        beforeEach(() => {
            screenWidth(false);
        });

        it('shows one file at a time, with where it is and the bar of the files', () => {
            renderPage(Import, props([light, gas, warranty]));

            expect(
                screen.getByRole('heading', { name: 'Import 3 files' }),
            ).toBeInTheDocument();
            expect(screen.getByText('File 1 of 3')).toBeInTheDocument();
            expect(
                screen.queryByRole('list', { name: 'Files to import' }),
            ).not.toBeInTheDocument();
            expect(screen.getByRole('link', { name: 'Close' })).toHaveAttribute(
                'href',
                '/to-sort',
            );
        });

        it('moves with Previous file and Next file, and imports from the last file', async () => {
            renderPage(Import, props([light, gas, warranty]));
            const previous = screen.getByRole('button', {
                name: 'Previous file',
            });
            expect(previous).toBeDisabled();
            expect(
                screen.queryByRole('button', { name: 'Import the 3 files' }),
            ).not.toBeInTheDocument();

            await fireEvent.click(
                screen.getByRole('button', { name: 'Next file' }),
            );
            expect(screen.getByText('File 2 of 3')).toBeInTheDocument();

            await fireEvent.click(
                screen.getByRole('button', { name: 'Next file' }),
            );
            expect(
                screen.queryByRole('button', { name: 'Next file' }),
            ).not.toBeInTheDocument();
            expect(
                screen.getByRole('button', { name: 'Import the 3 files' }),
            ).toBeEnabled();

            await fireEvent.click(previous);
            expect(screen.getByText('File 2 of 3')).toBeInTheDocument();
        });

        it('says on the bar which file needs a look', () => {
            renderPage(
                Import,
                props([light, gas], { 2: 'This file could not be saved.' }),
            );

            expect(
                screen.getByText('bolletta-gas.pdf needs a look'),
            ).toBeInTheDocument();
            expect(
                screen.queryByText('bolletta-luce.pdf needs a look'),
            ).not.toBeInTheDocument();
        });
    });
});

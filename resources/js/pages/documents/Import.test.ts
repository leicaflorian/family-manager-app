import { getNode } from '@formkit/core';
import { router } from '@inertiajs/vue3';
import { fireEvent, screen, within } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import { hasChanges } from '@/lib/unsavedChanges';
import {
    enrolmentProposal,
    ferriProposal,
    pendingPayslip,
} from '@/test/fixtures';
import { choose, renderPage, screenWidth, settle } from '@/test/page';
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

/** The Studio Ferri invoice, read: its proposal comes in `proposals`. */
const ferri = file({
    id: 7,
    name: 'Telefono 2026-10-25 19.04.pdf',
    title: 'dentista Giulia',
    reading_status: 'done',
    proposal: null,
});

/** The props of the page with the Studio Ferri invoice and its proposal. */
function ferriProps(
    proposal: App.Data.Reading.ProposalData = ferriProposal,
    extra: Record<string, unknown> = {},
): Record<string, unknown> {
    return {
        ...props([ferri]),
        people: [{ value: '2', label: 'Giulia Bianchi', help: null }],
        proposals: { 7: proposal },
        expenseCategories: [
            { value: '12', label: 'Salute › Spese mediche', help: null },
        ],
        accounts: [{ value: '3', label: 'Carta Laura', help: null }],
        ...extra,
    };
}

/** The body of the last import sent. */
function sent(
    post: ReturnType<typeof vi.spyOn>,
): { file_id: number; also?: Record<string, unknown> }[] {
    const [, body] = post.mock.calls.at(-1) as unknown as [
        string,
        { files: { file_id: number; also?: Record<string, unknown> }[] },
    ];

    return body.files;
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
            screen.getByText(
                'Check them one by one, then import them together.',
            ),
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

        await fireEvent.click(
            screen.getByRole('button', { name: 'Next file' }),
        );

        expect(
            within(row('bolletta-luce.pdf')).getByText('Checked'),
        ).toBeInTheDocument();
        expect(
            within(row('bolletta-gas.pdf')).getByText('You are looking at it'),
        ).toBeInTheDocument();

        await fireEvent.click(
            screen.getByRole('button', { name: 'Next file' }),
        );
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
        await fireEvent.click(
            screen.getByRole('button', { name: 'Next file' }),
        );
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

    describe('what I do too', () => {
        it('proposes the expense, the event and the organization of the Studio Ferri invoice, ticked', () => {
            renderPage(Import, ferriProps());

            expect(
                screen.getByRole('heading', { name: 'What I do too' }),
            ).toBeInTheDocument();
            expect(
                screen.getByRole('checkbox', {
                    name: /Create the expense of 120,00 €/,
                }),
            ).toBeChecked();
            expect(
                screen.getByText('Spese mediche · Giulia Bianchi'),
            ).toBeInTheDocument();
            expect(
                screen.getByRole('checkbox', {
                    name: /Create the health event/,
                }),
            ).toBeChecked();
            expect(
                screen.getByText(
                    '«Visita dal dentista» · Giulia Bianchi · 22/10/2026',
                ),
            ).toBeInTheDocument();
            expect(
                screen.getByText("I'll remind you to book the checkup"),
            ).toBeInTheDocument();
            expect(
                screen.getByRole('checkbox', {
                    name: /Create the organization Studio Ferri/,
                }),
            ).toBeChecked();
        });

        it('offers already paid, with the date and the account, or to pay', () => {
            renderPage(Import, ferriProps());

            expect(
                screen.getByRole('radio', {
                    name: 'Already paid on 22/10/2026 with Carta Laura',
                }),
            ).toBeChecked();
            expect(
                screen.getByRole('radio', { name: 'To pay' }),
            ).not.toBeChecked();
        });

        it('sends the boxes ticked with the document', async () => {
            const post = vi.spyOn(router, 'post').mockImplementation(() => {});
            renderPage(Import, ferriProps());
            await settle();

            await fireEvent.click(
                screen.getByRole('button', { name: 'Import the file' }),
            );

            expect(sent(post)[0]).toMatchObject({
                file_id: 7,
                also: {
                    movement: true,
                    event: true,
                    records: ['organization'],
                    targets: { movement: 'auto', event: 'auto', records: {} },
                    policy: 'auto',
                },
            });
            expect(sent(post)[0]?.also).not.toHaveProperty('due');
        });

        it('leaves the expense out of what is sent when it is unticked, and that is a change to keep', async () => {
            const post = vi.spyOn(router, 'post').mockImplementation(() => {});
            vi.spyOn(router, 'reload').mockImplementation(() => {});
            renderPage(Import, ferriProps());
            await settle();
            expect(hasChanges()).toBe(false);

            await fireEvent.click(
                screen.getByRole('checkbox', {
                    name: /Create the expense of 120,00 €/,
                }),
            );
            expect(hasChanges()).toBe(true);
            await fireEvent.click(
                screen.getByRole('button', { name: 'Import the file' }),
            );

            expect(sent(post)[0]?.also).toMatchObject({ movement: false });
        });

        it('sends to pay when the person chooses it', async () => {
            const post = vi.spyOn(router, 'post').mockImplementation(() => {});
            vi.spyOn(router, 'reload').mockImplementation(() => {});
            renderPage(Import, ferriProps());
            await settle();

            await fireEvent.click(
                screen.getByRole('radio', { name: 'To pay' }),
            );
            await fireEvent.click(
                screen.getByRole('button', { name: 'Import the file' }),
            );

            expect(sent(post)[0]?.also).toMatchObject({ due: true });
        });

        it('asks the proposal of the file again after a change, with the choices', async () => {
            const reload = vi
                .spyOn(router, 'reload')
                .mockImplementation(() => {});
            renderPage(Import, ferriProps());
            await settle();
            expect(reload).not.toHaveBeenCalled();

            await fireEvent.click(
                screen.getByRole('radio', { name: 'To pay' }),
            );
            await vi.advanceTimersByTimeAsync(400);

            expect(reload).toHaveBeenCalledOnce();
            expect(reload).toHaveBeenCalledWith(
                expect.objectContaining({
                    only: ['proposals'],
                    preserveUrl: true,
                    data: {
                        check: expect.objectContaining({
                            file_id: 7,
                            also: expect.objectContaining({ due: true }),
                        }),
                    },
                }),
            );
        });

        it('links to another expense chosen by hand, and goes back to the proposal', async () => {
            const post = vi.spyOn(router, 'post').mockImplementation(() => {});
            vi.spyOn(router, 'reload').mockImplementation(() => {});
            renderPage(Import, ferriProps());
            await settle();

            await fireEvent.click(
                screen.getByRole('button', {
                    name: 'Link to an expense already registered…',
                }),
            );
            await choose(
                'The expense to link',
                'Dentista settembre · 15/09/2026 · 80,00 €',
            );
            await fireEvent.click(
                screen.getByRole('button', { name: 'Import the file' }),
            );
            expect(sent(post)[0]?.also).toMatchObject({
                targets: { movement: 41 },
            });

            await fireEvent.click(
                screen.getByRole('button', { name: 'Back to the proposal' }),
            );
            await fireEvent.click(
                screen.getByRole('button', { name: 'Import the file' }),
            );
            expect(sent(post)[0]?.also).toMatchObject({
                targets: { movement: 'auto' },
            });
        });

        it('creates a new card instead of the one found', async () => {
            const post = vi.spyOn(router, 'post').mockImplementation(() => {});
            vi.spyOn(router, 'reload').mockImplementation(() => {});
            const found = {
                ...ferriProposal,
                records: [
                    {
                        ...ferriProposal.records[0]!,
                        existing: { id: 5, title: 'Studio dentistico' },
                    },
                ],
            };
            renderPage(Import, ferriProps(found));
            await settle();

            expect(
                screen.getByRole('checkbox', {
                    name: /Link to the organization "Studio dentistico"/,
                }),
            ).toBeChecked();
            await fireEvent.click(
                screen.getByRole('button', { name: 'Create a new card' }),
            );
            await fireEvent.click(
                screen.getByRole('button', { name: 'Import the file' }),
            );

            expect(sent(post)[0]?.also).toMatchObject({
                targets: { records: { organization: 'new' } },
            });
        });

        it('shows an error of the import on the row of the file and in its box', async () => {
            vi.spyOn(router, 'post').mockImplementation(
                (_url, _data, visit) => {
                    visit?.onError?.({
                        'files.0.also.records.organization':
                            'To create the organization Studio Ferri the name is needed: enter it or untick it.',
                    });
                },
            );
            renderPage(Import, ferriProps());
            await settle();

            await fireEvent.click(
                screen.getByRole('button', { name: 'Import the file' }),
            );

            const message =
                'To create the organization Studio Ferri the name is needed: enter it or untick it.';
            expect(
                within(row('Telefono 2026-10-25 19.04.pdf')).getByText(message),
            ).toBeInTheDocument();
            const box = screen
                .getByRole('checkbox', {
                    name: /Create the organization Studio Ferri/,
                })
                .closest('label')!;
            expect(within(box).getByText(message)).toBeInTheDocument();
        });

        it('says what the files already imported say', () => {
            renderPage(
                Import,
                ferriProps(ferriProposal, {
                    notices: {
                        9: [
                            'Not linked to the policy 40/123456: the policy was not created. Link it from the document form.',
                        ],
                    },
                }),
            );

            expect(
                screen.getByText(
                    'Not linked to the policy 40/123456: the policy was not created. Link it from the document form.',
                ),
            ).toBeInTheDocument();
        });

        it('takes the policy of another file, and can leave it', async () => {
            const post = vi.spyOn(router, 'post').mockImplementation(() => {});
            vi.spyOn(router, 'reload').mockImplementation(() => {});
            const giver: App.Data.Reading.PolicyGiverData = {
                key: 'file:9',
                fileName: 'Contratto.pdf',
                number: '40/123456',
                creates: true,
                organizationId: null,
                organizationName: 'UnipolSai',
                personId: null,
                type: 'car',
            };
            renderPage(
                Import,
                ferriProps({
                    ...ferriProposal,
                    policy: { need: true, giver, many: false },
                }),
            );
            await settle();

            const box = screen.getByRole('checkbox', {
                name: /Link to the policy 40\/123456/,
            });
            expect(box).toBeChecked();
            expect(
                screen.getByText(
                    'The document is linked to the policy 40/123456, which the file Contratto.pdf creates.',
                ),
            ).toBeInTheDocument();

            await fireEvent.click(box);
            await fireEvent.click(
                screen.getByRole('button', { name: 'Import the file' }),
            );
            expect(sent(post)[0]?.also).toMatchObject({ policy: 'none' });
        });

        it('to pay with no deadline read, offers the empty deadline and sends the one written', async () => {
            const post = vi.spyOn(router, 'post').mockImplementation(() => {});
            vi.spyOn(router, 'reload').mockImplementation(() => {});
            renderPage(Import, ferriProps());
            await settle();

            await fireEvent.click(
                screen.getByRole('radio', { name: 'To pay' }),
            );
            const deadline = getNode('also-due-date-7');
            expect(deadline?.value ?? '').toBe('');
            // The calendar of the datepicker cannot be driven in jsdom: the day is set on the field
            await deadline?.input('2026-11-30');
            await settle();
            await fireEvent.click(
                screen.getByRole('button', { name: 'Import the file' }),
            );

            expect(sent(post)[0]?.also).toMatchObject({
                due: true,
                overrides: { movement: { due_date: '2026-11-30' } },
            });
        });

        it('starts the deadline from the one read', async () => {
            vi.spyOn(router, 'reload').mockImplementation(() => {});
            const due = {
                ...ferriProposal.movement!.due!,
                suggested: true,
                date: '2026-11-15',
            };
            renderPage(
                Import,
                ferriProps({
                    ...ferriProposal,
                    movement: { ...ferriProposal.movement!, due },
                }),
            );
            await settle();

            expect(getNode('also-due-date-7')?.value).toBe('2026-11-15');
        });

        it('asks a card for the fields it misses, and sends what the person chose', async () => {
            const post = vi.spyOn(router, 'post').mockImplementation(() => {});
            vi.spyOn(router, 'reload').mockImplementation(() => {});
            const policy: App.Data.Reading.RecordDraftData = {
                kind: 'policy',
                title: 'the policy 40/123456 · UnipolSai',
                values: { policy_number: '40/123456' },
                suggested: false,
                missing: ['the type'],
                existing: null,
                candidates: [],
                reminder: null,
                fields: [
                    {
                        column: 'type',
                        label: 'Policy type',
                        kind: 'dropdown',
                        required: true,
                        options: [
                            { value: 'car', label: 'Car', help: null },
                            { value: 'home', label: 'Home', help: null },
                        ],
                        show_when: {},
                        label_when: {},
                    },
                ],
            };
            renderPage(
                Import,
                ferriProps({ ...ferriProposal, records: [policy] }),
            );
            await settle();

            await fireEvent.click(
                screen.getByRole('checkbox', {
                    name: /Create the policy 40\/123456/,
                }),
            );
            expect(
                screen.getByText('To create it the type is missing.'),
            ).toBeInTheDocument();
            await choose('Policy type', 'Car');
            await fireEvent.click(
                screen.getByRole('button', { name: 'Import the file' }),
            );

            expect(sent(post)[0]?.also).toMatchObject({
                records: ['policy'],
                overrides: { records: { policy: { type: 'car' } } },
            });
        });

        it('asks a new health event for its title and type when the reading has none', async () => {
            const post = vi.spyOn(router, 'post').mockImplementation(() => {});
            vi.spyOn(router, 'reload').mockImplementation(() => {});
            const event = {
                ...ferriProposal.event!,
                values: {
                    ...ferriProposal.event!.values,
                    title: null,
                    type: null,
                },
                blocked:
                    'To create the health event the type, the title are missing.',
            };
            renderPage(
                Import,
                ferriProps(
                    { ...ferriProposal, event },
                    {
                        healthTypes: [
                            { value: 'visit', label: 'Visit', help: null },
                            { value: 'exam', label: 'Exam', help: null },
                        ],
                    },
                ),
            );
            await settle();

            await userEvent.type(
                screen.getByRole('textbox', { name: 'Title of the event' }),
                'Controllo',
            );
            await choose('Type of the event', 'Exam');
            await fireEvent.click(
                screen.getByRole('button', { name: 'Import the file' }),
            );

            expect(sent(post)[0]?.also).toMatchObject({
                overrides: { event: { title: 'Controllo', type: 'exam' } },
            });
        });

        it('does not ask the proposal of a file being imported', async () => {
            const reload = vi
                .spyOn(router, 'reload')
                .mockImplementation(() => {});
            vi.spyOn(router, 'post').mockImplementation(
                (_url, _data, visit) => {
                    visit?.onStart?.({} as never);
                },
            );
            renderPage(Import, ferriProps());
            await settle();

            await fireEvent.click(
                screen.getByRole('radio', { name: 'To pay' }),
            );
            await fireEvent.click(
                screen.getByRole('button', { name: 'Import the file' }),
            );
            await vi.advanceTimersByTimeAsync(1000);

            expect(reload).not.toHaveBeenCalled();
        });

        it('forgets the changes of a file saved when the page comes back without it', async () => {
            vi.spyOn(router, 'reload').mockImplementation(() => {});
            const other = file({
                id: 8,
                name: 'altro.pdf',
                reading_status: 'done',
                proposal: null,
            });
            const both = {
                ...ferriProps(),
                files: [ferri, other],
                proposals: {
                    7: ferriProposal,
                    8: { ...ferriProposal, fileId: 8 },
                },
            };
            const view = renderPage(Import, both);
            await settle();

            await fireEvent.click(
                screen.getByRole('radio', { name: 'To pay' }),
            );
            expect(hasChanges()).toBe(true);

            await view.rerender({ ...both, files: [other] });
            await settle();

            expect(hasChanges()).toBe(false);
        });

        it('offers to create the card for a paper that names none, and sends it when ticked', async () => {
            const post = vi.spyOn(router, 'post').mockImplementation(() => {});
            vi.spyOn(router, 'reload').mockImplementation(() => {});
            renderPage(Import, ferriProps(enrolmentProposal));
            await settle();

            const box = screen.getByRole('checkbox', {
                name: /Create the card/,
            });
            expect(box).not.toBeChecked();
            expect(
                screen.getByText(
                    'After the import New card opens, with this document already linked.',
                ),
            ).toBeInTheDocument();

            await fireEvent.click(box);
            await fireEvent.click(
                screen.getByRole('button', { name: 'Import the file' }),
            );

            expect(sent(post)[0]?.also).toMatchObject({ create_card: true });
        });

        it('offers the card of the documents saved while other files failed', () => {
            renderPage(Import, {
                ...props([light], { 1: 'This file could not be saved.' }),
                cardDocuments: '41,42',
            });

            expect(
                screen.getByText('The documents are saved.'),
            ).toBeInTheDocument();
            expect(
                screen.getByRole('link', { name: 'Create their card' }),
            ).toHaveAttribute('href', '/cards/create?document=41%2C42');
        });

        it('offers the card of one document saved, in the singular, and nothing without', () => {
            const view = renderPage(Import, {
                ...props([light]),
                cardDocuments: '41',
            });
            expect(
                screen.getByText('The document is saved.'),
            ).toBeInTheDocument();
            expect(
                screen.getByRole('link', { name: 'Create its card' }),
            ).toHaveAttribute('href', '/cards/create?document=41');
            view.unmount();

            renderPage(Import, props([light]));
            expect(
                screen.queryByRole('link', { name: /Create (its|their) card/ }),
            ).not.toBeInTheDocument();
        });

        it('does not offer to create the card for a paper that names one', () => {
            renderPage(Import, ferriProps());

            expect(
                screen.queryByRole('checkbox', { name: /Create the card/ }),
            ).not.toBeInTheDocument();
        });

        it('sends nothing more for a file without a proposal', async () => {
            const post = vi.spyOn(router, 'post').mockImplementation(() => {});
            renderPage(Import, props([light]));
            await settle();

            await fireEvent.click(
                screen.getByRole('button', { name: 'Import the file' }),
            );

            expect(sent(post)[0]).not.toHaveProperty('also');
            expect(
                screen.queryByRole('heading', { name: 'What I do too' }),
            ).not.toBeInTheDocument();
        });

        it('asks the proposal again when the reading of a file ends', async () => {
            const reload = vi
                .spyOn(router, 'reload')
                .mockImplementation(() => {});
            vi.spyOn(router, 'visit').mockImplementation(() => {});
            const view = renderPage(
                Import,
                ferriProps(ferriProposal, {
                    files: [{ ...ferri, reading_status: 'reading' }],
                }),
            );
            await settle();

            await view.rerender(ferriProps());
            await vi.advanceTimersByTimeAsync(400);

            expect(reload).toHaveBeenCalledWith(
                expect.objectContaining({ only: ['proposals'] }),
            );
        });
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

        it('puts what I do too under the form of the document, as the board Importa', () => {
            renderPage(Import, ferriProps());

            const field = screen.getAllByLabelText('What it is')[0]!;
            const heading = screen.getByRole('heading', {
                name: 'What I do too',
            });
            expect(
                field.compareDocumentPosition(heading) &
                    Node.DOCUMENT_POSITION_FOLLOWING,
            ).toBeTruthy();
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

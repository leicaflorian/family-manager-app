import { router } from '@inertiajs/vue3';
import { fireEvent, screen } from '@testing-library/vue';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import { pendingPayslip as payslip } from '@/test/fixtures';
import { renderPage, settle } from '@/test/page';
import Upload from './Upload.vue';

const shared = vi.hoisted(() => ({ to_sort_count: 0 }));

vi.mock('@inertiajs/vue3', async (original) => ({
    ...(await original<typeof import('@inertiajs/vue3')>()),
    Head: { render: () => null },
    usePage: () => ({ url: '/upload', props: { ...shared } }),
}));

const options = {
    categories: [],
    people: [],
    organizations: [],
    links: [],
    statuses: [{ value: 'active', label: 'Active', help: null }],
    tags: [],
    presetLinks: [],
};

const reading: App.Data.PendingFileData = {
    ...payslip,
    reading_status: 'reading',
    pages: 1,
};

/** What the server sends for a file read: the status and the proposal. */
const done: App.Data.PendingFileData = {
    ...payslip,
    reading_status: 'done',
    pages: 1,
    proposal: {
        title: 'Busta paga ottobre 2026',
        category_id: null,
        person_id: null,
        organization_id: null,
        date: '2026-10-25',
        expiry_date: null,
        links: [],
        headline: 'I found a payslip',
        found: [{ label: 'Date', value: '25/10/2026' }],
    },
};

beforeEach(() => {
    vi.useFakeTimers({ shouldAdvanceTime: true });
});

afterEach(() => {
    shared.to_sort_count = 0;
    vi.useRealTimers();
    vi.restoreAllMocks();
});

describe('Upload', () => {
    it('without files waiting, asks to choose them, with the accepted types', () => {
        renderPage(Upload, { pending: [], ...options });

        expect(
            screen.getByRole('heading', { name: 'Upload a document' }),
        ).toBeInTheDocument();
        expect(screen.getByLabelText('Choose the files')).toHaveAttribute(
            'accept',
            expect.stringContaining('.heic'),
        );
        expect(
            screen.getByText('PDF, JPG, PNG or HEIC, up to 25 MB each'),
        ).toBeInTheDocument();
    });

    it('sends the chosen files', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        renderPage(Upload, { pending: [], ...options });

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

    it('shows the form of the first file waiting, with its preview and how many are left', () => {
        renderPage(Upload, {
            pending: [
                payslip,
                {
                    ...payslip,
                    id: 13,
                    name: 'Scontrino.png',
                    title: 'Scontrino',
                },
            ],
            ...options,
        });

        expect(screen.getByText('1 of 2')).toBeInTheDocument();
        expect(screen.getByLabelText('What it is')).toHaveValue(
            'Busta paga novembre 2026',
        );
        expect(
            screen.getByRole('img', { name: 'Busta paga novembre 2026.pdf' }),
        ).toHaveAttribute('src', '/files/12/preview');
        expect(
            screen.getByRole('button', { name: 'Save' }),
        ).toBeInTheDocument();
    });

    it('leads to the files to sort when there are some', () => {
        shared.to_sort_count = 1;
        renderPage(Upload, { pending: [], ...options });

        expect(
            screen.getByRole('link', { name: '1 file to sort' }),
        ).toHaveAttribute('href', '/to-sort');
    });

    it('does not mention the files to sort when there are none', () => {
        renderPage(Upload, { pending: [], ...options });

        expect(
            screen.queryByRole('link', { name: /to sort/ }),
        ).not.toBeInTheDocument();
    });

    it('cancels to the files to sort without deleting the file', async () => {
        const visit = vi.spyOn(router, 'visit').mockImplementation(() => {});
        const destroy = vi.spyOn(router, 'delete').mockImplementation(() => {});
        renderPage(Upload, { pending: [payslip], ...options });

        await fireEvent.click(screen.getByRole('button', { name: 'Cancel' }));

        expect(visit).toHaveBeenCalledWith('/to-sort');
        expect(destroy).not.toHaveBeenCalled();
    });

    const vehicles = [
        {
            type: 'vehicle',
            label: 'Vehicles',
            options: [{ value: '1', label: 'Fiat Panda', help: null }],
        },
    ];

    it('starts the form with the card the file was uploaded from, even opened without it', () => {
        renderPage(Upload, {
            pending: [{ ...payslip, links: [{ type: 'vehicle', id: 1 }] }],
            ...options,
            links: vehicles,
        });

        expect(
            screen.getByRole('button', { name: 'Remove the link Fiat Panda' }),
        ).toBeInTheDocument();
    });

    it('does not link a file uploaded without a card to the card it is opened from', () => {
        renderPage(Upload, {
            pending: [payslip],
            ...options,
            links: vehicles,
            presetLinks: [{ type: 'vehicle', id: 1 }],
        });

        expect(
            screen.queryByRole('button', {
                name: 'Remove the link Fiat Panda',
            }),
        ).not.toBeInTheDocument();
    });

    it('keeps the card it comes from when it sends the files', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        renderPage(Upload, {
            pending: [],
            ...options,
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

    it('keeps the card it comes from when it files the document', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        renderPage(Upload, {
            pending: [payslip],
            ...options,
            presetLinks: [{ type: 'vehicle', id: 1 }],
        });

        await fireEvent.click(screen.getByRole('button', { name: 'Save' }));
        await settle();

        expect(decodeURIComponent(`${post.mock.calls[0]?.[0] as string}`)).toBe(
            '/documents?link=vehicle:1',
        );
    });

    it('closes to the card it comes from, or to the home page', () => {
        const result = renderPage(Upload, {
            pending: [],
            ...options,
            presetLinks: [{ type: 'vehicle', id: 1 }],
        });
        expect(screen.getByRole('link', { name: 'Close' })).toHaveAttribute(
            'href',
            '/cards/vehicle/1',
        );
        result.unmount();

        renderPage(Upload, { pending: [], ...options });
        expect(screen.getByRole('link', { name: 'Close' })).toHaveAttribute(
            'href',
            '/dashboard',
        );
    });

    describe('the reading', () => {
        it('shows the progress in place of the form while the file is read', () => {
            renderPage(Upload, { pending: [reading], ...options });

            expect(
                screen.getByText('Reading the document…'),
            ).toBeInTheDocument();
            expect(screen.queryByLabelText('What it is')).not.toBeVisible();
        });

        it('asks for the state every 2 seconds while a file is read, and stops when none is', async () => {
            // The poll goes through `reload`, which is a visit to the same page
            const reload = vi
                .spyOn(router, 'visit')
                .mockImplementation(() => {});
            const result = renderPage(Upload, {
                pending: [{ ...payslip, reading_status: 'queued' }],
                ...options,
            });

            await vi.advanceTimersByTimeAsync(2000);
            expect(reload).toHaveBeenCalledOnce();
            expect(reload).toHaveBeenCalledWith(
                expect.anything(),
                expect.objectContaining({
                    only: ['pending'],
                    preserveUrl: true,
                }),
            );

            await result.rerender({ pending: [done], ...options });
            await vi.advanceTimersByTimeAsync(6000);
            expect(reload).toHaveBeenCalledOnce();
        });

        it('stops asking when the page goes away', async () => {
            const reload = vi
                .spyOn(router, 'visit')
                .mockImplementation(() => {});
            const result = renderPage(Upload, {
                pending: [reading],
                ...options,
            });

            result.unmount();
            await vi.advanceTimersByTimeAsync(6000);

            expect(reload).not.toHaveBeenCalled();
        });

        it('does not ask when no file is being read', async () => {
            const reload = vi
                .spyOn(router, 'visit')
                .mockImplementation(() => {});
            renderPage(Upload, { pending: [payslip], ...options });

            await vi.advanceTimersByTimeAsync(6000);

            expect(reload).not.toHaveBeenCalled();
        });

        it('shows the sentence and the form with what was read when the reading is done', () => {
            renderPage(Upload, { pending: [done], ...options });

            expect(screen.getByText('I found a payslip')).toBeInTheDocument();
            expect(screen.getByLabelText('What it is')).toHaveValue(
                'Busta paga ottobre 2026',
            );
            expect(
                screen.queryByText('Reading the document…'),
            ).not.toBeInTheDocument();
            expect(
                screen.getByRole('button', { name: 'Okay, save' }),
            ).toBeInTheDocument();
        });

        it('keeps the title the person wrote while the file was read', async () => {
            const result = renderPage(Upload, {
                pending: [reading],
                ...options,
            });

            await fireEvent.update(
                screen.getByLabelText('What it is'),
                'Il mio titolo',
            );
            await settle();
            await result.rerender({ pending: [done], ...options });

            expect(screen.getByLabelText('What it is')).toHaveValue(
                'Il mio titolo',
            );
            expect(screen.getByLabelText('Date')).toHaveValue('25/10/2026');
        });

        it('puts the reading back with Restore the reading', async () => {
            renderPage(Upload, { pending: [done], ...options });

            await fireEvent.update(
                screen.getByLabelText('What it is'),
                'Altro titolo',
            );
            await settle();
            await fireEvent.click(
                screen.getByRole('button', { name: 'Restore the reading' }),
            );
            await settle();

            expect(screen.getByLabelText('What it is')).toHaveValue(
                'Busta paga ottobre 2026',
            );
        });

        it('shows why a reading failed, with Read again, and the empty form', async () => {
            const post = vi.spyOn(router, 'post').mockImplementation(() => {});
            renderPage(Upload, {
                pending: [
                    {
                        ...payslip,
                        reading_status: 'failed',
                        reading_error: 'The reading did not work.',
                    },
                ],
                ...options,
            });

            expect(
                screen.getByText('The reading did not work.'),
            ).toBeInTheDocument();
            expect(screen.getByLabelText('What it is')).toBeVisible();

            await fireEvent.click(
                screen.getByRole('button', { name: 'Read again' }),
            );

            expect(post).toHaveBeenCalledWith(
                '/files/12/read',
                {},
                expect.objectContaining({ preserveScroll: true }),
            );
        });

        it.each(['off', 'skipped'] as const)(
            'shows the message of a reading %s and the form to fill by hand',
            (status) => {
                renderPage(Upload, {
                    pending: [
                        {
                            ...payslip,
                            reading_status: status,
                            reading_error: `Message ${status}`,
                        },
                    ],
                    ...options,
                });

                expect(
                    screen.getByText(`Message ${status}`),
                ).toBeInTheDocument();
                expect(
                    screen.queryByRole('button', { name: 'Read again' }),
                ).not.toBeInTheDocument();
                expect(screen.getByLabelText('What it is')).toBeVisible();
            },
        );

        it('files the document with the file id when saved after a reading', async () => {
            const post = vi.spyOn(router, 'post').mockImplementation(() => {});
            renderPage(Upload, { pending: [done], ...options });

            await fireEvent.click(
                screen.getByRole('button', { name: 'Okay, save' }),
            );
            await settle();

            expect(post.mock.calls[0]?.[0]).toBe('/documents');
            expect(post.mock.calls[0]?.[1]).toMatchObject({
                file_id: 12,
                title: 'Busta paga ottobre 2026',
                date: '2026-10-25',
            });
        });
    });
});

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
import type { DocumentFormOptions } from '@/lib/documents';
import {
    documentDetail,
    documentFilterOptions,
    documentReading,
} from '@/test/fixtures';
import { renderPage, settle, withTrail } from '@/test/page';
import EditDocument from './EditDocument.vue';

const options: DocumentFormOptions = {
    ...documentFilterOptions,
    tags: [],
    links: [
        {
            type: 'employment',
            label: 'Employments',
            options: [
                { value: '3', label: 'Laura - Comune di Bologna', help: null },
            ],
        },
    ],
};

afterEach(() => {
    vi.restoreAllMocks();
});

describe('Edit a document', () => {
    it('writes the trail Documents > the document > Edit the document into the top bar', () => {
        const { state, provide } = withTrail();
        renderPage(
            EditDocument,
            { document: documentDetail, options, reading: documentReading },
            provide,
        );

        expect(state.pieces.value).toEqual([
            { label: 'Documents', href: '/documents' },
            { label: 'Busta paga di ottobre', href: '/documents/5' },
            { label: 'Edit the document' },
        ]);
    });

    it('starts from the document as it is', () => {
        renderPage(EditDocument, {
            document: documentDetail,
            options,
            reading: documentReading,
        });

        expect(
            screen.getByRole('heading', { name: 'Edit the document' }),
        ).toBeInTheDocument();
        expect(screen.getByLabelText('What it is')).toHaveValue(
            'Busta paga di ottobre',
        );
        expect(
            screen.getByRole('button', {
                name: 'Remove the link Laura - Comune di Bologna',
            }),
        ).toBeInTheDocument();
        expect(
            screen.getByText('Busta paga ottobre 2026.pdf'),
        ).toBeInTheDocument();
    });

    it('saves the change on the document', async () => {
        const put = vi.spyOn(router, 'put').mockImplementation(() => {});
        renderPage(EditDocument, {
            document: documentDetail,
            options,
            reading: documentReading,
        });

        await fireEvent.click(screen.getByRole('button', { name: 'Save' }));

        expect(put).toHaveBeenCalledOnce();
        expect(put.mock.calls[0]?.[0]).toBe('/documents/5');
        expect(put.mock.calls[0]?.[1]).toMatchObject({
            title: 'Busta paga di ottobre',
            category_id: 4,
            links: [{ type: 'employment', id: 3 }],
        });
    });

    it('shows the file beside the form, as the board', () => {
        renderPage(EditDocument, {
            document: documentDetail,
            options,
            reading: documentReading,
        });

        expect(
            screen.getByRole('img', { name: 'Busta paga ottobre 2026.pdf' }),
        ).toHaveAttribute('src', '/files/12/preview');
        expect(
            screen.queryByRole('link', { name: 'Close' }),
        ).not.toBeInTheDocument();
    });

    it('deletes the document from the bottom of the form, after asking', async () => {
        const destroy = vi.spyOn(router, 'delete').mockImplementation(() => {});
        renderPage(EditDocument, {
            document: documentDetail,
            options,
            reading: documentReading,
        });

        await fireEvent.click(
            screen.getByRole('button', { name: 'Delete the document' }),
        );
        expect(destroy).not.toHaveBeenCalled();
        const panel = await screen.findByRole('dialog');
        await fireEvent.click(
            within(panel).getByRole('button', { name: 'Delete the document' }),
        );

        expect(destroy).toHaveBeenCalledWith('/documents/5', expect.anything());
    });

    it('without the permission it does not offer to delete', () => {
        renderPage(EditDocument, {
            document: { ...documentDetail, can_delete: false },
            options,
            reading: documentReading,
        });

        expect(
            screen.queryByRole('button', { name: 'Delete the document' }),
        ).not.toBeInTheDocument();
    });

    it('shows the tags as chips, each one with its ×, and sends what is left', async () => {
        const put = vi.spyOn(router, 'put').mockImplementation(() => {});
        renderPage(EditDocument, {
            document: documentDetail,
            options,
            reading: documentReading,
        });

        const tags = screen
            .getByRole('combobox', { name: 'Tags' })
            .closest<HTMLElement>('[data-type]')!;
        expect(within(tags).getByText('salary')).toBeInTheDocument();
        await fireEvent.click(
            within(tags).getAllByRole('button', { name: 'Remove' })[0]!,
        );
        await settle();
        await fireEvent.click(screen.getByRole('button', { name: 'Save' }));

        expect(put.mock.calls[0]?.[1]).toMatchObject({ tags: ['2026'] });
    });
});

/** What the reading proposes: a new title, a date and an expiry. */
const proposal: App.Data.Reading.DocumentProposalData = {
    title: 'Busta paga novembre',
    category_id: null,
    person_id: null,
    organization_id: null,
    date: '2026-11-02',
    expiry_date: '2027-01-15',
    links: [],
    headline: 'I found a payslip',
    found: [],
};

describe('Fill in from the file', () => {
    beforeEach(() => {
        vi.useFakeTimers({ shouldAdvanceTime: true });
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    /** Mocks the post of *Fill in from the file*: it lands, as the server answers. */
    function postLands() {
        return vi
            .spyOn(router, 'post')
            .mockImplementation((_url, _data, options) => {
                options?.onSuccess?.({} as never);
            });
    }

    /** Renders the page with a reading. */
    function open(reading: Partial<App.Data.DocumentReadingData> = {}) {
        return renderPage(EditDocument, {
            document: documentDetail,
            options,
            reading: { ...documentReading, ...reading },
        });
    }

    it('asks the server to read the file of the document', async () => {
        const post = postLands();
        open();

        await fireEvent.click(
            screen.getByRole('button', { name: 'Fill in from the file' }),
        );

        expect(post).toHaveBeenCalledWith(
            '/files/12/read',
            {},
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('has no button, and says why, when the reading is off or used up', () => {
        open({
            can_read: false,
            unavailable: 'Automatic reading is not on.',
        });

        expect(
            screen.queryByRole('button', { name: 'Fill in from the file' }),
        ).not.toBeInTheDocument();
        expect(
            screen.getByText('Automatic reading is not on.'),
        ).toBeInTheDocument();
    });

    it('has no button and no sentence for who may not read', () => {
        open({ can_read: false, unavailable: null });

        expect(
            screen.queryByRole('button', { name: 'Fill in from the file' }),
        ).not.toBeInTheDocument();
    });

    it('shows the wait, keeps the form and asks only for the reading every 2 seconds', async () => {
        const reload = vi.spyOn(router, 'visit').mockImplementation(() => {});
        const result = open({ status: 'queued' });

        expect(screen.getByText('Reading the document…')).toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: 'Fill in from the file' }),
        ).not.toBeInTheDocument();
        expect(screen.getByLabelText('What it is')).toBeVisible();

        await vi.advanceTimersByTimeAsync(2000);
        expect(reload).toHaveBeenCalledOnce();
        expect(reload).toHaveBeenCalledWith(
            expect.anything(),
            expect.objectContaining({ only: ['reading'], preserveUrl: true }),
        );

        await result.rerender({
            reading: { ...documentReading, status: 'done', proposal },
        });
        await vi.advanceTimersByTimeAsync(6000);
        expect(reload).toHaveBeenCalledOnce();
    });

    it('does not ask when nothing is being read', async () => {
        const reload = vi.spyOn(router, 'visit').mockImplementation(() => {});
        open({ status: 'done', proposal });

        await vi.advanceTimersByTimeAsync(6000);

        expect(reload).not.toHaveBeenCalled();
    });

    it('fills only the empty fields once the reading is done', async () => {
        postLands();
        const result = open();

        await fireEvent.click(
            screen.getByRole('button', { name: 'Fill in from the file' }),
        );
        await result.rerender({
            reading: { ...documentReading, status: 'queued' },
        });
        await result.rerender({
            reading: { ...documentReading, status: 'done', proposal },
        });
        await settle();

        // Filled before: the title and the date stay; empty: the expiry is filled
        expect(screen.getByLabelText('What it is')).toHaveValue(
            'Busta paga di ottobre',
        );
        expect(screen.getByLabelText('Date')).toHaveValue('23/10/2026');
        expect(screen.getByLabelText('Expires on')).toHaveValue('15/01/2027');
        expect(screen.getByText('I found a payslip')).toBeInTheDocument();
    });

    it('does not fill anything from an old reading when the page opens', async () => {
        open({ status: 'done', proposal });
        await settle();

        expect(screen.getByLabelText('Expires on')).toHaveValue('DD/MM/YYYY');
        expect(screen.queryByText('I found a payslip')).not.toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Fill in from the file' }),
        ).toBeInTheDocument();
    });

    it('says why a reading failed and lets the person try again', async () => {
        postLands();
        const result = open();

        await fireEvent.click(
            screen.getByRole('button', { name: 'Fill in from the file' }),
        );
        await result.rerender({
            reading: {
                ...documentReading,
                status: 'failed',
                error: 'The reading did not work.',
            },
        });

        expect(
            screen.getByText('The reading did not work.'),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Fill in from the file' }),
        ).toBeInTheDocument();
    });

    it('writes nothing from an old reading when the request did not land', async () => {
        vi.spyOn(router, 'post').mockImplementation(() => {});
        const result = open({ status: 'done', proposal });

        await fireEvent.click(
            screen.getByRole('button', { name: 'Fill in from the file' }),
        );
        await result.rerender({
            reading: { ...documentReading, status: 'done', proposal },
        });
        await settle();

        expect(screen.getByLabelText('Expires on')).toHaveValue('DD/MM/YYYY');
        expect(screen.queryByText('I found a payslip')).not.toBeInTheDocument();
    });

    it('does not show the unavailable sentence beside the wait', () => {
        open({
            status: 'queued',
            can_read: false,
            unavailable: 'Automatic reading is not on.',
        });

        expect(screen.getByText('Reading the document…')).toBeInTheDocument();
        expect(
            screen.queryByText('Automatic reading is not on.'),
        ).not.toBeInTheDocument();
    });

    it('keeps what the person types in an empty field while the file is read', async () => {
        postLands();
        const result = open();

        await fireEvent.click(
            screen.getByRole('button', { name: 'Fill in from the file' }),
        );
        await result.rerender({
            reading: { ...documentReading, status: 'reading' },
        });
        await fireEvent.update(
            screen.getByLabelText('Expires on'),
            '01/02/2027',
        );
        await settle();
        await result.rerender({
            reading: { ...documentReading, status: 'done', proposal },
        });
        await settle();

        expect(screen.getByLabelText('Expires on')).toHaveValue('01/02/2027');
    });
});

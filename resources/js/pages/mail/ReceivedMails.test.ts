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
import { renderPage } from '@/test/page';
import ReceivedMails from './ReceivedMails.vue';

const ADDRESS = 'bianchi.k7m2x9@bollette.familymanager.it';

/** A row as the server sends it. */
function row(
    attributes: Partial<App.Data.ReceivedMailRowData>,
): App.Data.ReceivedMailRowData {
    return {
        id: 1,
        sender: 'Enel Energia',
        subject: 'La tua bolletta',
        received_at: '2026-10-25T09:41:00+01:00',
        outcome_label: '1 file saved',
        tone: 'success',
        ...attributes,
    };
}

const enel = row({ id: 6 });
const hera = row({
    id: 4,
    sender: 'Hera',
    subject: 'La tua fattura è disponibile',
    received_at: '2026-10-24T17:30:00+02:00',
    outcome_label: 'Notice saved',
    tone: 'info',
});
const tari = row({
    id: 3,
    sender: 'Comune di Bologna',
    subject: 'TARI 2026 - avviso di pagamento',
    received_at: '2026-10-23T11:05:00+02:00',
});
const promo = row({
    id: 2,
    sender: 'promo@offerte-energia.it',
    subject: null,
    received_at: '2026-10-22T08:03:00+02:00',
    outcome_label: 'Discarded',
    tone: 'neutral',
});

/** The TARI open, as the server sends it to Marco. */
function openTari(
    attributes: Partial<App.Data.ReceivedMailData> = {},
): App.Data.ReceivedMailData {
    return {
        row: tari,
        from: 'Comune di Bologna <tributi@comune.bologna.it>',
        recipient: ADDRESS,
        recipients: [ADDRESS],
        files: [
            {
                name: 'Avviso TARI 2026.pdf',
                state: 'to_sort',
                url: '/to-sort',
                document_title: null,
            },
        ],
        skipped: [{ name: 'logo_comune.png', reason: 'Too small' }],
        notes: [],
        gmail_code: null,
        has_text: true,
        has_both_texts: true,
        html_too_large: null,
        text_url: '/mail/3/text',
        can_save_as_notice: false,
        can_delete: true,
        pending_notice: null,
        ...attributes,
    };
}

const page = {
    address: ADDRESS,
    search: '',
    mails: [enel, hera, tari, promo],
    next: null,
    mail: null,
};

beforeEach(() => {
    vi.useFakeTimers({ shouldAdvanceTime: true });
});

afterEach(() => {
    vi.useRealTimers();
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
});

describe('ReceivedMails', () => {
    it('says the address the mails arrive to, under the title', () => {
        renderPage(ReceivedMails, page);

        expect(
            screen.getByRole('heading', { name: 'Received mail' }),
        ).toBeInTheDocument();
        expect(
            screen.getByText(`The mails that arrived at ${ADDRESS}`),
        ).toBeInTheDocument();
    });

    it('lists the mails newest first, each with who wrote, when, the subject and its label', () => {
        renderPage(ReceivedMails, {
            ...page,
            mails: [promo, tari, enel, hera],
        });

        const links = screen.getAllByRole('link', { name: /·|Discarded/ });
        expect(links.map((link) => link.getAttribute('href'))).toEqual([
            '/mail/6',
            '/mail/4',
            '/mail/3',
            '/mail/2',
        ]);
        const first = within(links[0]);
        expect(first.getByText('Enel Energia')).toBeInTheDocument();
        expect(first.getByText('25/10/2026 09:41')).toBeInTheDocument();
        expect(first.getByText('25/10 · 09:41')).toBeInTheDocument();
        expect(first.getByText('La tua bolletta')).toBeInTheDocument();
        expect(first.getByText('1 file saved')).toBeInTheDocument();
    });

    it('colours each label as the board does, and writes no subject when there is none', () => {
        renderPage(ReceivedMails, page);

        expect(screen.getByText('Notice saved').closest('span')).toHaveClass(
            'bg-action-soft',
        );
        expect(screen.getByText('Discarded').closest('span')).toHaveClass(
            'bg-divider',
        );
        expect(screen.getByText('no subject')).toBeInTheDocument();
    });

    it('says when no mail arrived yet, or none matches the search', () => {
        const view = renderPage(ReceivedMails, { ...page, mails: [] });
        expect(screen.getByText('No mail received yet')).toBeInTheDocument();
        view.unmount();

        renderPage(ReceivedMails, { ...page, mails: [], search: 'acqua' });
        expect(screen.getByText('No mail found')).toBeInTheDocument();
    });

    it('searches on the server while typing, from the first mails again', async () => {
        const get = vi.spyOn(router, 'get').mockImplementation(() => {});
        renderPage(ReceivedMails, page);

        await fireEvent.update(
            screen.getByRole('searchbox', { name: 'Search sender or subject' }),
            'hera',
        );
        await vi.advanceTimersByTimeAsync(400);

        expect(get).toHaveBeenCalledWith(
            expect.any(String),
            { search: 'hera' },
            expect.objectContaining({ reset: ['mails'] }),
        );
    });

    it('asks for the list again every 15 seconds', async () => {
        const visit = vi.spyOn(router, 'visit').mockImplementation(() => {});
        renderPage(ReceivedMails, page);

        await vi.advanceTimersByTimeAsync(15_000);

        expect(visit).toHaveBeenCalledWith(
            expect.any(String),
            expect.objectContaining({ only: ['mails'] }),
        );
    });

    it('asks for the list from the address of the list while a mail is open, so a mail deleted meanwhile is no error', async () => {
        const visit = vi.spyOn(router, 'visit').mockImplementation(() => {});
        renderPage(ReceivedMails, {
            ...page,
            search: 'TARI',
            mail: openTari(),
        });

        await vi.advanceTimersByTimeAsync(15_000);

        expect(visit).toHaveBeenCalledWith(
            '/mail?search=TARI',
            expect.objectContaining({
                only: ['mails'],
                preserveUrl: true,
                preserveState: true,
            }),
        );
    });

    it('puts the search of the page back in the box, as after Back', async () => {
        const view = renderPage(ReceivedMails, { ...page, search: 'hera' });

        await view.rerender({ ...page, search: 'enel' });

        expect(
            screen.getByRole('searchbox', { name: 'Search sender or subject' }),
        ).toHaveValue('enel');
    });

    it('lists two attachments with the same name, each once, without a warning', () => {
        const warn = vi.spyOn(console, 'warn');
        renderPage(ReceivedMails, {
            ...page,
            mail: openTari({
                files: [
                    {
                        name: 'image001.png',
                        state: 'gone',
                        url: null,
                        document_title: null,
                    },
                    {
                        name: 'image001.png',
                        state: 'gone',
                        url: null,
                        document_title: null,
                    },
                ],
                skipped: [
                    { name: 'logo.png', reason: 'Too small' },
                    { name: 'logo.png', reason: 'Too small' },
                    { name: 'strano.bin', reason: '' },
                ],
            }),
        });

        expect(screen.getAllByText('image001.png')).toHaveLength(2);
        expect(
            screen.getAllByText('Not saved: logo.png, too small.'),
        ).toHaveLength(2);
        // A reason not known is left out
        expect(screen.getByText('Not saved: strano.bin.')).toBeInTheDocument();
        expect(warn).not.toHaveBeenCalled();
    });

    it('opens a mail with who wrote, to whom, and what was done with it', () => {
        renderPage(ReceivedMails, { ...page, mail: openTari() });

        const mail = screen.getByRole('region', { name: 'The mail' });
        expect(
            within(mail).getByRole('heading', {
                name: 'TARI 2026 - avviso di pagamento',
            }),
        ).toBeInTheDocument();
        expect(
            within(mail).getByText(
                'Comune di Bologna <tributi@comune.bologna.it>',
            ),
        ).toBeInTheDocument();
        expect(within(mail).getByText(ADDRESS)).toBeInTheDocument();
        expect(within(mail).getByText('23/10/2026 11:05')).toBeInTheDocument();
        expect(
            within(mail).getByText('The images of the mail are not shown.'),
        ).toBeInTheDocument();
        expect(
            within(mail).getByText('What was done with it'),
        ).toBeInTheDocument();
        expect(
            within(mail).getByRole('link', {
                name: 'Avviso TARI 2026.pdf Saved · in To sort',
            }),
        ).toHaveAttribute('href', '/to-sort');
        expect(
            within(mail).getByText('Not saved: logo_comune.png, too small.'),
        ).toBeInTheDocument();
        expect(within(mail).getByTitle('Text of the mail')).toHaveAttribute(
            'src',
            '/mail/3/text',
        );
        // The open row is lit
        expect(
            screen.getByRole('link', { name: /TARI 2026 - avviso/ }),
        ).toHaveAttribute('aria-current', 'true');
    });

    it('leads a file to its document, and says when a file is no longer there and how to get it back', () => {
        renderPage(ReceivedMails, {
            ...page,
            mail: openTari({
                files: [
                    {
                        name: 'gas.pdf',
                        state: 'document',
                        url: '/documents/9',
                        document_title: 'Bolletta gas ottobre',
                    },
                    {
                        name: 'luce.pdf',
                        state: 'gone',
                        url: null,
                        document_title: null,
                    },
                ],
            }),
        });

        expect(
            screen.getByRole('link', {
                name: 'gas.pdf Became the document Bolletta gas ottobre',
            }),
        ).toHaveAttribute('href', '/documents/9');
        expect(screen.getByText('luce.pdf')).toBeInTheDocument();
        expect(screen.getByText('no longer here')).toBeInTheDocument();
        expect(
            screen.getByText(
                /A file that is no longer here cannot be recovered from here/,
            ),
        ).toBeInTheDocument();
    });

    it('shows the notes of the mail', () => {
        renderPage(ReceivedMails, {
            ...page,
            mail: openTari({ files: [], notes: ['discarded: advertisement'] }),
        });

        expect(
            screen.getByText('discarded: advertisement'),
        ).toBeInTheDocument();
    });

    it('lets an admin delete the mail, saying first that its notice goes too', async () => {
        const destroy = vi.spyOn(router, 'delete').mockImplementation(() => {});
        renderPage(ReceivedMails, {
            ...page,
            mail: openTari({
                pending_notice: 'Notice - La tua fattura è disponibile.txt',
            }),
        });

        await fireEvent.click(
            screen.getByRole('button', { name: 'Delete the mail' }),
        );
        expect(
            await screen.findByRole('heading', { name: 'Delete this mail?' }),
        ).toBeInTheDocument();
        expect(
            screen.getByText(
                /the notice «Notice - La tua fattura è disponibile.txt»/,
            ),
        ).toBeInTheDocument();
        await fireEvent.click(screen.getByRole('button', { name: 'Delete' }));

        expect(destroy).toHaveBeenCalledWith('/mail/3', expect.anything());
    });

    it('has no Delete for a member', () => {
        renderPage(ReceivedMails, {
            ...page,
            mail: openTari({ can_delete: false }),
        });

        expect(
            screen.queryByRole('button', { name: 'Delete the mail' }),
        ).not.toBeInTheDocument();
    });

    it('saves a discarded mail as a notice, and says why when it cannot', async () => {
        const post = vi
            .spyOn(router, 'post')
            .mockImplementation((_url, _data, options) => {
                options?.onError?.({
                    notice: 'The notice is already in To sort',
                });
            });
        renderPage(ReceivedMails, {
            ...page,
            mail: openTari({ files: [], can_save_as_notice: true }),
        });

        await fireEvent.click(
            screen.getByRole('button', { name: 'Save as notice' }),
        );

        expect(post).toHaveBeenCalledWith(
            '/mail/3/notice',
            {},
            expect.anything(),
        );
        expect(
            await screen.findByText('The notice is already in To sort'),
        ).toBeInTheDocument();
    });

    it('puts the code of Gmail in sight, to copy', async () => {
        const writeText = vi.fn().mockResolvedValue(undefined);
        vi.stubGlobal('navigator', { clipboard: { writeText } });
        renderPage(ReceivedMails, {
            ...page,
            mail: openTari({ files: [], skipped: [], gmail_code: '123456789' }),
        });

        expect(screen.getByText('123456789')).toBeInTheDocument();
        await fireEvent.click(screen.getByRole('button', { name: 'Copy' }));

        expect(writeText).toHaveBeenCalledWith('123456789');
        expect(await screen.findByText('Code copied')).toBeInTheDocument();
    });

    it('closes the mail back to the list', () => {
        renderPage(ReceivedMails, { ...page, mail: openTari() });

        expect(screen.getByRole('link', { name: 'Close' })).toHaveAttribute(
            'href',
            '/mail',
        );
    });
});

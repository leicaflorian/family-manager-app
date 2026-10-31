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
import { renderPage } from '@/test/page';
import DevCases from './DevCases.vue';

/**
 * The lines of the *Events* log, also while a panel is open over it.
 *
 * @returns The text of each line, in order.
 */
function logLines(): string[] {
    // `hidden`: an open panel hides the rest of the page from screen readers
    return within(screen.getByRole('log', { name: 'Events', hidden: true }))
        .queryAllByRole('listitem', { hidden: true })
        .map((line) => line.textContent?.trim() ?? '');
}

describe('DevCases', () => {
    let open: ReturnType<typeof vi.spyOn>;

    beforeEach(() => {
        open = vi.spyOn(XMLHttpRequest.prototype, 'open');
    });

    afterEach(() => {
        open.mockRestore();
    });

    it('shows a title for each case and the real component', () => {
        renderPage(DevCases, { name: 'FmRow' });

        for (const title of ['Link', 'Overdue', 'Button', 'Area dot']) {
            expect(
                screen.getByRole('heading', { name: title }),
            ).toBeInTheDocument();
        }
        expect(
            screen.getByRole('button', { name: 'Carica un documento' }),
        ).toBeInTheDocument();
        expect(screen.getByText('84,20 €')).toBeInTheDocument();
    });

    it('starts with an empty log, then writes each event with its arguments', async () => {
        renderPage(DevCases, { name: 'FmRow' });

        expect(
            within(screen.getByRole('log', { name: 'Events' })).getByText(
                'Nothing happened yet.',
            ),
        ).toBeInTheDocument();

        await fireEvent.click(
            screen.getByRole('button', { name: 'Carica un documento' }),
        );

        expect(logLines()).toEqual(['Button: click()']);
    });

    it('keeps the value of a v-model as the person types', async () => {
        renderPage(DevCases, { name: 'FmSearchField' });
        const [empty] = screen.getAllByRole('searchbox', {
            name: 'Search the documents',
        });

        await userEvent.type(empty!, 'tv');

        expect(empty).toHaveValue('tv');
        expect(logLines()).toEqual([
            'Empty: update:modelValue("t")',
            'Empty: update:modelValue("tv")',
        ]);
    });

    it('opens a panel with Open, and its own Cancel closes it', async () => {
        renderPage(DevCases, { name: 'FmConfirmDelete' });

        expect(
            screen.queryByRole('heading', { name: 'Delete this document?' }),
        ).not.toBeInTheDocument();

        await userEvent.click(screen.getByRole('button', { name: 'Open' }));
        await userEvent.click(
            await screen.findByRole('button', { name: 'Cancel' }),
        );

        expect(
            screen.queryByRole('heading', { name: 'Delete this document?' }),
        ).not.toBeInTheDocument();
        expect(logLines()).toEqual(['Delete a document: update:open(false)']);
    });

    it('opens the panel again after Cancel', async () => {
        renderPage(DevCases, { name: 'FmConfirmDelete' });

        await userEvent.click(screen.getByRole('button', { name: 'Open' }));
        await userEvent.click(
            await screen.findByRole('button', { name: 'Cancel' }),
        );
        await userEvent.click(screen.getByRole('button', { name: 'Open' }));

        expect(
            await screen.findByRole('heading', {
                name: 'Delete this document?',
            }),
        ).toBeInTheDocument();
    });

    it('has no Open button for a component without a panel', () => {
        renderPage(DevCases, { name: 'FmRow' });

        expect(
            screen.queryByRole('button', { name: 'Open' }),
        ).not.toBeInTheDocument();
    });

    it('stops the visit of a component and writes it in the log', async () => {
        renderPage(DevCases, { name: 'FmConfirmDelete' });

        await userEvent.click(screen.getByRole('button', { name: 'Open' }));
        await userEvent.click(
            await screen.findByRole('button', { name: 'Delete the document' }),
        );

        expect(open).not.toHaveBeenCalled();
        expect(logLines()).toEqual(['DELETE /documents/3']);
        // The visit never ends, so the panel stays open: its Cancel still closes it
        await userEvent.click(screen.getByRole('button', { name: 'Cancel' }));
        expect(
            screen.queryByRole('heading', { name: 'Delete this document?' }),
        ).not.toBeInTheDocument();
    });

    it('says so for a component without cases', () => {
        renderPage(DevCases, { name: 'FmWithoutCases' });

        expect(
            screen.getByText('No cases for this component yet.'),
        ).toBeInTheDocument();
    });
});

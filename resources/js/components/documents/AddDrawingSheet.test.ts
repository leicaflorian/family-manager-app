import { router } from '@inertiajs/vue3';
import { fireEvent, screen, within } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { answerLeave, leaveQuestion } from '@/lib/unsavedChanges';
import { pickFile, renderPage, settle } from '@/test/page';
import AddDrawingSheet from './AddDrawingSheet.vue';

const people = [
    { id: 1, name: 'Giulia' },
    { id: 2, name: 'Marco' },
];

afterEach(() => {
    vi.restoreAllMocks();
});

/**
 * Opens the panel.
 *
 * @returns the rendered panel
 */
async function open(): Promise<ReturnType<typeof renderPage>> {
    const rendered = renderPage(AddDrawingSheet, { people, open: true });
    await screen.findByRole('dialog', { name: 'Add a drawing' });

    return rendered;
}

/**
 * Chooses the child in the menu of the panel: the page body is not clickable while a panel is open in jsdom.
 *
 * @param name - the child
 */
async function chooseChild(name: string): Promise<void> {
    const user = userEvent.setup({ pointerEventsCheck: 0 });
    const field = screen.getByRole('combobox', { name: /Child/ });
    await user.click(field);
    const list = await within(
        field.closest<HTMLElement>('[data-type]')!,
    ).findByRole('listbox');
    await user.click(within(list).getByRole('option', { name }));
    await settle();
}

describe('AddDrawingSheet', () => {
    it('sends file, person, title and notes as multipart', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        await open();
        const file = new File(['x'], 'drago.jpg', { type: 'image/jpeg' });

        await pickFile('Photo or PDF of the drawing', file);
        await chooseChild('Giulia');
        await fireEvent.update(screen.getByLabelText(/Title/), 'Il drago');
        await fireEvent.update(
            screen.getByLabelText(/Description/),
            'A scuola',
        );
        await settle();
        await userEvent.click(screen.getByRole('button', { name: 'Save' }));

        expect(post).toHaveBeenCalledTimes(1);
        const [url, data, options] = post.mock.calls[0]!;
        expect(url).toBe('/documents/gallery');
        expect(data).toMatchObject({
            file,
            person_id: '1',
            title: 'Il drago',
            notes: 'A scuola',
        });
        expect(options).toMatchObject({ forceFormData: true });
    });

    it('does not send without a file', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        await open();

        await userEvent.click(screen.getByRole('button', { name: 'Save' }));

        expect(post).not.toHaveBeenCalled();
        expect(screen.getByText('Choose a photo or a PDF.')).toBeTruthy();
    });

    it('asks before closing with changes', async () => {
        await open();

        await pickFile(
            'Photo or PDF of the drawing',
            new File(['x'], 'drago.jpg', { type: 'image/jpeg' }),
        );
        await userEvent.click(screen.getByRole('button', { name: 'Close' }));

        expect(leaveQuestion.open).toBe(true);
        answerLeave(false);
    });
});

import { router } from '@inertiajs/vue3';
import { fireEvent, screen, within } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { newDocument } from '@/lib/documents';
import {
    choose,
    openMenu,
    pressSave,
    renderPage,
    search,
    settle,
    shown,
} from '@/test/page';
import FmDocumentForm from './FmDocumentForm.vue';

const options = {
    categories: [
        {
            id: 3,
            name: 'Work',
            parent_id: null,
            icon: 'briefcase',
            color: 'work',
            documents_count: 0,
            children: [
                {
                    id: 4,
                    name: 'Payslips',
                    parent_id: 3,
                    icon: null,
                    color: 'work',
                    documents_count: 0,
                    children: [],
                },
            ],
        },
    ],
    people: [{ value: '7', label: 'Laura Bianchi', help: null }],
    organizations: [{ value: '9', label: 'Comune di Bologna', help: null }],
    links: [
        {
            type: 'employment',
            label: 'Employments',
            options: [
                { value: '3', label: 'Laura - Comune di Bologna', help: null },
            ],
        },
    ],
    statuses: [
        { value: 'active', label: 'Active', help: null },
        { value: 'archived', label: 'Archived', help: null },
    ],
    tags: ['Bills', 'salary'],
};

const props = {
    action: { url: '/documents', method: 'post' as const },
    initial: newDocument('Busta paga novembre 2026'),
    extra: { file_id: 12 },
    options,
    submitLabel: 'Save',
};

afterEach(() => {
    vi.restoreAllMocks();
});

describe('FmDocumentForm', () => {
    it('proposes the title and offers categories with their path, people and organizations', async () => {
        renderPage(FmDocumentForm, props);

        expect(screen.getByLabelText('What it is')).toHaveValue(
            'Busta paga novembre 2026',
        );
        await choose('What kind it is', 'Work › Payslips');
        await choose('Whose it is', 'Laura Bianchi');
        await choose('Who it is from', 'Comune di Bologna');
        expect(screen.getByLabelText('Tags')).toBeInTheDocument();
    });

    it('lists «None» first, then the categories indented under their main one', async () => {
        renderPage(FmDocumentForm, props);

        expect(shown(await openMenu('What kind it is'))).toEqual([
            'None',
            'Work',
            'Payslips',
        ]);
    });

    it('shows the path of the chosen category in the field', async () => {
        renderPage(FmDocumentForm, props);

        await choose('What kind it is', 'Work › Payslips');

        expect(
            screen.getByRole('combobox', { name: 'What kind it is' }),
        ).toHaveValue('Work › Payslips');
    });

    it('searches the categories by their path, the people and the organizations', async () => {
        renderPage(FmDocumentForm, props);

        expect(shown(await search('What kind it is', 'work'))).toEqual([
            'Work',
            'Payslips',
        ]);
        expect(shown(await search('Whose it is', 'laura'))).toEqual([
            'Laura Bianchi',
        ]);
        expect(shown(await search('Who it is from', 'bologna'))).toEqual([
            'Comune di Bologna',
        ]);
    });

    it('opens a filled menu with every option, and filters only what the person types', async () => {
        renderPage(FmDocumentForm, {
            ...props,
            initial: {
                ...props.initial,
                category_id: '4',
                person_id: '7',
                organization_id: '9',
            },
        });

        expect(
            screen.getByRole('combobox', { name: 'What kind it is' }),
        ).toHaveValue('Work › Payslips');
        expect(shown(await openMenu('What kind it is'))).toEqual([
            'None',
            'Work',
            'Payslips',
        ]);
        expect(shown(await openMenu('Whose it is'))).toEqual([
            'None',
            'Laura Bianchi',
        ]);
        expect(shown(await openMenu('Who it is from'))).toEqual([
            'None',
            'Comune di Bologna',
        ]);
        expect(shown(await search('What kind it is', 'pay'))).toEqual([
            'Payslips',
        ]);
    });

    it('keeps the chosen category when the menu closes without a new choice', async () => {
        renderPage(FmDocumentForm, {
            ...props,
            initial: { ...props.initial, category_id: '4' },
        });

        await openMenu('What kind it is');
        await userEvent.keyboard('{Escape}');
        await settle();

        expect(
            screen.getByRole('combobox', { name: 'What kind it is' }),
        ).toHaveValue('Work › Payslips');
    });

    it('shows the dates the Italian way and sends them as the server reads them', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        renderPage(FmDocumentForm, {
            ...props,
            initial: { ...props.initial, expiry_date: '2027-03-31' },
        });

        expect(await screen.findByLabelText('Expires on')).toHaveValue(
            '31/03/2027',
        );
        await fireEvent.click(screen.getByRole('button', { name: 'Save' }));

        expect(post.mock.calls[0]?.[1]).toMatchObject({
            expiry_date: '2027-03-31',
        });
    });

    it('adds a chosen record to the links, and removes it with the ×', async () => {
        renderPage(FmDocumentForm, props);

        await choose('Link it to', 'Laura - Comune di Bologna');
        const chip = await screen.findByRole('button', {
            name: 'Remove the link Laura - Comune di Bologna',
        });
        await fireEvent.click(chip);

        expect(
            screen.queryByRole('button', { name: /Remove the link/ }),
        ).not.toBeInTheDocument();
    });

    it('asks before removing a link that is already saved, and keeps it on Cancel', async () => {
        renderPage(FmDocumentForm, {
            ...props,
            initial: {
                ...props.initial,
                links: [{ type: 'employment', id: 3 }],
            },
        });

        await fireEvent.click(
            screen.getByRole('button', {
                name: 'Remove the link Laura - Comune di Bologna',
            }),
        );
        expect(
            await screen.findByRole('heading', {
                name: 'Remove the link to Laura - Comune di Bologna?',
            }),
        ).toBeInTheDocument();
        await fireEvent.click(screen.getByRole('button', { name: 'Cancel' }));
        await settle();

        expect(
            screen.getByRole('button', {
                name: 'Remove the link Laura - Comune di Bologna',
            }),
        ).toBeInTheDocument();
    });

    it('removes a saved link once the person confirms', async () => {
        renderPage(FmDocumentForm, {
            ...props,
            initial: {
                ...props.initial,
                links: [{ type: 'employment', id: 3 }],
            },
        });

        await fireEvent.click(
            screen.getByRole('button', {
                name: 'Remove the link Laura - Comune di Bologna',
            }),
        );
        await fireEvent.click(
            await screen.findByRole('button', { name: 'Remove the link' }),
        );

        expect(
            screen.queryByRole('button', { name: /Remove the link Laura/ }),
        ).not.toBeInTheDocument();
    });

    it('proposes the tags already used, but not the ones the document has', async () => {
        renderPage(FmDocumentForm, {
            ...props,
            initial: { ...props.initial, tags: ['salary'] },
        });

        await userEvent.type(screen.getByLabelText('Tags'), 'l');
        const list = await screen.findByRole('listbox');

        expect(
            within(list).getByRole('option', { name: 'Bills' }),
        ).toBeInTheDocument();
        expect(
            within(list).queryByRole('option', { name: 'salary' }),
        ).not.toBeInTheDocument();
    });

    it('adds the highlighted tag with Tab, and what was written with Enter', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        renderPage(FmDocumentForm, props);

        const field = screen.getByLabelText('Tags');
        await userEvent.type(field, 'Bi');
        await screen.findByRole('option', { name: 'Bills' });
        await userEvent.keyboard('{ArrowDown}{Tab}');
        await settle();
        await userEvent.type(field, 'house{Enter}');
        await settle();
        await fireEvent.click(screen.getByRole('button', { name: 'Save' }));

        expect(post.mock.calls[0]?.[1]).toMatchObject({
            tags: ['Bills', 'house'],
        });
    });

    it('says how to add a new tag when no tag used before matches', async () => {
        renderPage(FmDocumentForm, props);

        await userEvent.type(screen.getByLabelText('Tags'), 'house');

        expect(
            await screen.findByText('Press Enter to add the tag'),
        ).toBeInTheDocument();
    });

    it('does not send without the title', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        renderPage(FmDocumentForm, props);

        const title = screen.getByLabelText('What it is');
        await fireEvent.update(title, '   ');
        await settle();
        await fireEvent.click(screen.getByRole('button', { name: 'Save' }));

        expect(
            await screen.findByText('Write what the document is.'),
        ).toBeInTheDocument();
        expect(post).not.toHaveBeenCalled();
    });

    it('sends the document with its file and its links', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        renderPage(FmDocumentForm, props);

        await choose('What kind it is', 'Work › Payslips');
        await choose('Link it to', 'Laura - Comune di Bologna');
        await fireEvent.click(screen.getByRole('button', { name: 'Save' }));

        expect(post).toHaveBeenCalledOnce();
        expect(post.mock.calls[0]?.[0]).toBe('/documents');
        expect(post.mock.calls[0]?.[1]).toMatchObject({
            file_id: 12,
            title: 'Busta paga novembre 2026',
            category_id: 4,
            person_id: null,
            links: [{ type: 'employment', id: 3 }],
        });
    });

    it('chooses the status among the segments, one always chosen', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        renderPage(FmDocumentForm, props);

        const active = await screen.findByRole('button', { name: 'Active' });
        expect(active).toHaveAttribute('aria-pressed', 'true');
        await fireEvent.click(active);
        expect(active).toHaveAttribute('aria-pressed', 'true');

        await fireEvent.click(screen.getByRole('button', { name: 'Archived' }));
        await settle();
        await fireEvent.click(screen.getByRole('button', { name: 'Save' }));

        expect(post.mock.calls[0]?.[1]).toMatchObject({ status: 'archived' });
    });

    it('saves with ⌘S too', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        renderPage(FmDocumentForm, props);

        pressSave();
        await settle();

        expect(post).toHaveBeenCalled();
    });
});

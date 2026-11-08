import { router } from '@inertiajs/vue3';
import { fireEvent, screen, within } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { newDocument } from '@/lib/documents';
import { hasChanges } from '@/lib/unsavedChanges';
import {
    choose,
    openMenu,
    pressSave,
    renderPage,
    search,
    settle,
    shown,
    tapCancel,
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

    it('searches the records to link by their name, keeping their groups', async () => {
        renderPage(FmDocumentForm, {
            ...props,
            options: {
                ...options,
                links: [
                    ...options.links,
                    {
                        type: 'utility',
                        label: 'Utilities',
                        options: [
                            { value: '5', label: 'Acqua - Hera', help: null },
                            { value: '6', label: 'Gas - Enel', help: null },
                        ],
                    },
                ],
            },
        });

        const list = await search('Link it to', 'hera');

        expect(shown(list)).toEqual(['Acqua - Hera']);
        expect(within(list).getByText('Utilities')).toBeInTheDocument();
        await userEvent.click(
            within(list).getByRole('option', { name: 'Acqua - Hera' }),
        );
        await settle();

        expect(
            await screen.findByRole('button', {
                name: 'Remove the link Acqua - Hera',
            }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('combobox', { name: 'Link it to' }),
        ).toHaveValue('');
    });

    it('empties the menu of the links after a choice, and offers a removed record again unselected', async () => {
        renderPage(FmDocumentForm, {
            ...props,
            options: {
                ...options,
                links: [
                    ...options.links,
                    {
                        type: 'utility',
                        label: 'Utilities',
                        options: [
                            { value: '5', label: 'Acqua - Hera', help: null },
                            { value: '6', label: 'Gas - Enel', help: null },
                        ],
                    },
                ],
            },
        });

        await choose('Link it to', 'Gas - Enel');
        await userEvent.keyboard('{Escape}');
        let list = await openMenu('Link it to');

        expect(shown(list)).toEqual([
            'Laura - Comune di Bologna',
            'Acqua - Hera',
        ]);
        expect(
            within(list).queryByRole('option', { selected: true }),
        ).not.toBeInTheDocument();
        await userEvent.keyboard('{Escape}');

        await userEvent.click(
            screen.getByRole('button', { name: 'Remove the link Gas - Enel' }),
        );
        await settle();

        expect(
            screen.getByRole('combobox', { name: 'Link it to' }),
        ).toHaveValue('');
        list = await openMenu('Link it to');
        expect(shown(list)).toEqual([
            'Laura - Comune di Bologna',
            'Acqua - Hera',
            'Gas - Enel',
        ]);
        expect(
            within(list).queryByRole('option', { selected: true }),
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

describe('Changes not saved', () => {
    it('has them once a field changes, and gives them up with Cancel', async () => {
        renderPage(FmDocumentForm, { ...props, cancelLabel: 'Cancel' });
        expect(hasChanges()).toBe(false);

        await fireEvent.update(
            screen.getByLabelText('What it is'),
            'Busta paga di novembre',
        );
        await settle();
        expect(hasChanges()).toBe(true);

        await tapCancel();
        expect(hasChanges()).toBe(false);
    });

    it('fills the fields the person has not touched with the proposal, and restores the reading', async () => {
        renderPage(FmDocumentForm, {
            ...props,
            proposal: {
                title: 'Luce ottobre',
                category_id: '4',
                links: [{ type: 'employment', id: 3 }],
            },
        });

        expect(screen.getByLabelText('What it is')).toHaveValue('Luce ottobre');
        expect(
            screen.getByRole('combobox', { name: 'What kind it is' }),
        ).toHaveValue('Work › Payslips');
        expect(
            screen.getByText('Laura - Comune di Bologna'),
        ).toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: 'Restore the reading' }),
        ).not.toBeInTheDocument();

        await userEvent.type(screen.getByLabelText('What it is'), ' bis');
        await userEvent.click(
            await screen.findByRole('button', { name: 'Restore the reading' }),
        );

        expect(screen.getByLabelText('What it is')).toHaveValue('Luce ottobre');
    });

    it('in empty mode keeps what is already written', () => {
        renderPage(FmDocumentForm, {
            ...props,
            fill: 'empty',
            proposal: { title: 'Luce ottobre', category_id: '4' },
        });

        expect(screen.getByLabelText('What it is')).toHaveValue(
            'Busta paga novembre 2026',
        );
        expect(
            screen.getByRole('combobox', { name: 'What kind it is' }),
        ).toHaveValue('Work › Payslips');
    });

    it('fills the form when the proposal arrives after it opened', async () => {
        const view = renderPage(FmDocumentForm, props);

        await view.rerender({ ...props, proposal: { title: 'Luce ottobre' } });

        expect(screen.getByLabelText('What it is')).toHaveValue('Luce ottobre');
    });

    it('counts a proposal as a change not saved when the form opens with it', async () => {
        renderPage(FmDocumentForm, {
            ...props,
            proposal: { title: 'Luce ottobre' },
        });
        expect(hasChanges()).toBe(true);
    });

    it('counts a proposal that arrives later as a change not saved', async () => {
        const view = renderPage(FmDocumentForm, props);
        expect(hasChanges()).toBe(false);

        await view.rerender({ ...props, proposal: { title: 'Luce ottobre' } });

        expect(hasChanges()).toBe(true);
    });

    it('does not count a proposal as a change when it is the start, and counts what the person writes after', async () => {
        const view = renderPage(FmDocumentForm, {
            ...props,
            proposalIsStart: true,
            proposal: { title: 'Luce ottobre' },
        });
        expect(hasChanges()).toBe(false);

        await view.rerender({
            ...props,
            proposalIsStart: true,
            proposal: { title: 'Luce ottobre', person_id: '7' },
        });
        expect(hasChanges()).toBe(false);

        await fireEvent.update(
            screen.getByLabelText('What it is'),
            'Luce ottobre 2',
        );
        await settle();
        expect(hasChanges()).toBe(true);
    });
});

describe('FmDocumentForm without an action', () => {
    const { action: _action, submitLabel: _label, ...bare } = props;

    it('has no buttons and tells the values when they change', async () => {
        const view = renderPage(FmDocumentForm, bare);

        expect(
            screen.queryByRole('button', { name: 'Save the document' }),
        ).not.toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: 'Cancel' }),
        ).not.toBeInTheDocument();

        await fireEvent.update(
            screen.getByLabelText('What it is'),
            'Luce ottobre',
        );
        await settle();

        const changes = view.emitted<[{ title: string }]>().change;
        expect(changes.at(-1)?.[0].title).toBe('Luce ottobre');
    });

    it('tells the values the proposal wrote', async () => {
        const view = renderPage(FmDocumentForm, {
            ...bare,
            proposal: { title: 'Luce ottobre', person_id: '7' },
        });
        await settle();

        const changes =
            view.emitted<[{ title: string; person_id: string }]>().change;
        expect(changes.at(-1)?.[0]).toMatchObject({
            title: 'Luce ottobre',
            person_id: '7',
        });
    });

    it('does not send anything on Enter', async () => {
        const post = vi.spyOn(router, 'visit');
        renderPage(FmDocumentForm, bare);

        await fireEvent.submit(
            screen.getByLabelText('What it is').closest('form')!,
        );

        expect(post).not.toHaveBeenCalled();
    });
});

describe('FmDocumentForm with errors from outside', () => {
    const { action: _action, submitLabel: _label, ...bare } = props;

    it('shows them under the fields and drops them when they go', async () => {
        const view = renderPage(FmDocumentForm, {
            ...bare,
            errors: {
                title: 'Write what the document is.',
                'tags.0': 'The tag is too long.',
            },
        });

        expect(
            screen.getByText('Write what the document is.'),
        ).toBeInTheDocument();
        expect(screen.getByText('The tag is too long.')).toBeInTheDocument();

        await view.rerender({ ...bare, errors: {} });

        expect(
            screen.queryByText('Write what the document is.'),
        ).not.toBeInTheDocument();
    });
});

describe('FmDocumentForm with fields needed below', () => {
    const { action: _action, submitLabel: _label, ...bare } = props;

    it('says under each field needed that the boxes below need it, and stops when they no longer do', async () => {
        const view = renderPage(FmDocumentForm, {
            ...bare,
            needed: ['date', 'person_id'],
        });

        expect(screen.getAllByText('Needed by What I do too.')).toHaveLength(2);

        await view.rerender({ ...bare, needed: [] });

        expect(
            screen.queryByText('Needed by What I do too.'),
        ).not.toBeInTheDocument();
    });
});

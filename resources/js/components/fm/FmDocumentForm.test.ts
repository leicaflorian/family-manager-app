import { router } from '@inertiajs/vue3';
import { fireEvent, screen } from '@testing-library/vue';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { newDocument } from '@/lib/documents';
import { renderPage, settle } from '@/test/page';
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
};

const props = {
    action: { url: '/documents', method: 'post' as const },
    initial: newDocument('Busta paga novembre 2026'),
    extra: { file_id: 12 },
    options,
    submitLabel: 'Save',
};

/** Chooses a value in a menu, as the person does. */
async function choose(label: string, value: string): Promise<void> {
    const select = screen.getByLabelText(label) as HTMLSelectElement;
    select.value = value;
    await fireEvent.input(select);
    await settle();
}

afterEach(() => {
    vi.restoreAllMocks();
});

describe('FmDocumentForm', () => {
    it('proposes the title and offers categories with their path, people and organizations', () => {
        renderPage(FmDocumentForm, props);

        expect(screen.getByLabelText('What it is')).toHaveValue(
            'Busta paga novembre 2026',
        );
        expect(
            screen.getByRole('option', { name: 'Work › Payslips' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('option', { name: 'Laura Bianchi' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('option', { name: 'Comune di Bologna' }),
        ).toBeInTheDocument();
        expect(screen.getByLabelText('Expires on')).toHaveAttribute(
            'type',
            'date',
        );
        expect(screen.getByLabelText('Tags')).toBeInTheDocument();
    });

    it('adds a chosen record to the links, and removes it with the ×', async () => {
        renderPage(FmDocumentForm, props);

        await choose('Link it to', 'employment:3');
        const chip = await screen.findByRole('button', {
            name: 'Remove the link Laura - Comune di Bologna',
        });
        await fireEvent.click(chip);

        expect(
            screen.queryByRole('button', { name: /Remove the link/ }),
        ).not.toBeInTheDocument();
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

        await choose('What kind it is', '4');
        await choose('Link it to', 'employment:3');
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
});

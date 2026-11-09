import { render, screen, waitFor } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vite-plus/test';
import { ref } from 'vue';
import FmSearchField from './FmSearchField.vue';

describe('FmSearchField', () => {
    it('is a search box named by its label, that writes what is typed', async () => {
        const text = ref('bolletta');
        render({
            components: { FmSearchField },
            setup: () => ({ text }),
            template:
                '<FmSearchField v-model="text" label="Search a document" />',
        });

        const box = screen.getByRole('searchbox', {
            name: 'Search a document',
        });
        expect(box).toHaveValue('bolletta');
        expect(box).toHaveAttribute('placeholder', 'Search a document');

        await userEvent.clear(box);
        await userEvent.type(box, 'gas');

        expect(text.value).toBe('gas');
    });

    it('small, it is as high as the buttons of a toolbar', () => {
        render(FmSearchField, {
            props: { label: 'Search a reminder', modelValue: '', small: true },
        });

        expect(
            screen.getByRole('searchbox', { name: 'Search a reminder' })
                .parentElement,
        ).toHaveClass('h-9', 'text-body-sm');
    });

    it('clearable, shows the clear button only with text; it empties the box and gives the focus back', async () => {
        const text = ref('');
        render({
            components: { FmSearchField },
            setup: () => ({ text }),
            template:
                '<FmSearchField v-model="text" label="Search everything" clearable />',
        });

        expect(
            screen.queryByRole('button', { name: 'Clear the search' }),
        ).toBeNull();

        const box = screen.getByRole('searchbox');
        await userEvent.type(box, 'Pan');
        await userEvent.click(
            screen.getByRole('button', { name: 'Clear the search' }),
        );

        expect(text.value).toBe('');
        expect(box).toHaveFocus();
    });

    it('shortcut, shows the key without text and hides it while typing', async () => {
        render({
            components: { FmSearchField },
            setup: () => ({ text: ref('') }),
            template:
                '<FmSearchField v-model="text" label="Search everything" shortcut />',
        });

        const box = screen.getByRole('searchbox');
        expect(box).toHaveAttribute('aria-keyshortcuts', 'Meta+K Control+K');
        expect(screen.getByText(/^(⌘K|Ctrl K)$/)).toBeInTheDocument();

        await userEvent.type(box, 'a');

        expect(screen.queryByText(/^(⌘K|Ctrl K)$/)).toBeNull();
    });

    it('shows the blue focus ring only on the whole-app box (clearable)', () => {
        const view = render(FmSearchField, {
            props: { label: 'Search everything', modelValue: '' },
        });
        const box = (): HTMLElement | null =>
            screen.getByRole('searchbox').parentElement;

        expect(box()?.className).not.toContain('focus-within:border-action');

        return view
            .rerender({ clearable: true })
            .then(() =>
                expect(box()?.className).toContain(
                    'focus-within:border-action',
                ),
            );
    });

    it('emits submit on Enter and escape on Esc', async () => {
        const view = render(FmSearchField, {
            props: { label: 'Search everything', modelValue: 'Pan' },
        });

        await userEvent.type(screen.getByRole('searchbox'), '{Enter}');
        await userEvent.keyboard('{Escape}');

        expect(view.emitted()).toHaveProperty('submit');
        expect(view.emitted()).toHaveProperty('escape');
    });

    it('autofocus, takes the focus when it appears', async () => {
        render(FmSearchField, {
            props: {
                label: 'Search everything',
                modelValue: '',
                autofocus: true,
            },
        });

        await waitFor(() =>
            expect(screen.getByRole('searchbox')).toHaveFocus(),
        );
    });
    it('with a menu, tells a screen reader which list it opens and whether it is open', async () => {
        const view = render(FmSearchField, {
            props: { label: 'Search everything', modelValue: 'Pan' },
        });

        expect(screen.getByRole('searchbox')).not.toHaveAttribute(
            'aria-controls',
        );
        expect(screen.getByRole('searchbox')).not.toHaveAttribute(
            'aria-expanded',
        );

        await view.rerender({ controls: 'results', expanded: true });

        expect(screen.getByRole('searchbox')).toHaveAttribute(
            'aria-controls',
            'results',
        );
        expect(screen.getByRole('searchbox')).toHaveAttribute(
            'aria-expanded',
            'true',
        );

        await view.rerender({ expanded: false });

        expect(screen.getByRole('searchbox')).toHaveAttribute(
            'aria-expanded',
            'false',
        );
    });
});

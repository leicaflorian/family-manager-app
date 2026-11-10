import { render, screen, waitFor } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vite-plus/test';
import { nextTick, ref } from 'vue';
import FmSearchField from './FmSearchField.vue';

describe('FmSearchField', () => {
    it('puts the input id on the input, not on the label around it', () => {
        render(FmSearchField, {
            props: {
                label: 'Search a document',
                modelValue: '',
                inputId: 'field',
            },
        });

        const box = screen.getByRole('searchbox');
        expect(box).toHaveAttribute('id', 'field');
        expect(box.closest('label')).not.toHaveAttribute('id');
    });

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

    it('clearable, stops at 100 characters, as the server does', () => {
        const view = render(FmSearchField, {
            props: {
                label: 'Search everything',
                modelValue: '',
                clearable: true,
            },
        });

        expect(screen.getByRole('searchbox')).toHaveAttribute(
            'maxlength',
            '100',
        );

        view.unmount();
        render(FmSearchField, {
            props: { label: 'Search the documents', modelValue: '' },
        });
        expect(screen.getByRole('searchbox')).not.toHaveAttribute('maxlength');
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

    it('writes the shortcut in the font of the app, small and with the corners of the board', () => {
        render(FmSearchField, {
            props: { modelValue: '', label: 'Search everything', shortcut: true },
        });

        // a <kbd> gets the monospaced font from Tailwind's preflight: the board keeps the font of the app
        expect(screen.getByText(/^(⌘K|Ctrl K)$/)).toHaveClass(
            'font-sans',
            'text-caption',
            'rounded-sm',
        );
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

    it('hides the clear button of the browser, so only its own ✕ shows', () => {
        render(FmSearchField, {
            props: { modelValue: 'Pan', label: 'Search', clearable: true },
        });

        expect(screen.getByRole('searchbox', { name: 'Search' })).toHaveClass(
            '[&::-webkit-search-cancel-button]:appearance-none',
            '[&::-webkit-search-decoration]:appearance-none',
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
    it('with a menu, is a combobox that says which list it opens, whether it is open and the chosen option', async () => {
        const view = render(FmSearchField, {
            props: { label: 'Search everything', modelValue: 'Pan' },
        });

        const plain = screen.getByRole('searchbox', {
            name: 'Search everything',
        });
        expect(plain).not.toHaveAttribute('aria-controls');
        expect(plain).not.toHaveAttribute('aria-expanded');
        expect(plain).not.toHaveAttribute('aria-autocomplete');

        await view.rerender({
            controls: 'results',
            expanded: true,
            activeDescendant: 'search-option-c1',
        });

        const box = screen.getByRole('combobox', {
            name: 'Search everything',
        });
        expect(box).toHaveAttribute('aria-controls', 'results');
        expect(box).toHaveAttribute('aria-expanded', 'true');
        expect(box).toHaveAttribute('aria-autocomplete', 'list');
        expect(box).toHaveAttribute(
            'aria-activedescendant',
            'search-option-c1',
        );

        await view.rerender({ expanded: false, activeDescendant: undefined });

        expect(box).toHaveAttribute('aria-expanded', 'false');
        expect(box).not.toHaveAttribute('aria-activedescendant');
    });

    it('with a menu, Esc does not empty the box: it only emits escape', async () => {
        const view = render(FmSearchField, {
            props: {
                label: 'Search everything',
                modelValue: 'Pan',
                controls: 'results',
                expanded: true,
            },
        });

        const esc = new KeyboardEvent('keydown', {
            key: 'Escape',
            bubbles: true,
            cancelable: true,
        });
        screen.getByRole('combobox').dispatchEvent(esc);
        await nextTick();

        expect(esc.defaultPrevented).toBe(true);
        expect(view.emitted('escape')).toHaveLength(1);
    });
});

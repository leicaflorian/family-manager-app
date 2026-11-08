import { Wallet } from '@lucide/vue';
import { fireEvent, render, screen } from '@testing-library/vue';
import { afterEach, describe, expect, it } from 'vite-plus/test';
import FmAlsoDoItem from './FmAlsoDoItem.vue';

const observer = globalThis.ResizeObserver;

afterEach(() => {
    globalThis.ResizeObserver = observer;
});

describe('FmAlsoDoItem', () => {
    it('watches the size of its content, to animate its height when the content changes', () => {
        const watched: Element[] = [];
        globalThis.ResizeObserver = class {
            /** @param element - the element to watch */
            observe(element: Element): void {
                watched.push(element);
            }
            /** Nothing to stop watching. */
            unobserve(): void {}
            /** Nothing to disconnect. */
            disconnect(): void {}
        } as unknown as typeof ResizeObserver;

        render(FmAlsoDoItem, { props: { label: 'Create the expense' } });

        expect(watched).toHaveLength(1);
        expect(watched[0]).toContainElement(
            screen.getByRole('checkbox', { name: 'Create the expense' }),
        );
    });

    it('is a checkbox with its text', () => {
        render(FmAlsoDoItem, {
            props: { label: 'Create the expense', modelValue: true },
        });

        expect(
            screen.getByRole('checkbox', { name: 'Create the expense' }),
        ).toBeChecked();
    });

    it('shows the fields only while it is ticked', async () => {
        render(FmAlsoDoItem, {
            props: { label: 'Create the expense', modelValue: false },
            slots: { default: '<p>Amount to correct</p>' },
        });

        expect(screen.queryByText('Amount to correct')).not.toBeInTheDocument();

        await fireEvent.click(screen.getByRole('checkbox'));

        expect(screen.getByText('Amount to correct')).toBeInTheDocument();
    });

    it('says the new value when it is ticked', async () => {
        const view = render(FmAlsoDoItem, {
            props: { label: 'Create the expense', modelValue: false },
        });

        await fireEvent.click(screen.getByRole('checkbox'));

        expect(view.emitted('update:modelValue')).toEqual([[true]]);
    });

    it('reads the hint and the warnings', () => {
        render(FmAlsoDoItem, {
            props: {
                label: 'Create the expense',
                modelValue: true,
                hint: 'It is the amount of the bill.',
                warnings: ['The date is missing.', 'The fee does not match.'],
            },
        });

        expect(
            screen.getByText('It is the amount of the bill.'),
        ).toBeInTheDocument();
        expect(screen.getByText('The date is missing.')).toBeInTheDocument();
        expect(screen.getByText('The fee does not match.')).toBeInTheDocument();
    });

    it('shows the actions', () => {
        render(FmAlsoDoItem, {
            props: { label: 'Link the policy', modelValue: true },
            slots: {
                actions: '<button type="button">Back to the proposal</button>',
            },
        });

        expect(
            screen.getByRole('button', { name: 'Back to the proposal' }),
        ).toBeInTheDocument();
    });

    it('cannot be ticked when it is disabled', () => {
        render(FmAlsoDoItem, {
            props: {
                label: 'Create the expense',
                modelValue: false,
                disabled: true,
            },
        });

        expect(screen.getByRole('checkbox')).toBeDisabled();
    });

    it('toggles when its text is clicked, not only the box', async () => {
        const view = render(FmAlsoDoItem, {
            props: { label: 'Create the expense', modelValue: false },
        });

        await fireEvent.click(screen.getByText('Create the expense'));

        expect(view.emitted('update:modelValue')).toEqual([[true]]);
    });

    it('does not toggle from its text when it is disabled', async () => {
        const view = render(FmAlsoDoItem, {
            props: {
                label: 'Create the expense',
                modelValue: false,
                disabled: true,
            },
        });

        await fireEvent.click(screen.getByText('Create the expense'));

        expect(view.emitted('update:modelValue')).toBeUndefined();
    });

    it('keeps its name as the text of the checkbox with an icon', () => {
        render(FmAlsoDoItem, {
            props: {
                label: 'Create the expense',
                modelValue: true,
                icon: Wallet,
                hint: 'Paid on 22/10',
            },
        });

        expect(
            screen.getByRole('checkbox', { name: /Create the expense/ }),
        ).toBeChecked();
    });

    it('hides the fields and the actions while it is unticked', () => {
        render(FmAlsoDoItem, {
            props: { label: 'Link the policy', modelValue: false },
            slots: {
                default: '<p>Fields</p>',
                actions: '<button type="button">Choose another</button>',
            },
        });

        expect(screen.queryByText('Fields')).not.toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: 'Choose another' }),
        ).not.toBeInTheDocument();
    });
});

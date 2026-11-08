/**
 * The stories of the menu with the search (FormKit `autocomplete`), for a long
 * list: a person, an organization, a category. Measures and states: see *Text*.
 */
import type { Meta } from '@storybook/vue3-vite';
import { userEvent, within } from 'storybook/test';
import FieldStory from './FieldStory.vue';
import { fieldStates } from './fieldStates';

const meta = {
    title: 'Fields/Autocomplete',
    component: FieldStory,
    parameters: {
        docs: {
            description: {
                component:
                    'The menu with the search, for a long list (a person, an organization, a category): the person opens it and writes to narrow the list. With a choice made it opens with every option, and it filters only when the person types. It has the measures of the other fields: see *Fields / Text*.',
            },
        },
    },
} satisfies Meta<typeof FieldStory>;

export default meta;

const people = [
    { value: 1, label: 'Marco Bianchi' },
    { value: 2, label: 'Laura Bianchi' },
    { value: 3, label: 'Sofia Bianchi' },
    { value: 4, label: 'Luca Bianchi' },
];

const organizations = [
    { value: 1, label: 'Banca Intesa' },
    { value: 2, label: 'Edison' },
    { value: 3, label: 'Comune di Milano' },
    { value: 4, label: 'Poste Italiane' },
];

const states = fieldStates(
    {
        type: 'autocomplete',
        name: 'person_id',
        label: 'Holder',
        options: people,
    },
    { value: 1 },
    'Choose a person.',
);

export const Empty = states.empty;
export const Filled = states.filled;
export const Focus = states.focus;
export const WithAnError = { name: 'With an error', ...states.error };
export const Disabled = states.disabled;

/** Open, with Marco Bianchi chosen. */
export const Open = {
    args: states.filled.args,
    parameters: { docs: { story: { inline: false, iframeHeight: 340 } } },
    play: async ({ canvasElement }: { canvasElement: HTMLElement }) => {
        await userEvent.click(
            await within(canvasElement).findByRole('combobox', {
                name: 'Intestatario',
            }),
        );
    },
};

/** While the person writes «ed»: only Edison is left. */
export const Searching = {
    args: {
        type: 'autocomplete',
        name: 'organization_id',
        label: 'Who it is from',
        options: organizations,
    },
    parameters: { docs: { story: { inline: false, iframeHeight: 260 } } },
    play: async ({ canvasElement }: { canvasElement: HTMLElement }) => {
        const box = await within(canvasElement).findByRole('combobox', {
            name: 'Da chi arriva',
        });
        await userEvent.click(box);
        await userEvent.type(box, 'ed');
    },
};

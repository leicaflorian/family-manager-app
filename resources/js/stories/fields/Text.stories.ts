/**
 * The stories of the text field (FormKit `text`, also `email`, `tel` and
 * `number`): empty, filled, in focus, with an error and disabled. The other
 * fields have the same five states and the measures written here.
 */
import type { Meta } from '@storybook/vue3-vite';
import FieldStory from './FieldStory.vue';
import { fieldStates } from './fieldStates';

const meta = {
    title: 'Fields/Text',
    component: FieldStory,
    parameters: {
        docs: {
            description: {
                component: `
The fields of the app are FormKit fields, with the classes of \`formkit.config.ts\`. These measures are the same for every field (the other pages of *Fields* point here):

- the box is **44 px** high (**52 px** in a panel from below), with corners of **10 px** (\`rounded-md\`) and a border of **1.5 px**;
- in focus the border becomes \`action\` and a soft ring surrounds the box;
- the label is above the box, **15 px** and bold; with a star when the field is required;
- the hint is below, **13 px** and grey (\`ink-soft\`);
- the error is below the box, in \`overdue\`, and the border turns \`overdue\` as well;
- a disabled field is faded, and a field never shows an error before the first *Save*.

The types \`text\`, \`email\`, \`tel\` and \`number\` look the same.`,
            },
        },
    },
} satisfies Meta<typeof FieldStory>;

export default meta;

const states = fieldStates(
    { type: 'text', name: 'title', label: 'What it is' },
    { value: 'Bolletta luce di ottobre' },
);

export const Empty = states.empty;
export const Filled = states.filled;
export const Focus = states.focus;
export const WithAnError = { name: 'With an error', ...states.error };
export const Disabled = states.disabled;

/** The hint sits under the box, in grey. */
export const WithAHint = {
    name: 'With a hint',
    args: {
        type: 'text',
        name: 'title',
        label: 'What it is',
        help: 'Optional: a card, a document or a person.',
    },
};

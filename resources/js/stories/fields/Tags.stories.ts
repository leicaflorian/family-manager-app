/**
 * The stories of the labels (FormKit `taglist`): grey chips in the box, which
 * grows with them. Measures and states: see *Text*.
 */
import type { Meta } from '@storybook/vue3-vite';
import FieldStory from './FieldStory.vue';
import { fieldStates } from './fieldStates';

const meta = {
    title: 'Fields/Tags',
    component: FieldStory,
    parameters: {
        docs: {
            description: {
                component:
                    'The labels of a card or a document: each one is a grey chip with a × to remove it, and the box grows with the chips. There is no list to open. It has the measures of the other fields: see *Fields / Text*.',
            },
        },
    },
} satisfies Meta<typeof FieldStory>;

export default meta;

// As the form of a document sets it: new tags can be written, a list can be empty
const states = fieldStates(
    {
        type: 'taglist',
        name: 'tags',
        label: 'Tags',
        options: [
            { value: 'casa', label: 'casa' },
            { value: 'bollette', label: 'bollette' },
        ],
        placeholder: 'Add…',
        help: 'Write a tag and press Enter, for example: salary',
        'allow-new-values': true,
    },
    { value: ['casa', 'bollette'] },
);

export const Empty = states.empty;
export const Filled = states.filled;
export const Focus = states.focus;
export const WithAnError = { name: 'With an error', ...states.error };
export const Disabled = states.disabled;

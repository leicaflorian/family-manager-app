/**
 * The stories of the date (FormKit `datepicker`): `15/10/2026` in the box,
 * `2026-10-15` in the form, and the open calendar. Measures and states: see *Text*.
 */
import type { Meta } from '@storybook/vue3-vite';
import { userEvent, within } from 'storybook/test';
import FieldStory from './FieldStory.vue';
import { fieldStates } from './fieldStates';

const meta = {
    title: 'Fields/Date',
    component: FieldStory,
    parameters: {
        docs: {
            description: {
                component:
                    'The date: the box shows `15/10/2026`, the form gets `2026-10-15`. The calendar opens under the box, weeks start on Monday and the chosen day is a blue circle. It has the measures of the other fields: see *Fields / Text*.',
            },
        },
    },
} satisfies Meta<typeof FieldStory>;

export default meta;

const states = fieldStates(
    { type: 'datepicker', name: 'expires_on', label: 'Expires on' },
    { value: '2026-10-15' },
);

export const Empty = states.empty;
export const Filled = states.filled;
export const Focus = states.focus;
export const WithAnError = { name: 'With an error', ...states.error };
export const Disabled = states.disabled;

/** The calendar open under the box, with the 15th chosen. */
export const Open = {
    args: states.filled.args,
    parameters: {
        // The calendar is taller than the story: in Docs it is drawn in its own frame
        docs: { story: { inline: false, iframeHeight: 460 } },
    },
    play: async ({ canvasElement }: { canvasElement: HTMLElement }) => {
        // The button with the calendar icon: FormKit names it «Change date, 15/10/2026»
        await userEvent.click(
            await within(canvasElement).findByRole('button', {
                name: /15\/10\/2026/,
            }),
        );
    },
};

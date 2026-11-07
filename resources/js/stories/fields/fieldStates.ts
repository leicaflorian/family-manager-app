/**
 * The five states every field is shown in (*Empty*, *Filled*, *Focus*,
 * *Error* and *Disabled*), built from the props of one `FieldStory`. A story
 * file exports them one by one, so Storybook finds them.
 */
import type { StoryObj } from '@storybook/vue3-vite';
import FieldStory from './FieldStory.vue';

/** The props of a field story. */
export type FieldProps = InstanceType<typeof FieldStory>['$props'];

/** A story of a field. */
export type FieldStoryObj = StoryObj<typeof FieldStory>;

/** The five states of a field. */
export type FieldStates = Record<
    'empty' | 'filled' | 'focus' | 'error' | 'disabled',
    FieldStoryObj
>;

/**
 * Builds the five states of a field.
 *
 * @param base - The props the field has in every state: type, name and label.
 * @param filled - The props of the filled state: the `value`.
 * @param error - The message shown under the field in the *Error* state.
 * @returns The five stories, as `{ empty, filled, focus, error, disabled }`.
 */
export function fieldStates(
    base: Pick<FieldProps, 'type' | 'name' | 'label'> &
        Partial<FieldProps> &
        Record<string, unknown>,
    filled: Partial<FieldProps>,
    error = 'This field is required.',
): FieldStates {
    return {
        empty: { args: { ...base } },
        filled: { args: { ...base, ...filled } },
        // The ring is `focus-within` on the box of the field: the addon shows it without touching the field
        focus: {
            args: { ...base, ...filled },
            parameters: { pseudo: { focusWithin: true } },
        },
        error: { args: { ...base, errors: [error] } },
        disabled: { args: { ...base, ...filled, disabled: true } },
    };
}

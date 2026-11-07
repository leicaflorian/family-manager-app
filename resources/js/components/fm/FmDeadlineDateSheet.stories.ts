/**
 * The stories of `FmDeadlineDateSheet`: the panel of
 * a passed deadline, opened with the `open` control, to correct its
 * date or to write the date of the renewal.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import { agendaEntries } from '@/test/fixtures';
import FmDeadlineDateSheet from './FmDeadlineDateSheet.vue';

const meta = {
    title: 'Panels/FmDeadlineDateSheet',
    component: FmDeadlineDateSheet,
} satisfies Meta<typeof FmDeadlineDateSheet>;

export default meta;

type Story = StoryObj<typeof meta>;

export const UpdateTheDate: Story = {
    name: 'Update the date',
    args: { open: false, entry: agendaEntries[0], renewed: false },
};

export const Renewed: Story = {
    args: { open: false, entry: agendaEntries[0], renewed: true },
};

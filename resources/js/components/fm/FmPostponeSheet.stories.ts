/**
 * The stories of `FmPostponeSheet`: the panel of
 * *Choose a date…* of a late reminder, opened with the `open` control.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import { toReminderItem } from '@/lib/reminders';
import { healthCardReminder } from '@/test/fixtures';
import FmPostponeSheet from './FmPostponeSheet.vue';

const meta = {
    title: 'Panels/FmPostponeSheet',
    component: FmPostponeSheet,
} satisfies Meta<typeof FmPostponeSheet>;

export default meta;

type Story = StoryObj<typeof meta>;

export const ChooseADate: Story = {
    name: 'Choose a date',
    args: { open: false, reminder: toReminderItem(healthCardReminder) },
};

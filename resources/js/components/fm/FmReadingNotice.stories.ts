/**
 * The stories of `FmReadingNotice`: a reading done, one that failed with *Read again*,
 * one that failed without a reason, and one that is off, with and without *Read again*.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import FmReadingNotice from './FmReadingNotice.vue';

const meta = {
    title: 'Basics/FmReadingNotice',
    component: FmReadingNotice,
} satisfies Meta<typeof FmReadingNotice>;

export default meta;

type Story = StoryObj<typeof meta>;

export const Done: Story = {
    args: { status: 'done', headline: 'I found an electricity bill' },
};

export const Failed: Story = {
    args: {
        status: 'failed',
        error: 'The file could not be read.',
        again: true,
    },
};

export const FailedWithoutAReason: Story = {
    name: 'Failed without a reason',
    args: { status: 'failed', error: null, again: true },
};

export const Off: Story = {
    args: { status: 'off', error: 'Automatic reading is not on.' },
};

export const OffWithReadAgain: Story = {
    name: 'Off, with Read again',
    args: {
        status: 'off',
        error: 'Automatic reading is not on.',
        again: true,
    },
};

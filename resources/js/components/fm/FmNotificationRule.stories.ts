/**
 * The stories of `FmNotificationRule`: days before
 * for everyone, the day itself for two people, days after only by email.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import type { RulePerson } from '@/lib/reminders';
import FmNotificationRule from './FmNotificationRule.vue';

/** The people with an account. */
const people: RulePerson[] = [
    { id: 1, name: 'Marco', initials: 'MB', photo: null },
    { id: 2, name: 'Laura', initials: 'LB', photo: null },
];

const meta = {
    title: 'Forms/FmNotificationRule',
    component: FmNotificationRule,
} satisfies Meta<typeof FmNotificationRule>;

export default meta;

type Story = StoryObj<typeof meta>;

export const DaysBefore: Story = {
    name: 'Days before',
    args: {
        people,
        modelValue: {
            days: 45,
            timing: 'before',
            channels: 'both',
            recipients: ['all'],
        },
    },
};

export const OnTheDayTwoPeople: Story = {
    name: 'On the day, two people',
    args: {
        people,
        modelValue: {
            days: 0,
            timing: 'before',
            channels: 'app',
            recipients: [1, 2],
        },
    },
};

export const DaysAfterEmail: Story = {
    name: 'Days after, email',
    args: {
        people,
        modelValue: {
            days: 7,
            timing: 'after',
            channels: 'email',
            recipients: ['all'],
        },
    },
};

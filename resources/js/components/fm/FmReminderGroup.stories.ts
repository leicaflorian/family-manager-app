/**
 * The stories of `FmReminderGroup`: a group with
 * its kinds, the default group, and one that whoever is looking cannot edit.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import type { ReminderGroupItem, RulePerson } from '@/lib/reminders';
import FmReminderGroup from './FmReminderGroup.vue';

/** The people with an account. */
const people: RulePerson[] = [
    { id: 1, name: 'Marco', initials: 'MB', photo: null },
    { id: 2, name: 'Laura', initials: 'LB', photo: null },
];

/** The group of the vehicles. */
const group: ReminderGroupItem = {
    name: 'Veicoli',
    is_default: false,
    kinds: ['Bollo', 'Revisione'],
    rules: [
        { days: 45, timing: 'before', channels: 'both', recipients: ['all'] },
        { days: 15, timing: 'before', channels: 'app', recipients: [1] },
        { days: 0, timing: 'before', channels: 'both', recipients: [1, 2] },
    ],
};

const meta = {
    title: 'Page sections/FmReminderGroup',
    component: FmReminderGroup,
} satisfies Meta<typeof FmReminderGroup>;

export default meta;

type Story = StoryObj<typeof meta>;

export const WithKinds: Story = {
    name: 'With kinds',
    args: { group, people, canEdit: true },
};

export const Default: Story = {
    args: {
        group: {
            ...group,
            name: 'Predefinito',
            is_default: true,
            kinds: [],
        },
        people,
        canEdit: true,
    },
};

export const CannotEdit: Story = {
    name: 'Cannot edit',
    args: { group, people, canEdit: false },
};

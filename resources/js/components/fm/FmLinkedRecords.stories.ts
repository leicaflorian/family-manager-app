/**
 * The stories of `FmLinkedRecords`: the records that
 * name a person or an organization, with one type, with several (a tab for
 * each) and with none.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import FmLinkedRecords from './FmLinkedRecords.vue';

/** The cards of the vehicles that name the person. */
const vehicles: App.Data.LinkGroupData = {
    type: 'vehicle',
    label: 'Vehicles',
    options: [
        { value: '1', label: 'Fiat Panda', help: null },
        { value: '2', label: 'Vespa Primavera', help: null },
    ],
};

/** The policies that name the person. */
const policies: App.Data.LinkGroupData = {
    type: 'insurance_policy',
    label: 'Insurance policies',
    options: [
        { value: '1', label: 'RC auto Panda - UnipolSai', help: null },
        { value: '2', label: 'Polizza vita Marco', help: null },
        { value: '3', label: 'Casa e famiglia - Generali', help: null },
    ],
};

const meta = {
    title: 'Page sections/FmLinkedRecords',
    component: FmLinkedRecords,
} satisfies Meta<typeof FmLinkedRecords>;

export default meta;

type Story = StoryObj<typeof meta>;

export const OneType: Story = {
    name: 'One type',
    args: { groups: [vehicles] },
};

export const SeveralTypes: Story = {
    name: 'Several types',
    args: { groups: [vehicles, policies] },
};

export const WithTheRemindersOfThePerson: Story = {
    name: 'With the reminders of the person',
    args: {
        groups: [vehicles, policies],
        reminders: { count: 2, href: '/reminders?person=1' },
    },
};

export const NoRecords: Story = {
    name: 'No records',
    args: { groups: [] },
};

/**
 * The stories of `FmCard`: with a text, with a
 * title and buttons, overdue (with a note under it), and holding a list of rows.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import { withSlots } from '@/stories/slots';
import { h } from 'vue';
import FmButton from './FmButton.vue';
import FmCard from './FmCard.vue';
import FmRow from './FmRow.vue';

const meta = {
    title: 'Basics/FmCard',
    component: FmCard,
} satisfies Meta<typeof FmCard>;

export default meta;

type Story = StoryObj<typeof meta>;

export const Plain: Story = {
    render: withSlots({ default: 'Some text inside the card.' }),
};

export const WithATitleAndAButton: Story = {
    name: 'With a title and a button',
    args: { title: 'Payments' },
    render: withSlots({
        default: 'Nothing to pay this month.',
        actions: () => h(FmButton, { variant: 'text' }, () => 'See all'),
    }),
};

export const OverdueTitle: Story = {
    name: 'Overdue title',
    args: { title: 'To sort out', tone: 'overdue' },
    render: withSlots({ default: 'Deadlines already passed.' }),
};

export const OverdueListWithANote: Story = {
    name: 'Overdue list with a note',
    args: { title: 'To sort out', tone: 'overdue', list: true },
    render: withSlots({
        default: () =>
            h(FmRow, {
                title: 'Tessera sanitaria di Giulia',
                subtitle: 'Scaduta il 20/10/2026',
                href: '/documents/4',
            }),
        note: 'A passed deadline stays here for three months, until you renew it or fix the date.',
    }),
};

export const ListOfRows: Story = {
    name: 'List of rows',
    args: { title: 'Next deadlines', list: true },
    render: withSlots({
        default: () => [
            h(FmRow, {
                title: 'Bolletta luce ottobre',
                subtitle: 'Scade il 15/11/2026',
                href: '/cards/1',
            }),
            h(FmRow, {
                title: 'Assicurazione auto',
                subtitle: 'Scade il 03/02/2027',
                href: '/cards/2',
            }),
        ],
    }),
};

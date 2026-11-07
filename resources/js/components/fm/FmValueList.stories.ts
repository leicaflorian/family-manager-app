/**
 * The stories of `FmValueList`: plain values, values
 * that link to a person or an organization and the same links in a new tab.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import FmValueList from './FmValueList.vue';

/** Values of a policy, with the person and the insurer as links. */
const values: App.Data.CardValueData[] = [
    { label: 'Type', text: 'Auto', href: null },
    { label: 'Policyholder', text: 'Marco Bianchi', href: '/people/1' },
    { label: 'Insurer', text: 'UnipolSai', href: '/organizations/4' },
];

const meta = {
    title: 'Rows/FmValueList',
    component: FmValueList,
} satisfies Meta<typeof FmValueList>;

export default meta;

type Story = StoryObj<typeof meta>;

export const Plain: Story = {
    args: {
        values: [
            { label: 'Type', text: 'Auto', href: null },
            { label: 'Plate', text: 'FG 123 AB', href: null },
        ],
    },
};

export const WithLinks: Story = {
    name: 'With links',
    args: { values },
};

export const LinksInANewTab: Story = {
    name: 'Links in a new tab',
    args: { values, newTab: true },
};

/**
 * The stories of `FmFamilyViews`: the *People* view
 * and the *Organizations* view with a button.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import { withSlots } from '@/stories/slots';
import { h } from 'vue';
import { familySummary } from '@/test/fixtures';
import FmButton from './FmButton.vue';
import FmFamilyViews from './FmFamilyViews.vue';

const meta = {
    title: 'Navigation/FmFamilyViews',
    component: FmFamilyViews,
} satisfies Meta<typeof FmFamilyViews>;

export default meta;

type Story = StoryObj<typeof meta>;

export const People: Story = {
    args: { family: familySummary, current: 'people' },
};

export const OrganizationsWithAButton: Story = {
    name: 'Organizations, with a button',
    args: { family: familySummary, current: 'organizations' },
    render: withSlots({
        actions: () =>
            h(
                FmButton,
                { variant: 'soft', href: '/organizations/create' },
                () => 'Add',
            ),
    }),
};

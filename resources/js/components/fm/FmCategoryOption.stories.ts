/**
 * The stories of `FmCategoryOption`: a main
 * category, a subcategory indented under it and a plain choice such as *All*.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import FmCategoryOption from './FmCategoryOption.vue';

const meta = {
    title: 'Forms/FmCategoryOption',
    component: FmCategoryOption,
} satisfies Meta<typeof FmCategoryOption>;

export default meta;

type Story = StoryObj<typeof meta>;

export const MainCategory: Story = {
    name: 'Main category',
    args: { option: { label: 'Lavoro', name: 'Lavoro', depth: 0 } },
};

export const Subcategory: Story = {
    args: {
        option: {
            label: 'Lavoro › Buste paga',
            name: 'Buste paga',
            depth: 1,
        },
    },
};

export const PlainChoice: Story = {
    name: 'Plain choice',
    args: { option: { label: 'All' } },
};

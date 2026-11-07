/**
 * The stories of `FmCategoryTree`: the tree of the
 * document categories with their counts, one that can be tapped to edit and
 * an empty one.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import type { DefineComponent } from 'vue';
import type { ComponentProps } from 'vue-component-type-helpers';
import FmCategoryTree from './FmCategoryTree.vue';

/** The categories of the Bianchis' documents, with the number of documents of each. */
const categories: App.Data.CategoryData[] = [
    {
        id: 1,
        name: 'Casa e utenze',
        parent_id: null,
        icon: 'home',
        color: 'home',
        documents_count: 5,
        children: [
            {
                id: 2,
                name: 'Luce',
                parent_id: 1,
                icon: null,
                color: 'home',
                documents_count: 3,
                children: [],
            },
            {
                id: 5,
                name: 'Altro',
                parent_id: 1,
                icon: null,
                color: 'home',
                documents_count: 2,
                children: [],
            },
        ],
    },
    {
        id: 3,
        name: 'Lavoro',
        parent_id: null,
        icon: 'briefcase',
        color: 'work',
        documents_count: 0,
        children: [],
    },
];

/**
 * The tree is a generic component, which Storybook cannot type: here it is
 * the tree of the categories the server sends.
 */
const CategoryTree = FmCategoryTree as unknown as DefineComponent<
    ComponentProps<typeof FmCategoryTree<App.Data.CategoryData>>
>;

const meta = {
    title: 'Forms/FmCategoryTree',
    component: CategoryTree,
} satisfies Meta<typeof CategoryTree>;

export default meta;

type Story = StoryObj<typeof meta>;

export const Tree: Story = {
    args: { categories },
};

export const Selectable: Story = {
    args: { categories, selectable: true },
};

export const Empty: Story = {
    args: { categories: [] },
};

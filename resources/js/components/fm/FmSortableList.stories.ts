/**
 * The stories of `FmSortableList`: a list to reorder, and the same list
 * turned off.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import type { DefineComponent } from 'vue';
import type { ComponentProps } from 'vue-component-type-helpers';
import FmSortableList from './FmSortableList.vue';

/** An item of the stories. */
type Item = { key: string; name: string };

/**
 * The list is a generic component, which Storybook cannot type: here it is
 * typed for the items of the stories.
 */
const SortableList = FmSortableList as unknown as DefineComponent<
    ComponentProps<typeof FmSortableList<Item>>
>;

const meta = {
    title: 'Basics/FmSortableList',
    component: SortableList,
    args: {
        label: 'Areas',
        items: [
            { key: 'home', name: 'Home' },
            { key: 'health', name: 'Health' },
            { key: 'money', name: 'Money' },
        ],
    },
} satisfies Meta<typeof SortableList>;

export default meta;

type Story = StoryObj<typeof meta>;

/** The `render` of a story: a row with the name of the item and its two moves. */
const row: Story['render'] = (args) => ({
    components: { FmSortableList: SortableList },
    setup: () => ({ args }),
    template: `
        <FmSortableList v-bind="args">
            <template #item="{ item, moveUp, moveDown }">
                <span>{{ item.name }}</span>
                <button type="button" @click="moveUp">Up</button>
                <button type="button" @click="moveDown">Down</button>
            </template>
        </FmSortableList>`,
});

export const Default: Story = { render: row };

export const Disabled: Story = {
    args: { ...meta.args, disabled: true },
    render: row,
};

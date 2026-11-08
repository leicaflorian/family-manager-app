/**
 * The stories of `FmMenuItem`: a plain entry, with an icon, as a link, opened
 * in a new tab and the red one. An entry only works inside a menu, so each
 * story draws it in an `FmMenu`; open the ⋯ to see it.
 */
import { Download, ExternalLink, Pencil, Trash2 } from '@lucide/vue';
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import { h } from 'vue';
import FmMenu from './FmMenu.vue';
import FmMenuItem from './FmMenuItem.vue';

/**
 * The `render` of a story: the entry inside an `FmMenu`, with its text.
 *
 * @param label - The text of the entry.
 * @returns The `render` to give to the story.
 */
const inMenu =
    (label: string): Story['render'] =>
    (args) => ({
        setup: () => () =>
            h(FmMenu, null, () => h(FmMenuItem, { ...args }, () => label)),
    });

const meta = {
    title: 'Navigation/FmMenuItem',
    component: FmMenuItem,
} satisfies Meta<typeof FmMenuItem>;

export default meta;

type Story = StoryObj<typeof meta>;

export const Plain: Story = {
    render: inMenu('Rename'),
};

export const WithAnIcon: Story = {
    name: 'With an icon',
    args: { icon: Pencil },
    render: inMenu('Edit'),
};

export const Link: Story = {
    args: { icon: Download, href: '/documents/5/file', download: true },
    render: inMenu('Download'),
};

export const NewTab: Story = {
    name: 'New tab',
    args: { icon: ExternalLink, href: '/documents/5/file', external: true },
    render: inMenu('Open in a new tab'),
};

export const Danger: Story = {
    args: { icon: Trash2, danger: true },
    render: inMenu('Delete'),
};

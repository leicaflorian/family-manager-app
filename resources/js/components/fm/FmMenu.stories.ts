/**
 * The stories of `FmMenu`: the ⋯ button with a few
 * entries, the plain one with an entry that deletes, and the outlined one
 * with its label in sight, as *Postpone* of a late reminder.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import { withSlots } from '@/stories/slots';
import {
    CalendarDays,
    Clock,
    Download,
    ExternalLink,
    Pencil,
    Trash2,
} from '@lucide/vue';
import { h } from 'vue';
import FmMenu from './FmMenu.vue';
import FmMenuItem from './FmMenuItem.vue';

const meta = {
    title: 'Navigation/FmMenu',
    component: FmMenu,
} satisfies Meta<typeof FmMenu>;

export default meta;

type Story = StoryObj<typeof meta>;

export const FewEntries: Story = {
    name: 'Few entries',
    render: withSlots({
        default: () => [
            h(
                FmMenuItem,
                { icon: Pencil, href: '/documents/5/edit' },
                () => 'Edit',
            ),
            h(
                FmMenuItem,
                {
                    icon: Download,
                    href: '/documents/5/file',
                    download: true,
                },
                () => 'Download',
            ),
        ],
    }),
};

export const PlainWithADelete: Story = {
    name: 'Plain, with a delete',
    args: { plain: true, label: 'Actions of the document' },
    render: withSlots({
        default: () => [
            h(
                FmMenuItem,
                { icon: Pencil, href: '/documents/5/edit' },
                () => 'Edit',
            ),
            h(
                FmMenuItem,
                {
                    icon: ExternalLink,
                    href: '/documents/5/file',
                    external: true,
                },
                () => 'Open in a new tab',
            ),
            h(FmMenuItem, { icon: Trash2, danger: true }, () => 'Delete'),
        ],
    }),
};

export const OutlinedWithItsLabel: Story = {
    name: 'Outlined, with its label',
    args: { outline: true, label: 'Rimanda', icon: Clock },
    render: withSlots({
        default: () => [
            h(FmMenuItem, null, () => 'Domani'),
            h(FmMenuItem, null, () => 'Tra una settimana'),
            h(FmMenuItem, { icon: CalendarDays }, () => 'Scegli una data…'),
        ],
    }),
};

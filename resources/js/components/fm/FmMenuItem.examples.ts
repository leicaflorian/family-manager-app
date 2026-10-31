/**
 * The cases of `FmMenuItem` for the component catalogue: a plain entry, with
 * an icon, as a link, opened in a new tab and the red one. An entry only works
 * inside a menu, so each case draws it in an `FmMenu`; open the ⋯ to see it.
 */
import { Download, ExternalLink, Pencil, Trash2 } from '@lucide/vue';
import type { PropType, SlotsType } from 'vue';
import { defineComponent, h } from 'vue';
import type { ComponentProps } from 'vue-component-type-helpers';
import { defineExamples } from '@/catalog/examples';
import FmMenu from './FmMenu.vue';
import FmMenuItem from './FmMenuItem.vue';

/** The props of the entry, which the wrapper passes on. */
type ItemProps = ComponentProps<typeof FmMenuItem>;

/** An `FmMenu` with one `FmMenuItem`, since the entry needs the menu around it. */
const InMenu = defineComponent({
    name: 'FmMenuItem',
    props: {
        icon: [Object, Function] as PropType<ItemProps['icon']>,
        href: String,
        external: Boolean,
        download: Boolean,
        danger: Boolean,
    },
    slots: Object as SlotsType<{ default: () => unknown }>,
    setup(props, { slots }) {
        return () =>
            h(FmMenu, null, () => h(FmMenuItem, { ...props }, slots.default));
    },
});

export default defineExamples(InMenu, [
    { name: 'Plain', props: {}, slots: { default: 'Rename' } },
    {
        name: 'With an icon',
        props: { icon: Pencil },
        slots: { default: 'Edit' },
    },
    {
        name: 'Link',
        props: { icon: Download, href: '/documents/5/file', download: true },
        slots: { default: 'Download' },
    },
    {
        name: 'New tab',
        props: {
            icon: ExternalLink,
            href: '/documents/5/file',
            external: true,
        },
        slots: { default: 'Open in a new tab' },
    },
    {
        name: 'Danger',
        props: { icon: Trash2, danger: true },
        slots: { default: 'Delete' },
    },
]);

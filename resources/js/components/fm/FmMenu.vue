<script setup lang="ts">
/**
 * The ⋯ button that opens a small menu of the less frequent actions, so that
 * a title next to it keeps its room. The entries are `FmMenuItem`. With
 * `text` or `outline` the button shows its label beside the icon, as
 * *Record payment* beside a title or *Postpone* on a late reminder. With
 * `control` the ⋯ is a square as tall as the buttons beside it (52 px in a
 * panel from below), as beside *Done* and *Postpone* of a reminder. With
 * `choice` the button is the thing chosen, its label in ink and bold with a
 * chevron after it, and the entries are the other things to choose, open from
 * its left edge: the utility of *Usage*. The entries
 * open above every panel, so a menu inside a panel shows.
 *
 * @group navigation
 */
import type { LucideIcon } from '@lucide/vue';
import { ChevronDown, Ellipsis, EllipsisVertical } from '@lucide/vue';
import {
    DropdownMenuContent,
    DropdownMenuPortal,
    DropdownMenuRoot,
    DropdownMenuTrigger,
} from 'reka-ui';
import FmButton from './FmButton.vue';
import FmIcon from './FmIcon.vue';
import FmSectionLink from './FmSectionLink.vue';

const {
    label,
    small = false,
    plain = false,
    text = false,
    outline = false,
    control = false,
    choice = false,
    row = false,
    icon,
} = defineProps<{
    /** The text of the button for screen readers; «More actions» if missing. */
    label?: string;
    /** The icon of the button; the ⋯ if missing. */
    icon?: LucideIcon;
    /** The smaller ⋯ button, 40 px, as beside the title of a panel. */
    small?: boolean;
    /** The ⋯ button without a background until hovered, as the plain buttons beside a row. */
    plain?: boolean;
    /** The label beside the icon, as the link beside a title (`FmSectionLink`), as *Record payment*. */
    text?: boolean;
    /** The label beside the icon, in a white button with a grey border, as *Postpone* of a late reminder. */
    outline?: boolean;
    /** The ⋯ square as tall as the buttons beside it, 44 px on the page and 52 px in a panel, as beside *Done* of a reminder. */
    control?: boolean;
    /** The label of the thing chosen, in ink and bold, with a chevron after it (`icon` replaces it): the utility of *Usage*. */
    choice?: boolean;
    /** The 32 px vertical ⋮ at the right of a list row, outside its link; `label` is the name for screen readers. */
    row?: boolean;
}>();

defineSlots<{
    /** The entries, `FmMenuItem`. */
    default: () => unknown;
}>();
</script>

<template>
    <!-- Not modal: an entry may open a panel, which takes the focus itself -->
    <DropdownMenuRoot :modal="false">
        <DropdownMenuTrigger as-child>
            <button
                v-if="outline"
                type="button"
                class="flex h-9 shrink-0 cursor-pointer items-center gap-[7px] rounded-md border border-edge-soft bg-card px-3 text-label whitespace-nowrap text-ink hover:bg-canvas focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-action"
            >
                <FmIcon :icon="icon ?? Ellipsis" :size="18" />{{
                    label ?? $t('More actions')
                }}
            </button>
            <button
                v-else-if="row"
                type="button"
                :aria-label="label ?? $t('More actions')"
                :title="label ?? $t('More actions')"
                class="mr-3 flex size-8 shrink-0 cursor-pointer items-center justify-center rounded-md border-0 bg-transparent text-ink-soft hover:bg-canvas focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-action data-[state=open]:bg-canvas"
            >
                <FmIcon :icon="icon ?? EllipsisVertical" :size="18" />
            </button>
            <button
                v-else-if="choice"
                type="button"
                class="-my-1.5 flex min-h-touch min-w-0 cursor-pointer items-center gap-1.5 rounded-md pr-2 pl-1 text-left text-label text-ink hover:bg-canvas focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-action"
            >
                <span class="min-w-0 truncate">{{
                    label ?? $t('More actions')
                }}</span>
                <FmIcon
                    :icon="icon ?? ChevronDown"
                    :size="18"
                    class="shrink-0 text-ink-soft"
                />
            </button>
            <button
                v-else-if="control"
                type="button"
                :aria-label="label ?? $t('More actions')"
                :title="label ?? $t('More actions')"
                class="flex size-control shrink-0 cursor-pointer items-center justify-center rounded-control border border-edge-soft bg-segment text-ink hover:bg-divider focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-action"
            >
                <FmIcon :icon="icon ?? Ellipsis" :size="20" />
            </button>
            <FmSectionLink v-else-if="text" :icon="icon ?? Ellipsis">{{
                label ?? $t('More actions')
            }}</FmSectionLink>
            <FmButton
                v-else
                :variant="plain ? 'text' : 'soft'"
                :icon="icon ?? Ellipsis"
                :label="label ?? $t('More actions')"
                :small="small"
                :plain="plain"
            />
        </DropdownMenuTrigger>
        <DropdownMenuPortal>
            <DropdownMenuContent
                :align="choice ? 'start' : 'end'"
                :side-offset="6"
                class="z-[80] flex min-w-56 flex-col gap-0.5 rounded-xl bg-card p-1.5 text-ink shadow-menu duration-150 data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:animate-in data-[state=open]:fade-in-0 motion-reduce:animate-none"
            >
                <slot />
            </DropdownMenuContent>
        </DropdownMenuPortal>
    </DropdownMenuRoot>
</template>

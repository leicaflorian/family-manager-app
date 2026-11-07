<script setup lang="ts">
/**
 * The ⋯ button that opens a small menu of the less frequent actions, so that
 * a title next to it keeps its room. The entries are `FmMenuItem`. With
 * `text` or `outline` the button shows its label beside the icon, as
 * *Record payment* beside a title or *Postpone* on a late reminder.
 *
 * @group navigation
 */
import type { LucideIcon } from '@lucide/vue';
import { Ellipsis } from '@lucide/vue';
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
                class="flex h-9 shrink-0 cursor-pointer items-center gap-[7px] rounded-md border border-edge bg-card px-3 text-sm font-semibold whitespace-nowrap text-ink hover:bg-canvas focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-action"
            >
                <FmIcon :icon="icon ?? Ellipsis" :size="18" />{{
                    label ?? $t('More actions')
                }}
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
                align="end"
                :side-offset="6"
                class="z-30 flex min-w-56 flex-col gap-0.5 rounded-xl bg-card p-1.5 text-ink shadow-menu duration-150 data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:animate-in data-[state=open]:fade-in-0 motion-reduce:animate-none"
            >
                <slot />
            </DropdownMenuContent>
        </DropdownMenuPortal>
    </DropdownMenuRoot>
</template>

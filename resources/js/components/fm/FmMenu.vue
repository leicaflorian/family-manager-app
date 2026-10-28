<script setup lang="ts">
/**
 * The ⋯ button that opens a small menu of the less frequent actions, so that
 * a title next to it keeps its room. The entries are `FmMenuItem`.
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

const {
    label,
    small = false,
    plain = false,
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
            <FmButton
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
                class="z-30 flex min-w-56 flex-col gap-0.5 rounded-[14px] bg-white p-1.5 text-ink shadow-[0_12px_32px_rgba(24,33,47,0.18)] duration-150 data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:animate-in data-[state=open]:fade-in-0 motion-reduce:animate-none"
            >
                <slot />
            </DropdownMenuContent>
        </DropdownMenuPortal>
    </DropdownMenuRoot>
</template>

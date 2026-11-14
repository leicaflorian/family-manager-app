<script setup lang="ts">
/**
 * The small sheet that stands for a file in front of its row: a document of
 * the family, or a file chosen and not saved yet.
 *
 * With `square` it is the box of 44 px of a file waiting in *To sort*: the
 * icon of the kind of file (`icon`), where it came from in a small circle on
 * its corner (`badge`) and, for a copy, the label *Copy* under it instead of
 * the circle.
 *
 * @group files
 */
import type { LucideIcon } from '@lucide/vue';
import { FileText } from '@lucide/vue';
import FmIcon from './FmIcon.vue';

const {
    square = false,
    icon,
    badge,
    copy = false,
} = defineProps<{
    /** The box of 44 px of a file waiting, as in *To sort*. */
    square?: boolean;
    /** The icon of the kind of file: the envelope of a mail, the photo; the sheet if none. */
    icon?: LucideIcon;
    /** Where the file came from, in a small circle on the bottom right corner. */
    badge?: LucideIcon;
    /** It is a copy: the label *Copy* under the sheet, and no circle. */
    copy?: boolean;
}>();
</script>

<template>
    <span
        aria-hidden="true"
        class="relative flex shrink-0 items-center justify-center border border-edge-soft bg-canvas text-ink-soft"
        :class="square ? 'size-11 rounded-md' : 'h-[46px] w-[38px] rounded-tag'"
    >
        <FmIcon :icon="icon ?? FileText" :size="square ? 22 : 20" />
        <span
            v-if="copy"
            class="absolute -bottom-2 left-1/2 -translate-x-1/2 rounded-sm border-2 border-card bg-warning-soft px-[5px] py-px text-badge text-warning"
            >{{ $t('Copy') }}</span
        >
        <span
            v-else-if="badge"
            data-badge
            class="absolute -right-[7px] -bottom-[7px] flex size-5.5 items-center justify-center rounded-full border border-edge-soft bg-card text-ink-soft"
        >
            <FmIcon :icon="badge" :size="12" />
        </span>
    </span>
</template>

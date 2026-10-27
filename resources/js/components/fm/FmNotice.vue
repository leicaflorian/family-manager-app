<script setup lang="ts">
/**
 * A message inside the page, with its icon: some information, something done
 * (green), something that needs attention (orange).
 */
import type { LucideIcon } from '@lucide/vue';
import { CircleAlert, Info, MailCheck } from '@lucide/vue';
import FmIcon from './FmIcon.vue';

/** The tone of the message. */
export type NoticeTone = 'info' | 'ok' | 'warning';

const { tone = 'info', icon } = defineProps<{
    /** Information, done or warning. */
    tone?: NoticeTone;
    /** An icon other than the one of the tone. */
    icon?: LucideIcon;
}>();

defineSlots<{
    /** The text of the message. */
    default: () => unknown;
}>();

const styles: Record<NoticeTone, { classes: string; icon: LucideIcon }> = {
    info: {
        classes: 'bg-canvas text-[#4A5565] [&>svg]:text-action',
        icon: Info,
    },
    ok: { classes: 'bg-[#E6F4EE] text-[#14533F]', icon: MailCheck },
    warning: {
        classes: 'bg-warning-soft text-warning',
        icon: CircleAlert,
    },
};
</script>

<template>
    <div
        role="status"
        class="flex items-start gap-2.5 rounded-xl px-3.5 py-3 text-sm leading-[1.45]"
        :class="styles[tone].classes"
    >
        <FmIcon :icon="icon ?? styles[tone].icon" :size="20" class="mt-px" />
        <span><slot /></span>
    </div>
</template>

<script setup lang="ts">
/**
 * A message inside the page, with its icon: some information, something done
 * (green), something that needs attention (orange) or that is late (red). It
 * can have a bold title above the text and, on the right, an action (a link or
 * a button), as the notices at the top of a card. The danger tone is announced
 * at once (`alert`), the others politely (`status`).
 *
 * @group basics
 */
import type { LucideIcon } from '@lucide/vue';
import { CircleAlert, Info, MailCheck, TriangleAlert } from '@lucide/vue';
import { cn } from '@/lib/utils';
import FmIcon from './FmIcon.vue';

/** The tone of the message. */
export type NoticeTone = 'info' | 'ok' | 'warning' | 'danger';

const { tone = 'info', icon } = defineProps<{
    /** Information, done, warning or danger. */
    tone?: NoticeTone;
    /** An icon other than the one of the tone. */
    icon?: LucideIcon;
    /** A line in bold above the text. */
    title?: string;
}>();

defineSlots<{
    /** The text of the message. */
    default: () => unknown;
    /** What the person can do about it: a link or a button, on the right. */
    action?: () => unknown;
}>();

const styles: Record<NoticeTone, { classes: string; icon: LucideIcon }> = {
    info: {
        classes: cn('bg-canvas text-ink-soft [&>svg]:text-action'),
        icon: Info,
    },
    ok: { classes: cn('bg-paid-soft text-paid'), icon: MailCheck },
    warning: {
        classes: cn('bg-warning-soft text-warning'),
        icon: CircleAlert,
    },
    danger: {
        classes: cn('bg-overdue/10 text-overdue'),
        icon: TriangleAlert,
    },
};
</script>

<template>
    <div
        :role="tone === 'danger' ? 'alert' : 'status'"
        class="flex items-start gap-2.5 rounded-lg px-3.5 py-3 text-sm leading-[1.45]"
        :class="styles[tone].classes"
    >
        <FmIcon :icon="icon ?? styles[tone].icon" :size="20" class="mt-px" />
        <span class="flex min-w-0 grow flex-col">
            <strong v-if="title" class="font-bold">{{ title }}</strong>
            <span><slot /></span>
        </span>
        <span v-if="$slots.action" class="shrink-0 font-semibold">
            <slot name="action" />
        </span>
    </div>
</template>

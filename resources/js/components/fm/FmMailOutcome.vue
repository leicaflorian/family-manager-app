<script setup lang="ts">
/**
 * The label of what was done with a received mail, as *Received mail* shows it
 * on each row: green with a tick for the files saved, blue with a bell for a
 * notice or the code of Gmail, grey for a mail discarded or with nothing left,
 * orange while it is under review, red for an error.
 *
 * @group basics
 */
import type { LucideIcon } from '@lucide/vue';
import { Bell, CircleAlert, CircleCheck, Hourglass, MailX } from '@lucide/vue';
import { cn } from '@/lib/utils';
import FmIcon from './FmIcon.vue';

/** The colour of the label. */
export type MailTone = App.Data.ReceivedMailRowData['tone'];

const { label, tone } = defineProps<{
    /** What was done, in a few words: «1 file saved · 2 deliveries». */
    label: string;
    /** The colour, given by the server with the label. */
    tone: MailTone;
}>();

const styles: Record<MailTone, { classes: string; icon: LucideIcon }> = {
    success: { classes: cn('bg-paid-soft text-paid'), icon: CircleCheck },
    info: { classes: cn('bg-action-soft text-action-dark'), icon: Bell },
    neutral: { classes: cn('bg-divider text-ink-soft'), icon: MailX },
    warning: { classes: cn('bg-warning-soft text-warning'), icon: Hourglass },
    danger: { classes: cn('bg-overdue-soft text-overdue'), icon: CircleAlert },
};
</script>

<template>
    <span
        class="inline-flex items-center gap-[5px] rounded-tag px-2 py-[3px] text-[13px] font-semibold whitespace-nowrap"
        :class="styles[tone].classes"
    >
        <FmIcon :icon="styles[tone].icon" :size="14" />
        {{ label }}
    </span>
</template>

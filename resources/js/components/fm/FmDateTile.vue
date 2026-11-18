<script setup lang="ts">
/**
 * A date of a card on its page: the label, the date as `15/10/2026` and below
 * how far it is from today. An expiring date already passed reads «Expired 3
 * days ago» in red. `inset` drops the card around it, for a date inside the box
 * of the figures.
 *
 * @group figures
 */
import { transChoice } from 'laravel-vue-i18n';
import { computed } from 'vue';
import { useFormat } from '@/composables/useFormat';
import { daysFromToday, relativeDays } from '@/lib/format';
import { cn } from '@/lib/utils';

const { date, inset = false } = defineProps<{
    /** The date, with its label and whether it expires. */
    date: App.Data.CardDateData;
    /** Inside the box of the figures: no card of its own. */
    inset?: boolean;
}>();

const { date: formatted } = useFormat();

/** The line below the date and whether it is the red one. */
const distance = computed((): { text: string; expired: boolean } => {
    const days = daysFromToday(date.date);

    return date.expires && days < 0
        ? {
              text: transChoice(
                  '{1} Expired :count day ago|[2,*] Expired :count days ago',
                  -days,
                  { count: String(-days) },
              ),
              expired: true,
          }
        : { text: relativeDays(date.date), expired: false };
});
</script>

<template>
    <div
        class="flex min-w-0 flex-col gap-1"
        :class="!inset && 'rounded-card bg-card p-4 shadow-card'"
    >
        <span class="text-meta-strong text-ink-soft">{{ date.label }}</span>
        <span class="text-subtitle text-ink">{{ formatted(date.date) }}</span>
        <span
            :class="
                cn(
                    'text-body-sm',
                    distance.expired
                        ? 'text-label text-overdue'
                        : 'text-ink-soft',
                )
            "
            >{{ distance.text }}</span
        >
    </div>
</template>

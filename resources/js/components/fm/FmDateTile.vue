<script setup lang="ts">
/**
 * A date of a card on its page: the label, the date as `15/10/2026` and below
 * how far it is from today. An expiring date already passed reads «Expired 3
 * days ago» in red.
 */
import { transChoice } from 'laravel-vue-i18n';
import { computed } from 'vue';
import { useFormat } from '@/composables/useFormat';
import { daysFromToday, relativeDays } from '@/lib/format';
import { cn } from '@/lib/utils';

const { date } = defineProps<{
    /** The date, with its label and whether it expires. */
    date: App.Data.CardDateData;
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
    <div class="flex flex-col gap-1 rounded-2xl bg-white p-4">
        <span class="text-[13px] font-semibold text-ink-soft">{{
            date.label
        }}</span>
        <span class="text-lg font-bold text-ink">{{
            formatted(date.date)
        }}</span>
        <span
            :class="
                cn(
                    'text-sm',
                    distance.expired
                        ? 'font-semibold text-overdue'
                        : 'text-ink-soft',
                )
            "
            >{{ distance.text }}</span
        >
    </div>
</template>

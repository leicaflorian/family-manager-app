<script setup lang="ts">
/**
 * The blue box of the Home, *To pay in the next 30 days*: the sum of the
 * payments not yet made, and below how many they are, with the late ones
 * («8 payments, one already late»). With nothing to pay it says that nothing
 * is due and how to begin.
 *
 * @group figures
 */
import { transChoice } from 'laravel-vue-i18n';
import { computed } from 'vue';
import { useFormat } from '@/composables/useFormat';

const { total, payments, late } = defineProps<{
    /** The sum of the payments, in euros. */
    total: number;
    /** How many payments make the sum. */
    payments: number;
    /** How many of them are already late. */
    late: number;
}>();

const { euro } = useFormat();

/** How many payments there are, and how many of them are late. */
const count = computed((): string => {
    const replace = { count: String(payments), late: String(late) };

    if (late === 0) {
        return transChoice(
            '{1} :count payment|[2,*] :count payments',
            payments,
            replace,
        );
    }

    return late === 1
        ? transChoice(
              '{1} :count payment, already late|[2,*] :count payments, one already late',
              payments,
              replace,
          )
        : transChoice(
              '[2,*] :count payments, :late already late',
              payments,
              replace,
          );
});
</script>

<template>
    <section
        class="flex flex-col gap-1 rounded-[20px] bg-action p-5 text-white"
        :aria-label="$t('To pay in the next 30 days')"
    >
        <h2 class="m-0 text-[15px] font-semibold opacity-90">
            {{ $t('To pay in the next 30 days') }}
        </h2>
        <p class="m-0 text-4xl font-extrabold whitespace-nowrap">
            {{ euro(total) }}
        </p>
        <p v-if="payments > 0" class="m-0 text-[15px] opacity-90">
            {{ count }}
        </p>
        <p v-else class="m-0 text-[15px] opacity-90">
            {{
                $t(
                    "Nothing for now. Upload a bill and you'll see it here, with its due date.",
                )
            }}
        </p>
    </section>
</template>

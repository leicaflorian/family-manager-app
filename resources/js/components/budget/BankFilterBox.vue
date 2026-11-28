<script setup lang="ts">
/**
 * *The bank filter* of a planned expense or income, in *Where* of its form, as the boards *Spesa-fissa-modifica*
 * (phone, `-tablet`, `-pc`) draw it: the name of the filter that pays its due dates with how many movements it took in
 * the last 90 days, its lines *Matches:* and *Action:*, and *Edit the filter*. Without a filter it says so and, for who
 * can make one, offers *Create the filter*; a new planned item says the filter is made after saving.
 *
 * @group forms
 */
import { Pencil, Plus } from '@lucide/vue';
import { transChoice } from 'laravel-vue-i18n';
import { computed } from 'vue';
import BankFilterLines from '@/components/budget/BankFilterLines.vue';
import FmButton from '@/components/fm/FmButton.vue';
import { edit } from '@/routes/budget/bank/filters';

const {
    filter,
    createUrl = null,
    isNew = false,
} = defineProps<{
    /** The filter that pays the item, null when there is none. */
    filter: App.Data.BankFilterData | null;
    /** The address of the form of a new filter for this item, null for who cannot make one. */
    createUrl?: string | null;
    /** The planned item is not saved yet: the filter is made after saving. */
    isNew?: boolean;
}>();

/** How many movements of the last 90 days the filter took. */
const taken = computed(() =>
    filter === null
        ? ''
        : transChoice(
              '{0} No movement in the last 90 days|{1} 1 movement in the last 90 days|[2,*] :count movements in the last 90 days',
              filter.taken,
          ),
);
</script>

<template>
    <div class="mb-3.5 flex flex-col">
        <span class="mb-1.5 text-label text-ink">{{
            $t('How it shows in the bank')
        }}</span>
        <div
            class="flex flex-col gap-2 rounded-lg border border-edge-soft bg-card px-3.5 py-3"
        >
            <template v-if="filter !== null">
                <span class="flex min-w-0 flex-col gap-0.5">
                    <span class="text-body-strong text-ink">{{
                        filter.name
                    }}</span>
                    <span class="text-meta text-ink-soft">{{ taken }}</span>
                </span>
                <BankFilterLines :filter="filter" />
                <FmButton
                    v-if="filter.id !== null"
                    variant="neutral"
                    class="mt-1 self-start"
                    :icon="Pencil"
                    :href="edit(filter.id).url"
                    >{{ $t('Edit the filter') }}</FmButton
                >
            </template>
            <template v-else>
                <span class="text-body-sm text-ink">{{
                    isNew
                        ? $t('The filter is made after saving.')
                        : $t('No filter yet')
                }}</span>
                <FmButton
                    v-if="createUrl !== null"
                    variant="neutral"
                    class="mt-1 self-start"
                    :icon="Plus"
                    :href="createUrl"
                    >{{ $t('Create the filter') }}</FmButton
                >
            </template>
        </div>
        <span
            v-if="filter?.action === 'pay'"
            class="mt-1 text-meta text-ink-soft"
            >{{
                $t(
                    'When a movement the filter takes arrives, the due date is marked as paid by itself.',
                )
            }}</span
        >
    </div>
</template>

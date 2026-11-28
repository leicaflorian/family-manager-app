<script setup lang="ts">
/**
 * What the system proposes for a group of similar movements, as the boards *Dalla banca* and *Dalla banca, il
 * gruppo* draw it, on the card of the group and in its panel: «Proposal: create the expense «Ricarica Satispay» ·
 * Varie · Satispay» in blue with the sparkles, or, when no expense looks like the movements, the grey box that says
 * the AI will suggest title and category when the filter is created.
 *
 * @group rows
 */
import { SearchX, Sparkles } from '@lucide/vue';
import { computed } from 'vue';
import { groupProposal } from '@/lib/bankGroups';

const { group } = defineProps<{
    /** The group, as the server sends it in `groups`. */
    group: App.Data.BankGroupData;
}>();

/** What the system proposes, in a few words; null without a proposal. */
const proposal = computed((): string | null => groupProposal(group));
</script>

<template>
    <span
        v-if="proposal"
        class="flex items-start gap-2 rounded-lg bg-action-soft px-3 py-2.5 text-label text-action-dark"
    >
        <Sparkles aria-hidden="true" class="mt-px size-[18px] shrink-0" />
        <span class="min-w-0">{{
            $t('Proposal: :proposal', { proposal })
        }}</span>
    </span>
    <span
        v-else
        class="flex items-start gap-2 rounded-lg bg-card-sunken px-3 py-2.5 text-meta text-ink-soft"
    >
        <SearchX aria-hidden="true" class="mt-px size-[18px] shrink-0" />
        <span class="min-w-0">{{
            $t(
                'No similar expense found. When you create the filter, the AI suggests title and category.',
            )
        }}</span>
    </span>
</template>

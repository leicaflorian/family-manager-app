<script setup lang="ts">
/**
 * What the reading of a file says once it ended: when it is done, the sentence of what was found with the
 * invitation to check it; when it failed, is off or was skipped, the reason (with an action beside, for example
 * *Read again*). Nothing while the file waits or was never read.
 *
 * @group basics
 */
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';
import { isNotRead } from '@/lib/reading';
import FmNotice from './FmNotice.vue';

const { status, headline, error } = defineProps<{
    /** Where the reading stands. */
    status?: App.Enums.ReadingStatus | null;
    /** What the reading found, in a sentence; shown when it is done. */
    headline?: string | null;
    /** Why the file was not read; shown when it failed (with a sentence of its own when missing), is off or was skipped. */
    error?: string | null;
}>();

defineSlots<{
    /** What the person can do about a reading that did not work, on the right. */
    action?: () => unknown;
}>();

/** The sentence of what was found: only a done reading has one. */
const found = computed(() => (status === 'done' && headline ? headline : null));

/**
 * The reason, only for a reading that ended without a proposal. A failure always says something, so that the
 * notice and its action show even when the server gave no reason.
 */
const reason = computed(() => {
    if (!isNotRead(status)) {
        return null;
    }

    return (
        error ||
        (status === 'failed' ? trans('The file could not be read.') : null)
    );
});
</script>

<template>
    <FmNotice v-if="found" :title="found">
        {{ $t('Check it, change what is wrong and save.') }}
    </FmNotice>
    <FmNotice
        v-else-if="reason"
        :tone="status === 'failed' ? 'warning' : 'info'"
    >
        {{ reason }}
        <template v-if="$slots.action" #action><slot name="action" /></template>
    </FmNotice>
</template>

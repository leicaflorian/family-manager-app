<script setup lang="ts">
/**
 * What the reading of a file says once it ended: when it is done, the sentence of what was found with the
 * invitation to check it; when it failed, is off or was skipped, the reason, with *Read again* beside when the page
 * offers it and asking again can help (failed or off). Nothing while the file waits or was never read.
 *
 * @group basics
 */
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';
import { canReadAgain, isNotRead } from '@/lib/reading';
import FmNotice from './FmNotice.vue';

const { status, headline, error, again } = defineProps<{
    /** Where the reading stands. */
    status?: App.Enums.ReadingStatus | null;
    /** What the reading found, in a sentence; shown when it is done. */
    headline?: string | null;
    /** Why the file was not read; shown when it failed (with a sentence of its own when missing), is off or was skipped. */
    error?: string | null;
    /** True when the page can put the reading back in the queue: *Read again* shows for a reading failed or off. */
    again?: boolean;
}>();

defineEmits<{
    /** The person pressed *Read again*. */
    again: [];
}>();

/** Whether *Read again* shows: the page offers it, and the reading failed or was off. */
const showAgain = computed(() => again && canReadAgain(status));

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
        <template v-if="showAgain" #action>
            <button
                type="button"
                class="cursor-pointer border-0 bg-transparent p-0 font-semibold text-inherit underline"
                @click="$emit('again')"
            >
                {{ $t('Read again') }}
            </button>
        </template>
    </FmNotice>
</template>

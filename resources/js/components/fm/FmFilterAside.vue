<script setup lang="ts">
/**
 * The left column of a list with filters, on a computer: a sticky card that
 * shows the filters, or, with `detail`, the detail of a row chosen in the list
 * under a header with *Back to the filters* and a ×, both asking to close the
 * detail. Used by *Movements*, *Planned expenses* and *Documents*.
 *
 * @group panels
 */
import { ArrowLeft, X } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import FmIcon from './FmIcon.vue';

const {
    detail,
    label = trans('Filters'),
    detailLabel = trans('Detail'),
} = defineProps<{
    /** Shows the detail slot, with its header, instead of the filters. */
    detail: boolean;
    /** What the filters are called, said to screen readers. */
    label?: string;
    /** What the detail is called, said to screen readers. */
    detailLabel?: string;
}>();

const emit = defineEmits<{
    /** Back to the filters, or the × of the detail: the page closes the detail. */
    close: [];
}>();

defineSlots<{
    /** The filters. */
    filters: () => unknown;
    /** The detail of the chosen row. */
    detail: () => unknown;
}>();
</script>

<template>
    <aside
        v-if="detail"
        :aria-label="detailLabel"
        class="sticky top-(--app-header) flex flex-col gap-3.5 rounded-card-lg bg-card p-5 shadow-card"
    >
        <div
            class="-mt-2 -mr-2 -ml-1.5 flex items-center justify-between gap-2"
        >
            <button
                type="button"
                class="flex min-h-touch cursor-pointer items-center gap-1.5 border-0 bg-transparent px-1.5 text-label text-ink"
                @click="emit('close')"
            >
                <FmIcon :icon="ArrowLeft" :size="18" />{{
                    $t('Back to the filters')
                }}
            </button>
            <button
                type="button"
                :aria-label="$t('Close')"
                class="flex size-touch shrink-0 cursor-pointer items-center justify-center border-0 bg-transparent text-ink-soft"
                @click="emit('close')"
            >
                <FmIcon :icon="X" :size="22" />
            </button>
        </div>
        <slot name="detail" />
    </aside>
    <aside
        v-else
        :aria-label="label"
        class="sticky top-(--app-header) rounded-card-lg bg-card p-5 shadow-card"
    >
        <slot name="filters" />
    </aside>
</template>

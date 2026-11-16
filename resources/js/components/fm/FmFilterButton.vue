<script setup lang="ts">
/**
 * The square button that opens the filters of a list on the phone and the
 * tablet. With filters on it turns soft blue and shows how many are on in a
 * badge; its name says it too, and `aria-expanded` whether the panel is open.
 *
 * @group basics
 */
import { ListFilter } from '@lucide/vue';
import { trans, transChoice } from 'laravel-vue-i18n';
import { computed } from 'vue';
import { cn } from '@/lib/utils';
import FmIcon from './FmIcon.vue';

const { count, expanded = false } = defineProps<{
    /** How many filters are on. */
    count: number;
    /** Whether the panel of the filters it opens is open, said to screen readers. */
    expanded?: boolean;
}>();

const emit = defineEmits<{
    /** The button was pressed. */
    click: [];
}>();

/** What screen readers say: *Filters*, or how many are on. */
const name = computed(() =>
    count > 0
        ? transChoice('{1} Filters, :count on|[2,*] Filters, :count on', count)
        : trans('Filters'),
);

/** The look of the button, soft blue when a filter is on. */
const look = computed(() =>
    cn(
        'relative flex size-12 shrink-0 cursor-pointer items-center justify-center rounded-xl border',
        count > 0
            ? 'border-action-soft bg-action-soft text-action-dark'
            : 'border-edge-soft bg-card text-ink',
    ),
);
</script>

<template>
    <button
        type="button"
        :aria-label="name"
        aria-haspopup="dialog"
        :aria-expanded="expanded"
        :class="look"
        @click="emit('click')"
    >
        <FmIcon :icon="ListFilter" :size="22" />
        <span
            v-if="count > 0"
            aria-hidden="true"
            class="absolute -top-1.5 -right-1.5 flex h-5 min-w-5 items-center justify-center rounded-full border-2 border-canvas bg-action px-1.25 text-badge text-on-action"
            >{{ count }}</span
        >
    </button>
</template>

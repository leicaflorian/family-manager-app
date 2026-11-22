<script setup lang="ts">
/**
 * The button of the filters of a list. Square, it opens the panel from below
 * on the phone and the tablet; `labelled` it shows the word *Filters* and
 * opens (`controls`) the box under the search bar on the wide layout. With
 * filters on it shows how many are on in a badge; its name says it too, and
 * `aria-expanded` whether the panel or box is open.
 *
 * @group basics
 */
import { ListFilter } from '@lucide/vue';
import { trans, transChoice } from 'laravel-vue-i18n';
import { computed } from 'vue';
import { cn } from '@/lib/utils';
import FmIcon from './FmIcon.vue';

const {
    count,
    expanded = false,
    labelled = false,
    controls,
} = defineProps<{
    /** How many filters are on. */
    count: number;
    /** Whether the panel of the filters it opens is open, said to screen readers. */
    expanded?: boolean;
    /** Shows the word *Filters* beside the icon, for the box of the wide layout. */
    labelled?: boolean;
    /** The id of the box it opens: sets `aria-controls` and leaves `aria-haspopup` off. */
    controls?: string;
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

/** The look of the button, soft blue when a filter is on (the box open, when labelled). */
const look = computed(() =>
    labelled
        ? cn(
              'flex h-control shrink-0 cursor-pointer items-center gap-2 rounded-control px-3.5 text-label',
              expanded
                  ? 'border-[1.5px] border-action bg-action-soft text-action-dark'
                  : 'border border-edge-soft bg-card text-ink',
          )
        : cn(
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
        :aria-haspopup="controls ? undefined : 'dialog'"
        :aria-controls="controls"
        :aria-expanded="expanded"
        :class="look"
        @click="emit('click')"
    >
        <FmIcon :icon="ListFilter" :size="labelled ? 20 : 22" />
        <template v-if="labelled">
            <span aria-hidden="true">{{ $t('Filters') }}</span>
            <span
                v-if="count > 0"
                aria-hidden="true"
                class="flex h-5.5 min-w-5.5 items-center justify-center rounded-full bg-action px-1.5 text-badge text-on-action"
                >{{ count }}</span
            >
        </template>
        <span
            v-else-if="count > 0"
            aria-hidden="true"
            class="absolute -top-1.5 -right-1.5 flex h-5 min-w-5 items-center justify-center rounded-full border-2 border-canvas bg-action px-1.25 text-badge text-on-action"
            >{{ count }}</span
        >
    </button>
</template>

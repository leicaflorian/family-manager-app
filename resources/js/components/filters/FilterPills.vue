<script setup lang="ts">
/**
 * The line above a filtered list: a pill for each filter on, each with its ×,
 * *Remove the filters*, and what was found (beside them on the computer,
 * under them on the phone). Shared by *Movements* and *Planned expenses*.
 */
import FmActiveFilter from '@/components/fm/FmActiveFilter.vue';
import { useComputerLayout } from '@/composables/useLayoutMode';
import { cn } from '@/lib/utils';

const { pills, found = '' } = defineProps<{
    /** The filters on, with what each pill says. */
    pills: { key: string; label: string }[];
    /** What was found, for example «6 movements found, 310,10 € in all»; empty for nothing. */
    found?: string;
}>();

const emit = defineEmits<{
    /** The × of a pill: the filter to take away. */
    remove: [key: string];
    /** *Remove the filters*. */
    removeAll: [];
}>();

const desktop = useComputerLayout();
</script>

<template>
    <div class="flex flex-wrap items-center gap-2">
        <FmActiveFilter
            v-for="pill in pills"
            :key="pill.key"
            :label="pill.label"
            @remove="emit('remove', pill.key)"
        />
        <button
            type="button"
            class="cursor-pointer border-0 bg-transparent px-1 py-0 text-label text-action"
            @click="emit('removeAll')"
        >
            {{ $t('Remove the filters') }}
        </button>
        <span
            v-if="found !== ''"
            :class="
                cn(
                    'text-body-sm text-ink-soft',
                    desktop ? 'ml-1' : 'mt-1 basis-full',
                )
            "
            >{{ found }}</span
        >
    </div>
</template>

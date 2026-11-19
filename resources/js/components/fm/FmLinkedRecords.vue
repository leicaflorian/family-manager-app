<script setup lang="ts">
/**
 * *Links* of *A person* and *An organization*: the records that name them, one
 * type at a time, with a tab for each type and how many records it has. A tab
 * shows three records at a time, with *Show 3 more* under them («altre» or
 * «altri» in Italian, as the name of the tab).
 *
 * @group sections
 */
import { computed, ref, watch } from 'vue';
import { linkKind, showMoreLabel } from '@/lib/showMore';
import { show } from '@/routes/cards';
import FmRow from './FmRow.vue';
import FmShowMore from './FmShowMore.vue';

const { groups } = defineProps<{
    /** The records, grouped by type. */
    groups: App.Data.LinkGroupData[];
}>();

/** The type whose records are shown: the first, until another is chosen. */
const chosen = ref(groups[0]?.type ?? '');

const open = computed(
    () => groups.find((group) => group.type === chosen.value) ?? groups[0],
);

/** How many records of a tab a tap on *Show more* adds. */
const STEP = 3;

/** How many records of the open tab are shown: three, until more are asked. */
const shown = ref(STEP);

// Another tab starts again from its first three
watch(chosen, () => {
    shown.value = STEP;
});

/** How many records of the open tab are still hidden. */
const left = computed((): number =>
    Math.max(0, (open.value?.options.length ?? 0) - shown.value),
);
</script>

<template>
    <section class="flex flex-col gap-2">
        <h2 class="m-0 text-subtitle">{{ $t('Links') }}</h2>
        <div class="rounded-card bg-card px-4 pt-1 shadow-card">
            <template v-if="open">
                <div
                    class="flex gap-4 overflow-x-auto border-b border-edge-soft"
                >
                    <button
                        v-for="group in groups"
                        :key="group.type"
                        type="button"
                        :aria-pressed="group.type === open.type"
                        class="flex h-9 shrink-0 cursor-pointer items-center gap-1.5 border-0 border-b-2 bg-transparent px-0.5 text-body-sm whitespace-nowrap"
                        :class="
                            group.type === open.type
                                ? 'border-action text-action-dark'
                                : 'border-transparent text-ink-soft'
                        "
                        @click="chosen = group.type"
                    >
                        {{ group.label }}
                        <span class="font-semibold text-ink-soft">{{
                            group.options.length
                        }}</span>
                    </button>
                </div>
                <div v-if="open" class="-mx-4 divide-y divide-divider">
                    <FmRow
                        v-for="record in open.options.slice(0, shown)"
                        :key="record.value"
                        :title="record.label"
                        :href="show({ type: open.type, id: record.value }).url"
                    />
                    <FmShowMore
                        v-if="left > 0"
                        :label="showMoreLabel(left, STEP, linkKind(open.type))"
                        @more="shown += STEP"
                    />
                </div>
            </template>
            <p v-else class="m-0 py-3 text-body-sm text-ink-soft">
                {{ $t('No records yet.') }}
            </p>
        </div>
    </section>
</template>

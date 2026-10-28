<script setup lang="ts">
/**
 * *Links* of *A person* and *An organization*: the records that name them, one
 * type at a time, with a tab for each type and how many records it has.
 */
import { computed, ref } from 'vue';
import { show } from '@/routes/cards';
import FmRow from './FmRow.vue';

const { groups } = defineProps<{
    /** The records, grouped by type. */
    groups: App.Data.LinkGroupData[];
}>();

/** The type whose records are shown: the first, until another is chosen. */
const chosen = ref(groups[0]?.type ?? '');

const open = computed(
    () => groups.find((group) => group.type === chosen.value) ?? groups[0],
);
</script>

<template>
    <section class="flex flex-col gap-2">
        <h2 class="m-0 text-lg font-extrabold">{{ $t('Links') }}</h2>
        <div class="rounded-2xl bg-white px-4 pt-1">
            <template v-if="open">
                <div class="flex gap-4 overflow-x-auto border-b border-edge">
                    <button
                        v-for="group in groups"
                        :key="group.type"
                        type="button"
                        :aria-pressed="group.type === open.type"
                        class="flex h-9 shrink-0 cursor-pointer items-center gap-1.5 border-0 border-b-2 bg-transparent px-0.5 text-[15px] whitespace-nowrap"
                        :class="
                            group.type === open.type
                                ? 'border-action text-action-dark'
                                : 'border-transparent text-[#4A5565]'
                        "
                        @click="chosen = group.type"
                    >
                        {{ group.label }}
                        <span class="font-semibold text-ink-soft">{{
                            group.options.length
                        }}</span>
                    </button>
                </div>
                <div class="-mx-4 divide-y divide-divider">
                    <FmRow
                        v-for="record in open.options"
                        :key="record.value"
                        :title="record.label"
                        :href="show({ type: open.type, id: record.value }).url"
                    />
                </div>
            </template>
            <p v-else class="m-0 py-3 text-[15px] text-ink-soft">
                {{ $t('No records yet.') }}
            </p>
        </div>
    </section>
</template>

<script setup lang="ts">
/**
 * *Links* of *A person* and *An organization*: the records that name them, one
 * type at a time, with a tab for each type and how many records it has. With
 * `reminders` a last entry, *Reminders* with their number, leads to the list of
 * reminders of that person.
 *
 * @group sections
 */
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { show } from '@/routes/cards';
import FmRow from './FmRow.vue';

const { groups, reminders } = defineProps<{
    /** The records, grouped by type. */
    groups: App.Data.LinkGroupData[];
    /** The open reminders of the person: how many, and the list they are in; no entry when missing. */
    reminders?: { count: number; href: string };
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
            <template v-if="open || reminders">
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
                    <Link
                        v-if="reminders"
                        :href="reminders.href"
                        class="flex h-9 shrink-0 items-center gap-1.5 border-b-2 border-transparent px-0.5 text-[15px] whitespace-nowrap text-[#4A5565] no-underline"
                    >
                        {{ $t('Reminders') }}
                        <span class="font-semibold text-ink-soft">{{
                            reminders.count
                        }}</span>
                    </Link>
                </div>
                <div v-if="open" class="-mx-4 divide-y divide-divider">
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

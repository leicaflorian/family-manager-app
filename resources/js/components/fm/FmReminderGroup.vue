<script setup lang="ts">
/**
 * The box of a group of notices in *Settings → Notices*: its name (with the
 * label *Default* for the group of everything else), the kinds of deadline it
 * covers in words and a line for each notice («45 days before · App and email ·
 * Everyone»). Whoever can edit has the *Edit* button.
 *
 * @group sections
 */
import { computed } from 'vue';
import type { ReminderGroupItem, RulePerson } from '@/lib/reminders';
import { ruleText } from '@/lib/reminders';

const { group, people, canEdit } = defineProps<{
    /** The group. */
    group: ReminderGroupItem;
    /** The people with an account, to name the recipients. */
    people: RulePerson[];
    /** Whoever is looking can edit the group (an `admin`). */
    canEdit: boolean;
}>();

const emit = defineEmits<{
    /** *Edit* was tapped. */
    edit: [];
}>();

/** The kinds in words: the first as it is, the others in lower case. */
const kinds = computed((): string =>
    group.kinds
        .map((kind, index) =>
            index === 0 ? kind : kind.charAt(0).toLowerCase() + kind.slice(1),
        )
        .join(', '),
);
</script>

<template>
    <section
        :aria-label="group.name"
        class="flex flex-col overflow-hidden rounded-card-lg bg-white"
    >
        <div class="flex items-start justify-between gap-3 px-4 py-3.5">
            <div class="flex min-w-0 flex-col gap-0.5">
                <h3 class="m-0 flex items-center gap-2 text-base font-bold">
                    {{ group.name }}
                    <span
                        v-if="group.is_default"
                        class="rounded-full bg-action-soft px-2 py-0.5 text-xs font-bold text-action-dark"
                        >{{ $t('Default') }}</span
                    >
                </h3>
                <p class="m-0 text-[13px] text-ink-soft">
                    {{
                        group.is_default
                            ? $t(
                                  'Reminders written by hand and everything else',
                              )
                            : kinds
                    }}
                </p>
            </div>
            <button
                v-if="canEdit"
                type="button"
                class="min-h-11 shrink-0 cursor-pointer border-0 bg-transparent px-1 text-[15px] font-semibold text-action-dark"
                @click="emit('edit')"
            >
                {{ $t('Edit') }}
            </button>
        </div>
        <ul class="m-0 flex list-none flex-col border-t border-divider p-0">
            <li
                v-for="(rule, index) in group.rules"
                :key="index"
                class="border-b border-divider px-4 py-3 text-sm last:border-b-0"
            >
                {{ ruleText(rule, people) }}
            </li>
        </ul>
    </section>
</template>

<script setup lang="ts">
/**
 * The list of the tab *Filters* of *From the bank*, as the boards *Dalla-banca-filtri* (phone) and
 * *Dalla-banca-filtri-tablet* / *-pc* (wide) draw it: a row per filter with its name, the line *Matches* (the words as
 * tags, the words left out struck through, the direction, the account), the line *Action* (the name it speaks of in
 * bold) and how many movements it took in the last 90 days. An advanced filter, made from the rule of a planned item,
 * has its tag and says it changes only as text; a filter that misses something (the planned item it pays ended, the
 * account of the transfer is gone) says what. *Edit* and *Delete* are in the menu of the row on the phone and at its
 * end when wide, with the icon of the action before the row; *Delete* asks first.
 */
import {
    ArrowLeftRight,
    CircleCheck,
    EllipsisVertical,
    EyeOff,
    Funnel,
    Pencil,
    Plus,
    Trash2,
    TriangleAlert,
} from '@lucide/vue';
import type { LucideIcon } from '@lucide/vue';
import { trans, transChoice } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';
import FmButton from '@/components/fm/FmButton.vue';
import FmConfirmDelete from '@/components/fm/FmConfirmDelete.vue';
import FmDeleteButton from '@/components/fm/FmDeleteButton.vue';
import FmMenu from '@/components/fm/FmMenu.vue';
import FmMenuItem from '@/components/fm/FmMenuItem.vue';
import { useWideLayout } from '@/composables/useWideLayout';
import { actionPieces, criteriaParts } from '@/lib/bankFilters';
import { destroy, edit } from '@/routes/budget/bank/filters';

defineProps<{
    /** The filters of the family, by name, as the server sends them (`bank_filters`). */
    filters: App.Data.BankFilterData[];
}>();

const wide = useWideLayout();

/** The filter whose deletion is being asked on the phone, null while nothing is asked. */
const deleting = ref<App.Data.BankFilterData | null>(null);

/** The question of the deletion is open. */
const deleteOpen = computed({
    get: (): boolean => deleting.value !== null,
    set: (value: boolean) => {
        if (!value) {
            deleting.value = null;
        }
    },
});

/**
 * The icon of the action of a filter, in the square before the row when wide.
 *
 * @param action - the action of the filter
 * @returns the icon
 */
function actionIcon(action: string | null): LucideIcon {
    switch (action) {
        case 'create':
            return Plus;
        case 'pay':
            return CircleCheck;
        case 'transfer':
            return ArrowLeftRight;
        case 'ignore':
            return EyeOff;
        default:
            return Funnel;
    }
}

/**
 * How many movements of the last 90 days the filter took.
 *
 * @param filter - the filter
 * @returns «11 movements in the last 90 days» on the phone, «11 movements» when wide
 */
function takenText(filter: App.Data.BankFilterData): string {
    return wide.value
        ? transChoice(
              '{0} No movement|{1} 1 movement|[2,*] :count movements',
              filter.taken,
          )
        : transChoice(
              '{0} No movement in the last 90 days|{1} 1 movement in the last 90 days|[2,*] :count movements in the last 90 days',
              filter.taken,
          );
}

/**
 * The sentence under an advanced filter.
 *
 * @param filter - the filter, with its expression
 * @returns where it comes from and that it changes only as text
 */
function advancedText(filter: App.Data.BankFilterData): string {
    if (filter.planned === null) {
        return trans('It changes only as text.');
    }

    return trans(
        filter.planned.kind === 'income'
            ? 'It comes from the rule of the planned income :name: it changes only as text.'
            : 'It comes from the rule of the planned expense :name: it changes only as text.',
        { name: filter.planned.title },
    );
}
</script>

<template>
    <ul
        class="m-0 flex list-none flex-col divide-y divide-divider overflow-hidden rounded-card bg-card p-0 shadow-card"
    >
        <li
            v-for="filter in filters"
            :key="filter.id ?? filter.name"
            :class="
                wide
                    ? 'flex items-start gap-5 px-5 py-4'
                    : 'relative flex flex-col gap-2 py-3.5 pr-2 pl-4'
            "
        >
            <span
                v-if="wide"
                aria-hidden="true"
                class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-action-soft text-action-dark"
            >
                <component :is="actionIcon(filter.action)" class="size-5" />
            </span>
            <div class="flex min-w-0 grow flex-col gap-2">
                <div class="flex items-center gap-2">
                    <span class="flex min-w-0 grow flex-col gap-0.5">
                        <span class="flex flex-wrap items-center gap-2">
                            <span
                                data-filter-name
                                class="text-body-strong text-ink"
                                >{{ filter.name }}</span
                            >
                            <span
                                v-if="filter.pattern !== null"
                                class="inline-flex self-start rounded-tag bg-card-sunken px-2 py-[3px] text-meta-strong text-ink-soft"
                                >{{ $t('Advanced filter') }}</span
                            >
                        </span>
                        <span v-if="!wide" class="text-meta text-ink-soft">{{
                            takenText(filter)
                        }}</span>
                    </span>
                    <FmMenu
                        v-if="!wide && (filter.can.update || filter.can.delete)"
                        plain
                        :icon="EllipsisVertical"
                        :label="$t('More for :name', { name: filter.name })"
                    >
                        <FmMenuItem
                            v-if="filter.can.update && filter.id !== null"
                            :icon="Pencil"
                            :href="edit(filter.id).url"
                            >{{ $t('Edit') }}</FmMenuItem
                        >
                        <FmMenuItem
                            v-if="filter.can.delete"
                            :icon="Trash2"
                            danger
                            @select="deleting = filter"
                            >{{ $t('Delete') }}</FmMenuItem
                        >
                    </FmMenu>
                </div>
                <div
                    :class="
                        wide
                            ? 'flex flex-col gap-2'
                            : 'flex flex-col gap-2 pr-2'
                    "
                >
                    <span
                        class="flex flex-wrap items-center gap-1.5 text-body-sm text-ink"
                    >
                        <span class="text-meta-strong text-ink-soft">{{
                            $t('Matches:')
                        }}</span>
                        <template
                            v-for="(part, index) in criteriaParts(filter)"
                            :key="index"
                        >
                            <span v-if="index > 0">{{ ' · ' }}</span>
                            <template v-if="part.kind === 'words'">
                                {{ part.label }}
                                <span
                                    v-for="word in part.words"
                                    :key="word"
                                    :class="
                                        part.excluded
                                            ? 'inline-flex h-6 items-center rounded-tag bg-card-sunken px-2 text-meta-strong whitespace-nowrap text-ink-soft line-through decoration-ink-faint'
                                            : 'inline-flex h-6 items-center rounded-tag bg-card-sunken px-2 text-meta-strong whitespace-nowrap text-ink'
                                    "
                                    >{{ word }}</span
                                >
                            </template>
                            <template v-else-if="part.kind === 'pattern'">
                                {{ part.label }}
                                <code
                                    class="rounded-tag bg-card-sunken px-2 py-[3px] font-mono text-meta-strong wrap-anywhere"
                                    >{{ part.pattern }}</code
                                >
                            </template>
                            <span v-else>{{ part.text }}</span>
                        </template>
                    </span>
                    <span
                        class="flex flex-wrap items-center gap-1.5 text-body-sm text-ink"
                    >
                        <span class="text-meta-strong text-ink-soft">{{
                            $t('Action:')
                        }}</span>
                        <span
                            >{{ actionPieces(filter).before
                            }}<strong
                                v-if="actionPieces(filter).name"
                                class="text-label"
                                >{{ actionPieces(filter).name }}</strong
                            >{{ actionPieces(filter).after }}</span
                        >
                    </span>
                    <span
                        v-if="filter.pattern !== null"
                        class="text-meta text-ink-soft"
                        >{{ advancedText(filter) }}</span
                    >
                    <span
                        v-if="filter.missing"
                        class="flex items-start gap-1.5 text-meta text-warning"
                    >
                        <TriangleAlert
                            aria-hidden="true"
                            class="mt-px size-4 shrink-0"
                        />
                        <span class="text-ink">{{ filter.missing }}</span>
                    </span>
                </div>
            </div>
            <div v-if="wide" class="flex shrink-0 items-center gap-2">
                <span class="mr-2 text-meta text-ink-soft">{{
                    takenText(filter)
                }}</span>
                <FmButton
                    v-if="filter.can.update && filter.id !== null"
                    variant="neutral"
                    row-action
                    :icon="Pencil"
                    :href="edit(filter.id).url"
                    >{{ $t('Edit') }}</FmButton
                >
                <FmDeleteButton
                    v-if="filter.can.delete && filter.id !== null"
                    :url="destroy(filter.id).url"
                    :question="
                        $t('Delete the filter :name?', { name: filter.name })
                    "
                    :text="$t('What it sorted stays as it is.')"
                    :confirm-label="$t('Delete the filter')"
                />
            </div>
        </li>
    </ul>
    <FmConfirmDelete
        v-if="deleting && deleting.id !== null"
        v-model:open="deleteOpen"
        :url="destroy(deleting.id).url"
        :question="$t('Delete the filter :name?', { name: deleting.name })"
        :text="$t('What it sorted stays as it is.')"
        :confirm-label="$t('Delete the filter')"
    />
</template>

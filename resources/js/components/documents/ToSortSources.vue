<script setup lang="ts">
/**
 * *Where they come from*, beside the list of *To sort* on the computer, as the
 * board *Da smistare-pc* draws it: each filter with its icon, its longer name
 * and how many files it has; the one chosen is light blue, and one with no
 * file cannot be chosen. Below the box, the row that opens *Received mail* and
 * how long the files stay. On the phone
 * and the tablet the page shows the same filters as tabs.
 */
import type { LucideIcon } from '@lucide/vue';
import { Inbox, Mail, ScanLine, Smartphone, Upload } from '@lucide/vue';
import { Link } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';
import FmIcon from '@/components/fm/FmIcon.vue';
import FmRow from '@/components/fm/FmRow.vue';
import { toSort } from '@/routes';
import { index as receivedMail } from '@/routes/mail';

const { filters, source } = defineProps<{
    /** The filters in order, with their names and how many files each has. */
    filters: App.Data.ToSortPageData['filters'];
    /** The filter chosen, `null` for *All*. */
    source: App.Enums.FileSource | null;
}>();

/** A filter as the box shows it. */
type Source = {
    /** The value of the filter, `null` for *All*. */
    value: string | null;
    /** The name in the box, longer than the one of the tabs. */
    label: string;
    /** The icon of where the files come from. */
    icon: LucideIcon;
    /** How many files it has. */
    count: number;
    /** The address of the page with the filter. */
    href: string;
    /** It is the filter chosen. */
    current: boolean;
    /** It has no file, and it is not *All*. */
    disabled: boolean;
};

/**
 * The name and the icon of a filter in the box: the tabs have shorter names.
 *
 * @param value - the value of the filter
 * @param label - the name the server gives it, for a source the box does not know
 * @returns the name and the icon
 */
function describe(
    value: string | null,
    label: string,
): { label: string; icon: LucideIcon } {
    switch (value) {
        case null:
            return { label, icon: Inbox };
        case 'mail':
            return { label: trans('Arrived by mail'), icon: Mail };
        case 'phone':
            return {
                label: trans('Uploaded from the phone'),
                icon: Smartphone,
            };
        case 'upload':
            return { label: trans('Uploaded manually'), icon: Upload };
        default:
            return { label, icon: ScanLine };
    }
}

/** The filters of the box. */
const sources = computed((): Source[] =>
    filters.map((filter) => ({
        ...describe(filter.value, filter.label),
        value: filter.value,
        count: filter.count,
        href:
            filter.value === null
                ? toSort().url
                : toSort({ query: { source: filter.value } }).url,
        current: source === filter.value,
        disabled: filter.count === 0 && filter.value !== null,
    })),
);
</script>

<template>
    <div class="flex flex-col gap-4">
        <nav
            :aria-label="$t('Where they come from')"
            class="flex flex-col gap-0.5 rounded-card bg-card p-3.5"
        >
            <span class="px-3 pt-1 pb-2 text-meta-strong text-ink-soft">{{
                $t('Where they come from')
            }}</span>
            <template v-for="item in sources" :key="item.value ?? 'all'">
                <span
                    v-if="item.disabled"
                    class="flex h-touch items-center gap-3 px-3 text-body text-ink-faint"
                >
                    <FmIcon :icon="item.icon" :size="20" />
                    <span class="grow">{{ item.label }}</span>
                    <span class="text-body-sm">{{ item.count }}</span>
                </span>
                <Link
                    v-else
                    :href="item.href"
                    :aria-current="item.current ? 'page' : undefined"
                    class="flex h-touch items-center gap-3 rounded-lg px-3 text-body no-underline"
                    :class="
                        item.current
                            ? 'bg-action-soft text-body-strong text-action-dark'
                            : 'text-ink hover:bg-canvas'
                    "
                >
                    <FmIcon
                        :icon="item.icon"
                        :size="20"
                        :class="item.current ? '' : 'text-ink-soft'"
                    />
                    <span class="grow">{{ item.label }}</span>
                    <!-- A space, so the link reads «All 5» -->
                    {{ ' ' }}
                    <span class="text-label text-ink-soft">{{
                        item.count
                    }}</span>
                </Link>
            </template>
        </nav>
        <div class="overflow-hidden rounded-card bg-card">
            <FmRow
                :title="$t('Received mail')"
                :subtitle="$t('The mails and what was done with them')"
                :href="receivedMail().url"
            >
                <template #before>
                    <span
                        aria-hidden="true"
                        class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-action-soft text-action-dark"
                    >
                        <FmIcon :icon="Mail" :size="18" />
                    </span>
                </template>
            </FmRow>
        </div>
        <p class="m-0 px-1 text-meta text-ink-soft">
            {{
                $t(
                    'The files that arrive by mail, from the phone or uploaded manually stay until you import or delete them.',
                )
            }}
        </p>
    </div>
</template>

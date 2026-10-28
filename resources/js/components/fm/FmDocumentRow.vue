<script setup lang="ts">
/**
 * A document in a list: the icon of the file, the title, what it is and whose,
 * and on the right its date (or its expiry). With `expiry` the line below is
 * the expiry instead, red once passed and orange while it is ahead, as at the
 * top of *A person*.
 */
import { FileText } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';
import { useFormat } from '@/composables/useFormat';
import { documentSubtitle } from '@/lib/documents';
import { isoDate } from '@/lib/format';
import FmIcon from './FmIcon.vue';
import FmRow from './FmRow.vue';
import type { RowTone } from './FmRow.vue';

const {
    document,
    href,
    expiry = false,
    today = isoDate(),
    current = false,
} = defineProps<{
    /** The document. */
    document: App.Data.DocumentData;
    /** Where the row leads; without it the row is not a link. */
    href?: string;
    /** The line below is the expiry, with its colour. */
    expiry?: boolean;
    /** The date of today, ISO: tells a passed expiry from one ahead. */
    today?: string;
    /** The document is the one open beside the list. */
    current?: boolean;
}>();

const { date } = useFormat();

/**
 * The date of today on this device, ISO.
 *
 * @returns for example `2026-10-27`
 */
function localToday(): string {
    const now = new Date();
    const pad = (n: number): string => String(n).padStart(2, '0');

    return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
}

/** The line below the title and its colour. */
const line = computed((): { text?: string; tone: RowTone } => {
    const expires = document.expiry_date;

    if (!expiry || expires === null) {
        return {
            text: documentSubtitle(document) || undefined,
            tone: 'normal',
        };
    }

    return expires < today
        ? {
              text: trans('Expired on :date', { date: date(expires) }),
              tone: 'overdue',
          }
        : {
              text: trans('Expires on :date', { date: date(expires) }),
              tone: 'warning',
          };
});

/** The date on the right: the date of the document, or else its expiry. */
const when = computed(() => {
    if (document.date) {
        return date(document.date);
    }

    return document.expiry_date
        ? trans('expires :date', { date: date(document.expiry_date) })
        : '';
});
</script>

<template>
    <FmRow
        :title="document.title"
        :subtitle="line.text"
        :tone="line.tone"
        :href="href"
        :current="current"
    >
        <template #before>
            <span
                class="flex h-[46px] w-[38px] shrink-0 items-center justify-center rounded-lg border border-edge bg-canvas text-ink-soft"
            >
                <FmIcon :icon="FileText" :size="20" />
            </span>
        </template>
        <template v-if="!expiry" #after>
            <span class="text-[13px] font-normal text-ink-soft">{{
                when
            }}</span>
        </template>
    </FmRow>
</template>

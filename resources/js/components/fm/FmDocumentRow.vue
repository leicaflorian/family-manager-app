<script setup lang="ts">
/**
 * A document in a list: the icon of the file, the title, what it is and whose,
 * and on the right its date (or its expiry). With `expiry` the line below is
 * the expiry instead, red once passed and orange while it is ahead, as at the
 * top of *A person*.
 *
 * @group rows
 */
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';
import { useFormat } from '@/composables/useFormat';
import { documentLine } from '@/lib/documents';
import { isoDate } from '@/lib/format';
import FmFileTile from './FmFileTile.vue';
import FmRow from './FmRow.vue';

const {
    document,
    href,
    external = false,
    expiry = false,
    dateless = false,
    today = isoDate(),
    current = false,
} = defineProps<{
    /** The document. */
    document: App.Data.DocumentData;
    /** Where the row leads; without it the row is not a link. */
    href?: string;
    /** With `href`: the row opens the address in a new tab. */
    external?: boolean;
    /** The line below is the expiry, with its colour. */
    expiry?: boolean;
    /** No date on the right, nor the arrow, where the room is short. */
    dateless?: boolean;
    /** The date of today, ISO: tells a passed expiry from one ahead. */
    today?: string;
    /** The document is the one open beside the list. */
    current?: boolean;
}>();

const { date } = useFormat();

/** The line below the title and its colour. */
const line = computed(() => documentLine(document, { expiry, today }));

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
        :external="external"
        :current="current"
        :arrow="!dateless"
    >
        <template #before>
            <FmFileTile />
        </template>
        <template v-if="!expiry && !dateless" #after>
            <span class="text-meta text-ink-soft">{{ when }}</span>
        </template>
    </FmRow>
</template>

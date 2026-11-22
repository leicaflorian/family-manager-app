<script setup lang="ts">
/**
 * A document pinned at the top of a person or an organization: a tile of its
 * own with the icon (the colours of its area, neutral without one), the title
 * and, below, its expiry (grey while ahead, red once passed) or else what it
 * is and its date. On the right, for who can change documents, the pin that
 * takes it off the top.
 *
 * @group rows
 */
import { Link } from '@inertiajs/vue3';
import { Pin } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';
import { areaIcon, areaOf, areaTile } from '@/lib/areas';
import { documentLine } from '@/lib/documents';
import type { DocumentLineTone } from '@/lib/documents';
import { isoDate } from '@/lib/format';
import { rowLink } from '@/lib/rows';
import { cn } from '@/lib/utils';
import FmIcon from './FmIcon.vue';

const {
    pinned,
    href,
    canUnpin = false,
    today = isoDate(),
} = defineProps<{
    /** The document with its area and the name of who pinned it. */
    pinned: App.Data.PinnedDocumentData;
    /** Where the tile leads: the document. */
    href: string;
    /** Shows the pin that takes the document off the top. */
    canUnpin?: boolean;
    /** The date of today, ISO: tells a passed expiry from one ahead. */
    today?: string;
}>();

const emit = defineEmits<{
    /** The person asked to take the document off the top. */
    unpin: [];
}>();

/** The area of the document, if it has a known one. */
const area = computed(() => areaOf(pinned.area));

/** The colours of the icon: those of the area, or neutral. */
const iconClass = computed(() =>
    area.value ? areaTile[area.value] : cn('bg-canvas text-ink-soft'),
);

/** The icon of the area, or the sheet of a document without one. */
const icon = computed(() => areaIcon(area.value ?? 'general'));

/** The line below the title: the expiry with its colour, or what it is and its date. */
const line = computed(() =>
    documentLine(pinned.document, {
        expiry: true,
        withDate: true,
        today,
        aheadTone: 'normal',
    }),
);

/** The colour of the line for its tone; `warning` is never asked here (an expiry ahead is grey) but the type of the tone has it. */
const toneClass: Record<DocumentLineTone, string> = {
    normal: cn('text-ink-soft'),
    overdue: cn('text-overdue'),
    warning: cn('text-warning'),
};

/** The hint of the pin: who pinned the document, when we know. */
const pinTitle = computed(() =>
    pinned.pinned_by
        ? trans('Marked by :name · remove from favourites', {
              name: pinned.pinned_by,
          })
        : trans('Favourite · remove from favourites'),
);
</script>

<template>
    <div
        class="flex min-w-0 items-center gap-2 rounded-card bg-card pr-2.5 shadow-card"
    >
        <Link
            :href="href"
            class="flex min-w-0 grow items-center gap-3 rounded-l-card px-3.5 py-3 text-ink no-underline"
            :class="rowLink"
        >
            <span
                data-testid="pinned-icon"
                class="flex size-9 shrink-0 items-center justify-center rounded-md"
                :class="iconClass"
            >
                <FmIcon :icon="icon" :size="20" />
            </span>
            <span class="flex min-w-0 flex-col gap-0.5">
                <span class="truncate text-row">{{
                    pinned.document.title
                }}</span>
                <span
                    v-if="line.text"
                    class="truncate text-meta"
                    :class="toneClass[line.tone]"
                    >{{ line.text }}</span
                >
            </span>
        </Link>
        <button
            v-if="canUnpin"
            type="button"
            :aria-label="
                trans('Remove from favourites «:title»', {
                    title: pinned.document.title,
                })
            "
            :title="pinTitle"
            aria-pressed="true"
            class="flex size-9 shrink-0 cursor-pointer items-center justify-center rounded-md bg-action-soft text-action focus-visible:outline-2 focus-visible:outline-action"
            @click="emit('unpin')"
        >
            <FmIcon :icon="Pin" :size="18" />
        </button>
    </div>
</template>

<script setup lang="ts">
/**
 * A document in a list: the icon of the file, the title, what it is and whose,
 * and on the right its date (or its expiry). With `expiry` the line below is
 * the expiry instead, red once passed and orange while it is ahead, as at the
 * top of *A person*. With `menu` the row has a ⋯ button beside its link with
 * the actions of the document; the parent's attributes and class then go on the
 * row, not on the wrapper, and a `current` highlight covers the whole wrapper,
 * under the ⋯ too. A pinned document shows a pin before its date.
 *
 * @group rows
 */
import { trans } from 'laravel-vue-i18n';
import { createReusableTemplate } from '@vueuse/core';
import { computed } from 'vue';
import {
    ChevronRight,
    Download,
    ExternalLink,
    Pencil,
    Pin,
    PinOff,
} from '@lucide/vue';
import { useDocumentPin } from '@/composables/useDocumentPin';
import type { PinVisitOptions } from '@/composables/useDocumentPin';
import { useFormat } from '@/composables/useFormat';
import { cn } from '@/lib/utils';
import { documentLine } from '@/lib/documents';
import { isoDate } from '@/lib/format';
import { edit } from '@/routes/documents';
import FmIcon from './FmIcon.vue';
import FmFileTile from './FmFileTile.vue';
import FmMenu from './FmMenu.vue';
import FmMenuItem from './FmMenuItem.vue';
import FmRow from './FmRow.vue';

defineOptions({ inheritAttrs: false });

const {
    document,
    href,
    external = false,
    expiry = false,
    dateless = false,
    today = isoDate(),
    current = false,
    menu = false,
    pinOptions = undefined,
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
    /** A ⋯ menu beside the row, outside its link, in place of the arrow: Pin, Download, Open in a new tab, Edit. */
    menu?: boolean;
    /** With `menu`: what the pin visit adds, where the page must reload more than the default (the pinned and the others of a person). */
    pinOptions?: PinVisitOptions;
}>();

const { date } = useFormat();
const { togglePin, pinLabel } = useDocumentPin();

/** The row, written once for both layouts (with the menu beside it or alone). */
const [DefineRow, UseRow] = createReusableTemplate();

/** The row would show the arrow: a link with nothing on the right (the pin then carries it in the slot). */
const showArrow = computed(() => !menu && !dateless);

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
    <DefineRow>
        <FmRow
            v-bind="$attrs"
            :class="menu && 'min-w-0 grow pr-2'"
            :title="document.title"
            :subtitle="line.text"
            :tone="line.tone"
            :href="href"
            :external="external"
            :current="current"
            :highlight="!menu"
            :arrow="showArrow"
        >
            <template #before>
                <FmFileTile />
            </template>
            <template v-if="(!expiry && !dateless) || document.pinned" #after>
                <span class="inline-flex shrink-0 items-center gap-1.5">
                    <template v-if="document.pinned">
                        <Pin
                            :size="14"
                            class="text-action"
                            aria-hidden="true"
                        />
                        <span class="sr-only">{{
                            $t('Pinned at the top')
                        }}</span>
                    </template>
                    <span
                        v-if="!expiry && !dateless"
                        class="text-meta text-ink-soft"
                        >{{ when }}</span
                    >
                    <FmIcon
                        v-if="showArrow && document.pinned"
                        :icon="ChevronRight"
                        :size="20"
                        class="text-ink-faint"
                    />
                </span>
            </template>
        </FmRow>
    </DefineRow>
    <div
        v-if="menu"
        :class="cn('flex items-center', current && 'bg-action-soft')"
    >
        <UseRow />
        <FmMenu
            row
            :label="$t('More actions for «:title»', { title: document.title })"
        >
            <FmMenuItem
                v-if="document.can_pin"
                :icon="document.pinned ? PinOff : Pin"
                @select="togglePin(document, pinOptions)"
            >
                {{ pinLabel(document.pinned) }}
            </FmMenuItem>
            <FmMenuItem :icon="Download" :href="document.download_url" download>
                {{ $t('Download') }}
            </FmMenuItem>
            <FmMenuItem :icon="ExternalLink" :href="document.file_url" external>
                {{ $t('Open in a new tab') }}
            </FmMenuItem>
            <FmMenuItem
                v-if="document.can_edit"
                :icon="Pencil"
                :href="edit.url(document.id)"
            >
                {{ $t('Edit') }}
            </FmMenuItem>
        </FmMenu>
    </div>
    <UseRow v-else />
</template>

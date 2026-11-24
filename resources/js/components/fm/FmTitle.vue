<script setup lang="ts">
/**
 * The title at the top of a page: 28 px on the phone and the tablet, 32 px on the
 * computer, with the sentence below and, on the right and centred on them, the
 * actions of the page. It has no link back: the layout draws it above the page
 * (`FmPageTrail`). On a section page on the phone the round search and the bell
 * (`FmTitleTools`) follow the actions. Inside a frame with its own title on top
 * (`SECTION_TITLE`, the settings in the wide layout) it is the title of a
 * section: an `h2`, smaller, without the tools. With the key of the page in
 * `pageInfo.ts` (`info`, or given by `usePageMeta`) the «i» of `FmPageInfo`
 * stands right after the title, on its line.
 *
 * @group basics
 */
import { usePage } from '@inertiajs/vue3';
import { computed, inject, ref } from 'vue';
import { useLayoutMode } from '@/composables/useLayoutMode';
import type { PageInfoKey } from '@/lib/pageInfo';
import { isSectionPage } from '@/lib/pageTrail';
import { PAGE_INFO, SECTION_TITLE } from '@/lib/titles';
import FmPageInfo from './FmPageInfo.vue';
import FmTitleTools from './FmTitleTools.vue';

const { title, subtitle, info } = defineProps<{
    /** The title of the page. */
    title: string;
    /** The sentence below the title. */
    subtitle?: string;
    /** The key of the explanation of the page, for a title outside the page (`FmFamilyViews`); otherwise `usePageMeta` gives it. */
    info?: PageInfoKey;
}>();

defineSlots<{
    /** The buttons on the right of the title, as `FmActions`. */
    actions?: () => unknown;
    /** Something right of the title, on the same line: the tabs of the Agenda. */
    beside?: () => unknown;
    /** The sentence below the title, instead of `subtitle`: for one with a button in it. */
    subtitle?: () => unknown;
    /** Above the title, in its column: the area of a card, the role of a person. */
    overline?: () => unknown;
    /** Before the column of the title, centred on it: an avatar. */
    leading?: () => unknown;
}>();

/** Whether the title is the one of a section, under the title of the frame. */
const section = inject(SECTION_TITLE, ref(false));

/** The key of the explanation the page gives, if any. */
const pageKey = inject(PAGE_INFO, ref(undefined));

/** The key of the «i» beside the title: the prop, else the one of the page. */
const infoKey = computed((): PageInfoKey | undefined => info ?? pageKey.value);

const page = usePage();
const mode = useLayoutMode();

/** Whether the search and the bell go beside the title: section pages on the phone. */
const tools = computed(
    (): boolean =>
        !section.value &&
        mode.value === 'phone' &&
        isSectionPage(page.component),
);
</script>

<template>
    <!-- The title in one block. It is the container the title measures: on a page
         with a panel beside it the column is narrower than the screen. -->
    <div class="@container/title flex flex-col gap-5">
        <!-- The actions never go below the title: they take the room the title
             leaves, at least 40 % of the row, and put in the ⋯ what does not fit.
             With something beside, title and actions stay on the first line and
             what is beside goes on its own line below, until the row has room
             for all three. -->
        <div
            :class="
                $slots.beside
                    ? 'flex flex-wrap items-center gap-x-5 gap-y-3'
                    : 'flex items-center justify-between gap-4'
            "
        >
            <div
                :class="
                    $slots.beside
                        ? 'contents'
                        : [
                              'flex min-w-0 flex-wrap items-center gap-x-5 gap-y-3',
                              ($slots.actions || tools) &&
                                  'max-w-[60%] shrink-0',
                          ]
                "
            >
                <div class="flex min-w-0 items-center gap-4">
                    <slot name="leading" />
                    <div class="flex min-w-0 flex-col gap-1">
                        <slot name="overline" />
                        <div class="flex min-w-0 items-center gap-1.5">
                            <h2 v-if="section" class="m-0 text-sheet-title">
                                {{ title }}
                            </h2>
                            <h1
                                v-else
                                class="m-0 text-title-mobile computer:text-title"
                            >
                                {{ title }}
                            </h1>
                            <FmPageInfo v-if="infoKey" :info-key="infoKey" />
                        </div>
                        <span
                            v-if="$slots.subtitle || subtitle"
                            class="text-body-sm text-ink-soft"
                        >
                            <slot name="subtitle">{{ subtitle }}</slot>
                        </span>
                    </div>
                </div>
                <div
                    v-if="$slots.beside"
                    class="order-last w-full @3xl/title:order-none @3xl/title:w-auto"
                >
                    <slot name="beside" />
                </div>
            </div>
            <div
                v-if="$slots.actions || tools"
                class="flex items-center justify-end gap-2.5"
                :class="$slots.beside ? 'ml-auto shrink-0' : 'min-w-0 flex-1'"
            >
                <slot name="actions" />
                <FmTitleTools v-if="tools" />
            </div>
        </div>
    </div>
</template>

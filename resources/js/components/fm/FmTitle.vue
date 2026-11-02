<script setup lang="ts">
/**
 * The title at the top of a page: 28 px on the phone, 32 px on the computer, with
 * the sentence below and, on the right and centred on them, the actions of the
 * page. Above it, the link back to the page it comes from, with that page's
 * name, always 20 px above the title like on the pages of a person and an
 * organization.
 *
 * @group basics
 */
import FmBackLink from './FmBackLink.vue';

const { title, subtitle, back, backLabel } = defineProps<{
    /** The title of the page. */
    title: string;
    /** The sentence below the title. */
    subtitle?: string;
    /** The address of the page it comes from. */
    back?: string;
    /** The name of the page it comes from, the text of the link back. */
    backLabel?: string;
}>();

defineSlots<{
    /** The buttons on the right of the title, as `FmActions`. */
    actions?: () => unknown;
    /** Something right of the title, on the same line: the tabs of the Agenda. */
    beside?: () => unknown;
}>();
</script>

<template>
    <!-- The link back and the title in one block, so the space between them is
         the same on every page whatever the gap of the page. It is the container
         the title measures: on a page with a panel beside it the column is
         narrower than the screen. -->
    <div class="@container/title flex flex-col gap-5">
        <FmBackLink v-if="back && backLabel" :href="back" :label="backLabel" />
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
                              $slots.actions && 'max-w-[60%] shrink-0',
                          ]
                "
            >
                <div class="flex min-w-0 flex-col gap-1">
                    <h1
                        class="m-0 text-[28px] leading-tight font-extrabold lg:text-[32px]"
                    >
                        {{ title }}
                    </h1>
                    <span
                        v-if="subtitle"
                        class="text-sm text-ink-soft lg:text-[15px]"
                        >{{ subtitle }}</span
                    >
                </div>
                <div
                    v-if="$slots.beside"
                    class="order-last w-full @3xl/title:order-none @3xl/title:w-auto"
                >
                    <slot name="beside" />
                </div>
            </div>
            <div
                v-if="$slots.actions"
                class="flex items-center justify-end gap-2.5"
                :class="$slots.beside ? 'ml-auto shrink-0' : 'min-w-0 flex-1'"
            >
                <slot name="actions" />
            </div>
        </div>
    </div>
</template>

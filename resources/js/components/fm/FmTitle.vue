<script setup lang="ts">
/**
 * The title at the top of a page: 28 px on the phone, 32 px on the computer, with
 * the sentence below and, on the right and centred on them, the actions of the
 * page. Above it, the link back to the page it comes from, with that page's
 * name, always 20 px above the title like on the pages of a person and an
 * organization.
 */
import { Link } from '@inertiajs/vue3';
import { ChevronLeft } from '@lucide/vue';
import FmIcon from './FmIcon.vue';

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
}>();
</script>

<template>
    <!-- The link back and the title in one block, so the space between them is
         the same on every page whatever the gap of the page. -->
    <div class="flex flex-col gap-5">
        <Link
            v-if="back && backLabel"
            :href="back"
            class="flex items-center gap-1.5 self-start text-[15px] font-semibold no-underline"
        >
            <FmIcon :icon="ChevronLeft" :size="18" />{{ backLabel }}
        </Link>
        <!-- The actions never go below the title: they take the room the title
             leaves, at least 40 % of the row, and put in the ⋯ what does not fit -->
        <div class="flex items-center justify-between gap-4">
            <div
                class="flex min-w-0 flex-col gap-1"
                :class="$slots.actions && 'max-w-[60%] shrink-0'"
            >
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
                v-if="$slots.actions"
                class="flex min-w-0 flex-1 items-center justify-end gap-2.5"
            >
                <slot name="actions" />
            </div>
        </div>
    </div>
</template>

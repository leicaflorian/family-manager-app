<script setup lang="ts">
/**
 * The title at the top of a page: 28 px on the phone, 32 px on the computer, with
 * the sentence below and, on the right, the actions of the page. Above it, the
 * link back to the page it comes from, with that page's name.
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
    /** The buttons on the right of the title. */
    actions?: () => unknown;
}>();
</script>

<template>
    <Link
        v-if="back && backLabel"
        :href="back"
        class="flex items-center gap-1.5 self-start text-[15px] font-semibold no-underline"
    >
        <FmIcon :icon="ChevronLeft" :size="18" />{{ backLabel }}
    </Link>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="flex flex-col gap-1">
            <h1 class="m-0 text-[28px] font-extrabold lg:text-[32px]">
                {{ title }}
            </h1>
            <span
                v-if="subtitle"
                class="text-sm text-ink-soft lg:text-[15px]"
                >{{ subtitle }}</span
            >
        </div>
        <div v-if="$slots.actions" class="flex flex-wrap gap-2.5">
            <slot name="actions" />
        </div>
    </div>
</template>

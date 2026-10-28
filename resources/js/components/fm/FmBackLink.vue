<script setup lang="ts">
/**
 * The link back to the page a page comes from, with a chevron and that page's
 * name, above the title or the header of a page.
 */
import { Link } from '@inertiajs/vue3';
import { ChevronLeft } from '@lucide/vue';
import FmIcon from './FmIcon.vue';

const { href, label } = defineProps<{
    /** The address of the page it comes from. */
    href: string;
    /** The name of that page, the text of the link. */
    label: string;
}>();
</script>

<template>
    <Link
        :href="href"
        class="flex items-center gap-1.5 self-start text-[15px] font-semibold no-underline"
    >
        <FmIcon :icon="ChevronLeft" :size="18" />{{ label }}
    </Link>
</template>

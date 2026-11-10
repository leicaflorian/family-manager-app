<script setup lang="ts">
/**
 * The link back to the page a page comes from, with a chevron and that page's
 * name, above the title or the header of a page. On the computer it is not drawn: the
 * top bar has Back and the trail.
 *
 * @group navigation
 */
import { Link } from '@inertiajs/vue3';
import { ChevronLeft } from '@lucide/vue';
import { useLayoutMode } from '@/composables/useLayoutMode';
import FmIcon from './FmIcon.vue';

const { href, label } = defineProps<{
    /** The address of the page it comes from. */
    href: string;
    /** The name of that page, the text of the link. */
    label: string;
}>();

const layout = useLayoutMode();
</script>

<template>
    <Link
        v-if="layout !== 'computer'"
        :href="href"
        class="flex items-center gap-1.5 self-start text-row no-underline"
    >
        <FmIcon :icon="ChevronLeft" :size="18" />{{ label }}
    </Link>
</template>

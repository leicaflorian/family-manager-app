<script setup lang="ts">
/**
 * The link back to the page a page comes from, with a chevron and that page's
 * name, above the title or the header of a page. On the computer it is not drawn: the
 * top bar has Back and the trail. Without `href` it is a button that emits `click`, for a
 * page that asks something before leaving (*Scan*, with pages not uploaded).
 *
 * @group navigation
 */
import { Link } from '@inertiajs/vue3';
import { ChevronLeft } from '@lucide/vue';
import { useLayoutMode } from '@/composables/useLayoutMode';
import FmIcon from './FmIcon.vue';

const { href, label } = defineProps<{
    /** The address of the page it comes from; without it, a button. */
    href?: string;
    /** The name of that page, the text of the link. */
    label: string;
}>();

defineEmits<{
    /** The button was pressed (only without `href`). */
    click: [];
}>();

const layout = useLayoutMode();
</script>

<template>
    <template v-if="layout !== 'computer'">
        <Link
            v-if="href"
            :href="href"
            class="flex items-center gap-1.5 self-start text-row no-underline"
        >
            <FmIcon :icon="ChevronLeft" :size="18" />{{ label }}
        </Link>
        <button
            v-else
            type="button"
            class="flex items-center gap-1.5 self-start border-0 bg-transparent p-0 text-row"
            @click="$emit('click')"
        >
            <FmIcon :icon="ChevronLeft" :size="18" />{{ label }}
        </button>
    </template>
</template>

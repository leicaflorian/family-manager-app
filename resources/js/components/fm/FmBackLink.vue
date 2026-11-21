<script setup lang="ts">
/**
 * The link back to the page a page comes from, with a chevron and that page's
 * name, above the title or the header of a page. It always draws: the caller
 * decides where it shows (the phone). Without `href` it is a button; both emit
 * `click` with the event, so a page can decide where to go (*Scan*, with pages
 * not uploaded; `FmPageTrail`, which goes back in the history).
 *
 * @group navigation
 */
import { Link } from '@inertiajs/vue3';
import { ChevronLeft } from '@lucide/vue';
import FmIcon from './FmIcon.vue';

const {
    href,
    label,
    handled = false,
} = defineProps<{
    /** The address of the page it comes from; without it, a button. */
    href?: string;
    /** The name of that page, the text of the link. */
    label: string;
    /** The caller handles the click (`click`) and the visit: a plain link, so Inertia does not visit as well. */
    handled?: boolean;
}>();

defineEmits<{
    /** The link or the button was pressed. */
    click: [event: MouseEvent];
}>();
</script>

<template>
    <a
        v-if="href && handled"
        :href="href"
        class="flex items-center gap-1.5 self-start text-row no-underline"
        @click="$emit('click', $event)"
    >
        <FmIcon :icon="ChevronLeft" :size="18" />{{ label }}
    </a>
    <Link
        v-else-if="href"
        :href="href"
        class="flex items-center gap-1.5 self-start text-row no-underline"
        @click="$emit('click', $event)"
    >
        <FmIcon :icon="ChevronLeft" :size="18" />{{ label }}
    </Link>
    <button
        v-else
        type="button"
        class="flex items-center gap-1.5 self-start border-0 bg-transparent p-0 text-row"
        @click="$emit('click', $event)"
    >
        <FmIcon :icon="ChevronLeft" :size="18" />{{ label }}
    </button>
</template>

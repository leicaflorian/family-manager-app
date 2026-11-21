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
import { cn } from '@/lib/utils';
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

/** The look of the link, the same whether it is a link or a button. */
const baseClass = cn('flex items-center gap-1.5 self-start text-row');
/** A link: no underline. */
const linkClass = cn(baseClass, 'no-underline');
/** A button: no border or background of its own. */
const buttonClass = cn(baseClass, 'border-0 bg-transparent p-0');
</script>

<template>
    <a
        v-if="href && handled"
        :href="href"
        :class="linkClass"
        @click="$emit('click', $event)"
    >
        <FmIcon :icon="ChevronLeft" :size="18" />{{ label }}
    </a>
    <Link
        v-else-if="href"
        :href="href"
        :class="linkClass"
        @click="$emit('click', $event)"
    >
        <FmIcon :icon="ChevronLeft" :size="18" />{{ label }}
    </Link>
    <button
        v-else
        type="button"
        :class="buttonClass"
        @click="$emit('click', $event)"
    >
        <FmIcon :icon="ChevronLeft" :size="18" />{{ label }}
    </button>
</template>

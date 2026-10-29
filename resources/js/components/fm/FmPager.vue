<script setup lang="ts">
/**
 * *Previous* and *Next* under a list shown a page at a time (the documents,
 * the documents of a card); nothing when the list fits in one page.
 */
import { Link } from '@inertiajs/vue3';

const {
    previous,
    next,
    preserveScroll = false,
} = defineProps<{
    /** The address of the previous page, null on the first. */
    previous: string | null;
    /** The address of the next page, null on the last. */
    next: string | null;
    /** Keeps the scroll where it is, for a list inside a longer page. */
    preserveScroll?: boolean;
}>();
</script>

<template>
    <nav
        v-if="next || previous"
        :aria-label="$t('Pages')"
        class="flex justify-between gap-3"
    >
        <Link
            v-if="previous"
            :href="previous"
            :preserve-scroll="preserveScroll"
            class="font-semibold"
            >{{ $t('Previous') }}</Link
        >
        <span v-else />
        <Link
            v-if="next"
            :href="next"
            :preserve-scroll="preserveScroll"
            class="font-semibold"
            >{{ $t('Next') }}</Link
        >
    </nav>
</template>

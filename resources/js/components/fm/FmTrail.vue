<script setup lang="ts">
/**
 * The trail of the top bar: the pages above this one as links, separated by
 * chevrons, and the current page last, cut with an ellipsis when it is too long.
 * Shows nothing when there are no pieces.
 *
 * @group navigation
 */
import { Link } from '@inertiajs/vue3';
import { ChevronRight } from '@lucide/vue';
import type { TrailPiece } from '@/lib/pageTrail';
import FmIcon from './FmIcon.vue';

const { pieces } = defineProps<{
    /** The pieces in order; the last one is the current page and has no `href`. */
    pieces: TrailPiece[];
}>();
</script>

<template>
    <nav
        v-if="pieces.length > 0"
        :aria-label="$t('Where you are')"
        class="min-w-0"
    >
        <ol class="m-0 flex min-w-0 list-none items-center gap-1.5 p-0">
            <template v-for="(piece, index) in pieces" :key="index">
                <li
                    v-if="index < pieces.length - 1"
                    class="flex shrink-0 items-center gap-1.5"
                >
                    <Link
                        v-if="piece.href"
                        :href="piece.href"
                        class="inline-flex min-h-11 shrink-0 items-center text-body-sm text-ink-soft no-underline"
                    >
                        {{ piece.label }}
                    </Link>
                    <span v-else class="shrink-0 text-body-sm text-ink-soft">{{
                        piece.label
                    }}</span>
                    <FmIcon
                        :icon="ChevronRight"
                        :size="16"
                        class="text-ink-faint"
                        aria-hidden="true"
                    />
                </li>
                <li v-else class="min-w-0">
                    <span
                        aria-current="page"
                        class="block min-w-0 truncate text-label text-ink"
                    >
                        {{ piece.label }}
                    </span>
                </li>
            </template>
        </ol>
    </nav>
</template>

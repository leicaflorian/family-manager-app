<script setup lang="ts">
/**
 * Back and the trail above the page on the phone and the tablet, as the boards
 * *Layout-telefono*, *Layout-tablet* and *Scheda-tablet* draw them (the computer
 * has them in the top bar). On the phone: the link to the page above, with its
 * name (`FmBackLink`). On the tablet: a row with the 36 px square Back (with
 * border and an 18 px arrow) and the trail (`FmTrail`). Back goes to the
 * previous page of the app, or to the page above without a history
 * (`usePageBack`). Nothing on the page of a section, with fewer than 2 pieces in
 * the trail, or on the phone without a page above.
 *
 * @group navigation
 */
import { ArrowLeft } from '@lucide/vue';
import { usePageBack } from '@/composables/usePageBack';
import type { TrailPiece } from '@/lib/pageTrail';
import FmBackLink from './FmBackLink.vue';
import FmIcon from './FmIcon.vue';
import FmTrail from './FmTrail.vue';

defineOptions({ inheritAttrs: false });

const { mode, trail, section } = defineProps<{
    /** The layout of the window: the link of the phone or the row of the tablet. */
    mode: 'phone' | 'tablet';
    /** The pieces of the trail; the last one is the open page. */
    trail: TrailPiece[];
    /** The open page is the page of a section: nothing is drawn. */
    section: boolean;
}>();

const { parent, visible, back } = usePageBack(
    () => trail,
    () => section,
);
</script>

<template>
    <template v-if="!section && trail.length >= 2">
        <FmBackLink
            v-if="mode === 'phone' && parent"
            v-bind="$attrs"
            :href="parent.href"
            :label="parent.label"
            handled
            @click="back"
        />
        <div
            v-else-if="mode === 'tablet'"
            v-bind="$attrs"
            class="flex min-w-0 items-center gap-2.5"
        >
            <component
                :is="parent?.href ? 'a' : 'button'"
                v-if="visible()"
                :href="parent?.href"
                :type="parent?.href ? undefined : 'button'"
                :aria-label="$t('Back')"
                :title="$t('Back')"
                class="relative flex size-9 shrink-0 items-center justify-center rounded-md border border-edge-soft bg-card text-ink"
                @click="back"
            >
                <FmIcon :icon="ArrowLeft" :size="18" />
            </component>
            <FmTrail :pieces="trail" />
        </div>
    </template>
</template>

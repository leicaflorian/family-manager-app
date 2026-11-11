<script setup lang="ts">
/**
 * The blue card *Take a photo of a sheet* of *Upload a document*: a link to the
 * scan, where the photos are cropped and improved before they wait in *To sort*.
 * On the phone it is a row with an arrow; `stacked` makes it the centred column
 * of the tablet, beside the drop zone.
 */
import { Link } from '@inertiajs/vue3';
import { Camera, ChevronRight } from '@lucide/vue';
import FmIcon from '@/components/fm/FmIcon.vue';
import { cn } from '@/lib/utils';
import { scan } from '@/routes';

const { stacked = false } = defineProps<{
    /** The column of the tablet (big circle, centred) instead of the row of the phone. */
    stacked?: boolean;
}>();
</script>

<template>
    <Link
        :href="scan().url"
        :class="
            cn(
                'flex items-center rounded-card-lg bg-action text-on-action no-underline',
                stacked
                    ? 'min-h-[420px] flex-col justify-center gap-3 p-8 text-center'
                    : 'min-h-[88px] gap-3.5 px-4.5 py-4',
            )
        "
    >
        <span
            :class="
                cn(
                    'flex shrink-0 items-center justify-center rounded-full bg-on-hero-soft',
                    stacked ? 'size-[72px]' : 'size-[52px]',
                )
            "
        >
            <FmIcon :icon="Camera" :size="stacked ? 34 : 26" />
        </span>
        <span :class="cn('flex min-w-0 flex-col gap-0.5', !stacked && 'grow')">
            <span :class="stacked ? 'text-title-sm' : 'text-body-strong'">{{
                $t('Take a photo of a sheet')
            }}</span>
            <span :class="stacked ? 'text-body-sm' : 'text-meta'">{{
                $t(
                    'You crop and improve the photos, then you find it in To sort',
                )
            }}</span>
        </span>
        <FmIcon
            v-if="!stacked"
            :icon="ChevronRight"
            :size="20"
            class="shrink-0"
            data-testid="photo-sheet-arrow"
        />
    </Link>
</template>

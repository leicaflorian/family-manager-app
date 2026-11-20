<script setup lang="ts">
/**
 * The blue card *Take a photo of a sheet* of *Upload a document*: a link to the
 * scan, where the photos are cropped and improved before they wait in *To sort*.
 * On the phone it is a row with an arrow; `stacked` makes it the centred column
 * of the tablet, beside the drop zone. `scanner` makes it the card of the home
 * scanner on the computer (board `Carica-pc`): the same column, a link to *Scan
 * the sheets*, white with a thin edge as a secondary action, because there the
 * drop zone is the main one.
 */
import { Link } from '@inertiajs/vue3';
import { Camera, ChevronRight, ScanLine } from '@lucide/vue';
import { computed } from 'vue';
import FmIcon from '@/components/fm/FmIcon.vue';
import { cn } from '@/lib/utils';
import { scan, scanner as scannerPage } from '@/routes';

const { stacked = false, scanner = false } = defineProps<{
    /** The column of the tablet (big circle, centred) instead of the row of the phone. */
    stacked?: boolean;
    /** The card of the home scanner of the computer, always a column. */
    scanner?: boolean;
}>();

/** A column, as the tablet and the scanner draw it. */
const column = computed((): boolean => stacked || scanner);
</script>

<template>
    <Link
        :href="scanner ? scannerPage().url : scan().url"
        :class="
            cn(
                'flex items-center rounded-card-lg no-underline',
                scanner
                    ? 'border border-edge-soft bg-card text-ink shadow-card hover:bg-canvas'
                    : 'bg-action text-on-action',
                column
                    ? 'min-h-[420px] flex-col justify-center gap-3 p-8 text-center'
                    : 'min-h-[88px] gap-3.5 px-4.5 py-4',
            )
        "
    >
        <span
            :class="
                cn(
                    'flex shrink-0 items-center justify-center rounded-full',
                    scanner ? 'bg-action-soft text-action' : 'bg-on-hero-soft',
                    column ? 'size-[72px]' : 'size-[52px]',
                )
            "
        >
            <FmIcon
                :icon="scanner ? ScanLine : Camera"
                :size="column ? 34 : 26"
            />
        </span>
        <span :class="cn('flex min-w-0 flex-col gap-0.5', !column && 'grow')">
            <span :class="column ? 'text-subtitle' : 'text-body-strong'">{{
                scanner
                    ? $t('Scan the sheets with the scanner')
                    : $t('Take a photo of a sheet')
            }}</span>
            <span
                :class="
                    cn(
                        column ? 'text-body-sm' : 'text-meta',
                        scanner && 'text-ink-soft',
                    )
                "
                >{{
                    scanner
                        ? $t(
                              'Front and back of all the sheets, then you find them in To sort',
                          )
                        : $t(
                              'You crop and improve the photos, then you find it in To sort',
                          )
                }}</span
            >
        </span>
        <FmIcon
            v-if="!column"
            :icon="ChevronRight"
            :size="20"
            class="shrink-0"
            data-testid="photo-sheet-arrow"
        />
    </Link>
</template>

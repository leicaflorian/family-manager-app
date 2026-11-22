<script setup lang="ts">
/**
 * The square thumbnail of a drawing in the gallery: the picture, a mark with
 * the number of pages when the file has more than one, and a full heart when
 * it is a favourite. Clicking it opens the drawing.
 *
 * @group files
 */
import { Copy, Heart } from '@lucide/vue';
import FmIcon from './FmIcon.vue';

const { drawing, selected = false } = defineProps<{
    /** The drawing. */
    drawing: App.Data.DrawingData;
    /** It is the drawing open beside the grid: a ring around it. */
    selected?: boolean;
}>();

const emit = defineEmits<{
    /** The tile was clicked: the id of the drawing to open. */
    open: [id: number];
}>();
</script>

<template>
    <button
        type="button"
        :aria-current="selected ? 'true' : undefined"
        class="relative block w-full cursor-pointer rounded-md border-0 bg-transparent p-0 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-action"
        :class="selected && 'outline-3 outline-offset-2 outline-action'"
        @click="emit('open', drawing.id)"
    >
        <img
            :src="drawing.image_url"
            :alt="drawing.title"
            loading="lazy"
            class="aspect-square w-full rounded-md bg-canvas object-cover"
        />
        <span
            v-if="drawing.pages !== null && drawing.pages > 1"
            data-pages
            class="absolute right-1.5 bottom-1.5 inline-flex h-[22px] items-center gap-[3px] rounded-full bg-card px-[7px] text-badge text-ink shadow-raised"
        >
            <FmIcon :icon="Copy" :size="12" :stroke="2.5" />
            <span aria-hidden="true">{{ drawing.pages }}</span>
            <span class="sr-only">{{
                $tChoice('{1} :count page|[2,*] :count pages', drawing.pages)
            }}</span>
        </span>
        <span
            v-if="drawing.is_favorite"
            class="absolute top-1.5 right-1.5 flex size-[26px] items-center justify-center rounded-full bg-card text-overdue shadow-raised"
        >
            <Heart
                :size="15"
                :stroke-width="2"
                fill="currentColor"
                aria-hidden="true"
            />
            <span class="sr-only">{{ $t('Favourite') }}</span>
        </span>
    </button>
</template>

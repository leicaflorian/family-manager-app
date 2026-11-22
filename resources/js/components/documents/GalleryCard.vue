<script setup lang="ts">
/**
 * The card of the drawings gallery on *Documents*: the palette, *Drawings and
 * crafts*, how many drawings, by whom and the last upload, and the newest
 * pictures. Without drawings yet it says so and still leads to the gallery,
 * where the first one is added.
 *
 * @group sections
 */
import { Link } from '@inertiajs/vue3';
import { ChevronRight, Palette } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';
import FmIcon from '@/components/fm/FmIcon.vue';
import { useFormat } from '@/composables/useFormat';

const { gallery } = defineProps<{
    /** The card as the server sends it. */
    gallery: App.Data.GalleryTeaserData;
}>();

const { date } = useFormat();

/** The line under the title: how many, by whom and the last upload, or the invitation when there are none. */
const summary = computed((): string => {
    if (gallery.count === 0) {
        return trans('No drawings yet · add the first one');
    }

    const people = gallery.people.join(', ');
    const count = String(gallery.count);
    const last = gallery.last_uploaded_on
        ? date(gallery.last_uploaded_on)
        : null;

    if (people && last) {
        return trans(':count by :people · the last on :date', {
            count,
            people,
            date: last,
        });
    }

    if (people) {
        return trans(':count by :people', { count, people });
    }

    return last
        ? trans(':count · the last on :date', { count, date: last })
        : count;
});

/** How many drawings the thumbnails do not show. */
const more = computed((): number =>
    Math.max(0, gallery.count - gallery.thumbnails.length),
);
</script>

<template>
    <Link
        :href="gallery.url"
        class="flex flex-col gap-3 rounded-card bg-card px-4 py-3.5 text-ink no-underline shadow-card wide:flex-row wide:items-center wide:gap-4"
    >
        <span class="flex min-w-0 grow items-center gap-3">
            <span
                aria-hidden="true"
                class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-school-soft text-school-text"
                ><FmIcon :icon="Palette" :size="22"
            /></span>
            <span class="flex min-w-0 grow flex-col gap-0.5">
                <span class="text-body-strong">{{
                    $t('Drawings and crafts')
                }}</span>
                <span class="text-meta text-ink-soft">{{ summary }}</span>
            </span>
            <FmIcon
                :icon="ChevronRight"
                :size="20"
                class="text-ink-soft wide:hidden"
            />
        </span>
        <span v-if="gallery.thumbnails.length > 0" class="flex gap-2">
            <img
                v-for="thumbnail in gallery.thumbnails"
                :key="thumbnail"
                :src="thumbnail"
                alt=""
                class="size-[58px] shrink-0 rounded-md bg-segment object-cover wide:size-14"
            />
            <span
                v-if="more > 0"
                aria-hidden="true"
                class="flex size-[58px] shrink-0 items-center justify-center rounded-md bg-segment text-label text-ink-soft wide:size-14"
                >+{{ more }}</span
            >
        </span>
        <FmIcon
            :icon="ChevronRight"
            :size="20"
            class="hidden text-ink-soft wide:block"
        />
    </Link>
</template>

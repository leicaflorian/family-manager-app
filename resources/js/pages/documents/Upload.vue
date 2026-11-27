<script setup lang="ts">
/**
 * *Upload a document*: one or more files (PDF, JPG, PNG, HEIC, up to 25 MB
 * each) are chosen or dragged here and sent. The server keeps them waiting,
 * starts their reading and opens *Import more files* with them, where each is
 * checked and filed as a document: this page only takes the files. The card
 * the upload starts from travels with them. The layout draws the way
 * back above the page (the trail). The phone and the tablet offer *Take a photo of a sheet*
 * beside the drop zone (above it on the phone); a Mac or a Windows PC offers the
 * home scanner beside it instead, as a secondary card; any other computer has
 * only the zone.
 */
import { Head } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';
import PhotoSheetCard from '@/components/documents/PhotoSheetCard.vue';
import FmFileDrop from '@/components/fm/FmFileDrop.vue';
import FmNotice from '@/components/fm/FmNotice.vue';
import FmTitle from '@/components/fm/FmTitle.vue';
import FmToSortLink from '@/components/fm/FmToSortLink.vue';
import { useFileUpload } from '@/composables/useFileUpload';
import { useToSortCount } from '@/composables/useToSortCount';
import { cn } from '@/lib/utils';
import type { DocumentLink } from '@/lib/documents';
import { desktopSystem } from '@/lib/devices';
import { useLayoutMode } from '@/composables/useLayoutMode';
import { usePageMeta } from '@/composables/usePageMeta';

const props = defineProps<{
    /** The card the person comes from, kept for the files. */
    presetLinks: DocumentLink[];
    /** The document category already chosen (the registration document of a vehicle): the import form starts with it. */
    presetCategory?: number | null;
}>();

/** The files waiting in *To sort*, for the link beside the title. */
const toSortCount = useToSortCount();

/** The card the person comes from. */
const preset = computed(() => props.presetLinks[0]);

const {
    send,
    processing,
    errors: uploadErrors,
} = useFileUpload(() => preset.value, {
    category: () => props.presetCategory,
});

const layout = useLayoutMode();

/** The card beside the drop zone: the photo on the phone and the tablet, the home scanner on a computer it is built for. */
const sideCard = computed((): 'photo' | 'scanner' | null => {
    if (layout.value !== 'computer') {
        return 'photo';
    }

    return desktopSystem() === 'other' ? null : 'scanner';
});

/** The wording of the drop zone on the phone, for its gallery and files. */
type PhoneWording = {
    /** The text of the button that chooses the files. */
    label?: string;
    /** The line under it, with the accepted types. */
    hint?: string;
};

/** The phone has its own wording, for the gallery and the files of the phone; elsewhere the zone keeps its own. */
const phoneWording = computed((): PhoneWording =>
    layout.value === 'phone'
        ? {
              label: trans('Choose the files'),
              hint: trans(
                  "From the gallery or the phone's files. PDF, JPG, PNG or HEIC, up to 25 MB each",
              ),
          }
        : {},
);

const { title, head } = usePageMeta({
    info: 'upload',
    title: () => trans('Upload a document'),
    parent: 'toSort',
});
</script>

<template>
    <Head :title="head" />

    <div class="flex flex-col gap-6">
        <!-- On the phone the link to the files to sort has its own row under the title, as the board Carica -->
        <div class="flex flex-col gap-3">
            <FmTitle :title="title">
                <template #actions>
                    <FmToSortLink
                        v-if="layout !== 'phone'"
                        :count="toSortCount"
                    />
                </template>
            </FmTitle>
            <div v-if="layout === 'phone' && toSortCount > 0" class="flex">
                <FmToSortLink :count="toSortCount" />
            </div>
        </div>

        <FmNotice v-if="uploadErrors.length > 0" tone="warning">
            <p v-for="message in uploadErrors" :key="message" class="m-0">
                {{ message }}
            </p>
        </FmNotice>

        <div
            :class="
                cn(
                    layout === 'tablet' || sideCard === 'scanner'
                        ? 'grid min-h-[420px] grid-cols-[1fr_2fr] gap-4'
                        : 'flex flex-col gap-4',
                )
            "
        >
            <PhotoSheetCard
                v-if="sideCard"
                :stacked="layout === 'tablet'"
                :scanner="sideCard === 'scanner'"
            />
            <FmFileDrop
                v-bind="phoneWording"
                :min-height="layout === 'phone' ? 260 : undefined"
                :processing="processing"
                @files="send"
            />
        </div>
    </div>
</template>

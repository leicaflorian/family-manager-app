<script setup lang="ts">
/**
 * The photo of a person or the logo of an organization on grey, beside its
 * form, as the file beside *Edit the document*. Under it *Add* or *Change*
 * opens the picker and *Remove* takes it away. Nothing is sent here: the
 * chosen file and the removal go to the form, which saves them with the rest.
 * What to draw is up to the default slot, which gets the image to show.
 */
import { onBeforeUnmount, ref, useTemplateRef, watch } from 'vue';
import { IMAGE_FILE_TYPES } from '@/lib/files';

const { image, addLabel, changeLabel, error } = defineProps<{
    /** The address of the saved image, or null without one. */
    image: string | null;
    /** The button without an image, for example «Add a photo». */
    addLabel: string;
    /** The button with an image, for example «Change the photo». */
    changeLabel: string;
    /** The error of the image, from the server. */
    error?: string;
}>();

/** The image chosen and not saved yet. */
const file = defineModel<File | null>('file', { default: null });
/** The saved image is to be removed. */
const removed = defineModel<boolean>('removed', { default: false });

defineSlots<{
    /** What to draw, with the image to show, or null for the placeholder. */
    default(props: { image: string | null }): unknown;
}>();

const picker = useTemplateRef<HTMLInputElement>('picker');

/** The object URL of the image chosen, to show it before it is saved. */
const chosenUrl = ref<string | null>(null);

/** The image on show: the chosen one, else the saved one unless removed. */
const shown = ref<string | null>(image);

watch([chosenUrl, removed, () => image], () => {
    shown.value = chosenUrl.value ?? (removed.value ? null : image);
});

/**
 * Lets go of the object URL of the previous choice, which holds the file in memory.
 */
function forgetChosen(): void {
    if (chosenUrl.value !== null) {
        URL.revokeObjectURL(chosenUrl.value);
        chosenUrl.value = null;
    }
}

/**
 * Takes the file just chosen in the picker.
 */
function chosen(): void {
    const next = picker.value?.files?.[0] ?? null;

    if (next !== null) {
        forgetChosen();
        chosenUrl.value = URL.createObjectURL(next);
        file.value = next;
        removed.value = false;
    }

    if (picker.value) {
        picker.value.value = '';
    }
}

/**
 * Takes the image away: the chosen one and the saved one.
 */
function remove(): void {
    forgetChosen();
    file.value = null;
    removed.value = true;
}

onBeforeUnmount(forgetChosen);
</script>

<template>
    <div
        class="flex flex-col items-center gap-4 rounded-3xl bg-[#e9edf3] p-5 lg:p-7"
    >
        <slot :image="shown" />
        <span class="flex flex-wrap justify-center gap-x-3 text-sm">
            <button
                type="button"
                class="cursor-pointer border-0 bg-transparent p-0 font-semibold text-action underline hover:text-action-dark"
                @click="picker?.click()"
            >
                {{ shown ? changeLabel : addLabel }}
            </button>
            <button
                v-if="shown"
                type="button"
                class="cursor-pointer border-0 bg-transparent p-0 font-semibold text-ink-soft underline hover:text-ink"
                @click="remove"
            >
                {{ $t('Remove') }}
            </button>
            <input
                ref="picker"
                type="file"
                :accept="IMAGE_FILE_TYPES"
                :aria-label="shown ? changeLabel : addLabel"
                class="sr-only"
                @change="chosen"
            />
        </span>
        <span v-if="error" role="alert" class="text-[13px] text-overdue">{{
            error
        }}</span>
    </div>
</template>

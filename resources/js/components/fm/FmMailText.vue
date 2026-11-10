<script setup lang="ts">
/**
 * The text of a received mail, in *Received mail*: a frame on its own page
 * (`mail.text`), sandboxed, never the HTML in the page, because the mail was
 * written by a stranger. With both the plain text and the original kept, a
 * button switches between them; it says when the original was too large to
 * keep, and when no text was kept at all.
 *
 * @group files
 */
import { computed, ref } from 'vue';
import { formatSize } from '@/lib/format';

const {
    src,
    both = false,
    tooLarge = null,
} = defineProps<{
    /** The address of the text, null when no text was kept. */
    src: string | null;
    /** Both the plain text and the original are kept: they can be switched. */
    both?: boolean;
    /** The size in bytes of an original too large to keep. */
    tooLarge?: number | null;
}>();

/** The plain text is shown instead of the original. */
const plain = ref(false);

/** The address of the frame, with the version chosen. */
const frameSrc = computed((): string | null =>
    src === null ? null : plain.value ? `${src}?plain=1` : src,
);
</script>

<template>
    <p v-if="frameSrc === null" class="m-0 text-body-sm text-ink-soft">
        {{ $t('The text of this mail was not kept.') }}
    </p>
    <div v-else class="flex flex-col gap-2">
        <div
            v-if="tooLarge || both"
            class="flex flex-wrap items-center justify-between gap-2"
        >
            <span v-if="tooLarge" class="text-meta text-ink-soft">{{
                $t('The original is too large (:size) and was not kept', {
                    size: formatSize(tooLarge),
                })
            }}</span>
            <button
                v-if="both"
                type="button"
                class="ml-auto cursor-pointer rounded-md border-0 bg-transparent px-2 py-1 text-label text-action hover:bg-action-soft"
                @click="plain = !plain"
            >
                {{ plain ? $t('Original') : $t('Plain text') }}
            </button>
        </div>
        <!-- The one place the mail reaches the page: never v-html, and no
             allow-scripts, allow-same-origin, allow-forms or allow-top-navigation -->
        <iframe
            :src="frameSrc"
            sandbox="allow-popups allow-popups-to-escape-sandbox"
            :title="$t('Text of the mail')"
            class="h-[60dvh] min-h-80 w-full rounded-lg border-0 bg-canvas"
        />
    </div>
</template>

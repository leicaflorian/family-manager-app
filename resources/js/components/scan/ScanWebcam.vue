<script setup lang="ts">
/**
 * The webcam of a computer, over the scan: the picture of the webcam, what to do and *Take a picture*, which gives
 * the frame on the screen to the page as a photo. *Cancel* closes it, and the light of the webcam goes off.
 */
import { Camera } from '@lucide/vue';
import { onMounted, ref, watch } from 'vue';
import FmButton from '@/components/fm/FmButton.vue';

const props = defineProps<{
    /** The picture of the webcam; null while the browser is asking for it. */
    stream: MediaStream | null;
}>();

const emit = defineEmits<{
    /** *Take a picture*, with the video to take the frame from. */
    shoot: [video: HTMLVideoElement];
    /** *Cancel*. */
    cancel: [];
}>();

const video = ref<HTMLVideoElement | null>(null);

/** Shows the picture of the webcam in the video. */
function show(): void {
    if (!video.value) return;

    video.value.srcObject = props.stream;
    // the picture starts by itself (autoplay) where play() is refused
    void video.value.play?.()?.catch(() => undefined);
}

onMounted(show);
watch(() => props.stream, show);
</script>

<template>
    <section
        role="dialog"
        aria-modal="true"
        aria-labelledby="webcam-title"
        class="dark fixed inset-0 z-50 flex flex-col items-center justify-center gap-5 bg-camera-bg p-8 text-ink"
    >
        <h1 id="webcam-title" class="sr-only">{{ $t('Webcam') }}</h1>
        <video
            ref="video"
            autoplay
            muted
            playsinline
            class="max-h-[70dvh] max-w-full rounded-lg bg-camera-surface"
        />
        <p role="status" class="m-0 text-center text-sm text-ink-soft">
            {{
                stream
                    ? $t(
                          'Put the document in front of the webcam, all inside the frame, and tap Take a picture.',
                      )
                    : $t(
                          'Opening the webcam: if the browser asks, allow the use of the camera.',
                      )
            }}
        </p>
        <div class="flex gap-3">
            <FmButton variant="text" @click="emit('cancel')">{{
                $t('Cancel')
            }}</FmButton>
            <FmButton
                :icon="Camera"
                :disabled="!stream"
                @click="video && emit('shoot', video)"
                >{{ $t('Take a picture') }}</FmButton
            >
        </div>
    </section>
</template>

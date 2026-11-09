import { trans } from 'laravel-vue-i18n';
import type { Ref } from 'vue';
import { onBeforeUnmount, ref, shallowRef } from 'vue';

/** The quality of the JPEG taken from the webcam: it is reduced again afterwards, as every photo. */
const SHOT_QUALITY = 0.95;

/** What `useWebcam` gives to the scan. */
export type UseWebcam = {
    /** The webcam can be asked for: the browser has one to give, the page is in https, and it has not failed. */
    available: Ref<boolean>;
    /** The picture of the webcam while it is open. */
    stream: Ref<MediaStream | null>;
    /** Why the webcam did not open, for the person; null when it did. */
    error: Ref<string | null>;
    /**
     * Opens the webcam, with the largest picture it gives. After a refusal it is not available any more, and the
     * button of the page opens the choice of a file instead.
     *
     * @returns true when the webcam is open
     */
    open: () => Promise<boolean>;
    /**
     * Takes the frame on the screen and closes the webcam.
     *
     * @param video - the video that shows the webcam
     * @returns the photo, or null when the frame could not be taken
     */
    shoot: (video: HTMLVideoElement) => Promise<File | null>;
    /** Closes the webcam: its light goes off. */
    close: () => void;
};

/**
 * Whether the browser can give the page a webcam: `getUserMedia` is there only in https (or on localhost).
 *
 * @returns true when the webcam can be asked for
 */
function canAsk(): boolean {
    return (
        typeof window !== 'undefined' &&
        window.isSecureContext === true &&
        typeof navigator.mediaDevices?.getUserMedia === 'function'
    );
}

/**
 * The webcam of a computer for *Take a photo*: a browser there does not know `capture`, so the page opens the
 * webcam itself and the photo is the frame on the screen. Without a webcam, outside https or with the permission
 * refused, the page lets the person choose a file. The light goes off when the page goes away.
 *
 * @returns the state of the webcam, `open`, `shoot` and `close`
 */
export function useWebcam(): UseWebcam {
    const available = ref(canAsk());
    // a MediaStream is a browser object: Vue does not wrap it
    const stream = shallowRef<MediaStream | null>(null);
    /** Counts the openings and closings: an opening that waited past a closing is stopped. */
    let opening = 0;
    const error = ref<string | null>(null);

    /** Closes the webcam. */
    function close(): void {
        // a webcam still asking for the permission is forgotten too: it is stopped as soon as it opens
        opening++;
        stream.value?.getTracks().forEach((track) => track.stop());
        stream.value = null;
    }

    /**
     * Opens the webcam.
     *
     * @returns true when it is open
     */
    async function open(): Promise<boolean> {
        error.value = null;
        close();
        const attempt = opening;

        try {
            const opened = await navigator.mediaDevices.getUserMedia({
                // the largest picture the webcam gives: the document is read from it
                video: { width: { ideal: 3840 }, height: { ideal: 2160 } },
                audio: false,
            });

            if (attempt !== opening) {
                // closed while the browser asked: the light must not stay on
                opened.getTracks().forEach((track) => track.stop());

                return false;
            }

            stream.value = opened;

            return true;
        } catch (caught) {
            if (attempt !== opening) return false;

            available.value = false;
            error.value =
                (caught as { name?: string } | null)?.name === 'NotAllowedError'
                    ? trans(
                          'I cannot open the webcam: the browser is not allowed to use it. Tap again to choose a photo from the computer.',
                      )
                    : trans(
                          'I cannot open the webcam: I cannot find one. Tap again to choose a photo from the computer.',
                      );

            return false;
        }
    }

    /**
     * Takes the frame on the screen and closes the webcam.
     *
     * @param video - the video that shows the webcam
     * @returns the photo, or null when the frame could not be taken
     */
    async function shoot(video: HTMLVideoElement): Promise<File | null> {
        const canvas = document.createElement('canvas');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        const context = canvas.getContext('2d');

        if (!context || !video.videoWidth) {
            return null;
        }

        context.drawImage(video, 0, 0);
        const blob = await new Promise<Blob | null>((resolve) =>
            canvas.toBlob(resolve, 'image/jpeg', SHOT_QUALITY),
        );
        // the memory of a canvas comes back only when it is emptied
        canvas.width = 0;
        canvas.height = 0;
        close();

        return blob
            ? new File([blob], 'webcam.jpg', { type: 'image/jpeg' })
            : null;
    }

    if (typeof window !== 'undefined') {
        window.addEventListener('pagehide', close);
    }

    onBeforeUnmount(() => {
        window.removeEventListener('pagehide', close);
        close();
    });

    return { available, stream, error, open, shoot, close };
}

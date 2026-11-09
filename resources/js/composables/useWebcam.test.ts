import { render } from '@testing-library/vue';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import { defineComponent, h } from 'vue';
import type { UseWebcam } from './useWebcam';
import { useWebcam } from './useWebcam';

/** A webcam picture with one track to stop. */
function fakeStream(): MediaStream & { stop: ReturnType<typeof vi.fn> } {
    const stop = vi.fn();

    return { getTracks: () => [{ stop }], stop } as unknown as MediaStream & {
        stop: ReturnType<typeof vi.fn>;
    };
}

/** Mounts a component that uses the webcam and gives it back. */
function mountWebcam(): { webcam: UseWebcam; unmount: () => void } {
    let webcam: UseWebcam | undefined;
    const view = render(
        defineComponent({
            setup() {
                webcam = useWebcam();

                return () => h('div');
            },
        }),
    );

    return { webcam: webcam!, unmount: () => view.unmount() };
}

/** Gives the browser a `getUserMedia`. */
function withCamera(getUserMedia: ReturnType<typeof vi.fn>): void {
    Object.defineProperty(navigator, 'mediaDevices', {
        value: { getUserMedia },
        configurable: true,
    });
}

beforeEach(() => {
    vi.stubGlobal('isSecureContext', true);
});

afterEach(() => {
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
    Reflect.deleteProperty(navigator, 'mediaDevices');
});

describe('useWebcam', () => {
    it('asks for the largest picture, without sound', async () => {
        const stream = fakeStream();
        const getUserMedia = vi.fn(async () => stream);
        withCamera(getUserMedia);
        const { webcam } = mountWebcam();

        expect(webcam.available.value).toBe(true);
        expect(await webcam.open()).toBe(true);

        expect(getUserMedia).toHaveBeenCalledWith({
            video: { width: { ideal: 3840 }, height: { ideal: 2160 } },
            audio: false,
        });
        expect(webcam.stream.value).toBe(stream);
    });

    it('is not available without getUserMedia or outside https', () => {
        expect(mountWebcam().webcam.available.value).toBe(false);

        withCamera(vi.fn());
        vi.stubGlobal('isSecureContext', false);
        expect(mountWebcam().webcam.available.value).toBe(false);
    });

    it('says why when the permission is refused, and is not available any more', async () => {
        withCamera(
            vi.fn(async () => {
                throw Object.assign(new Error('denied'), {
                    name: 'NotAllowedError',
                });
            }),
        );
        const { webcam } = mountWebcam();

        expect(await webcam.open()).toBe(false);

        expect(webcam.available.value).toBe(false);
        expect(webcam.error.value).toBe(
            'I cannot open the webcam: the browser is not allowed to use it. Tap again to choose a photo from the computer.',
        );
    });

    it('says it finds none when there is no webcam', async () => {
        withCamera(
            vi.fn(async () => {
                throw Object.assign(new Error('none'), {
                    name: 'NotFoundError',
                });
            }),
        );
        const { webcam } = mountWebcam();

        await webcam.open();

        expect(webcam.error.value).toBe(
            'I cannot open the webcam: I cannot find one. Tap again to choose a photo from the computer.',
        );
    });

    it('turns the light off when closed and when the page goes away', async () => {
        const first = fakeStream();
        const second = fakeStream();
        withCamera(
            vi.fn().mockResolvedValueOnce(first).mockResolvedValueOnce(second),
        );
        const { webcam, unmount } = mountWebcam();

        await webcam.open();
        webcam.close();
        expect(first.stop).toHaveBeenCalled();
        expect(webcam.stream.value).toBeNull();

        await webcam.open();
        unmount();
        expect(second.stop).toHaveBeenCalled();
    });

    it('takes the frame on the screen as a JPEG and closes', async () => {
        const stream = fakeStream();
        withCamera(vi.fn(async () => stream));
        const drawImage = vi.fn();
        vi.spyOn(HTMLCanvasElement.prototype, 'getContext').mockReturnValue({
            drawImage,
        } as unknown as CanvasRenderingContext2D);
        vi.spyOn(HTMLCanvasElement.prototype, 'toBlob').mockImplementation(
            function (callback, type, quality) {
                callback(
                    new Blob([`${type}:${quality}`], { type: 'image/jpeg' }),
                );
            },
        );
        const { webcam } = mountWebcam();
        await webcam.open();
        const video = document.createElement('video');
        Object.defineProperty(video, 'videoWidth', { value: 3840 });
        Object.defineProperty(video, 'videoHeight', { value: 2160 });

        const photo = await webcam.shoot(video);

        expect(photo?.name).toBe('webcam.jpg');
        expect(photo?.type).toBe('image/jpeg');
        expect(await photo?.text()).toBe('image/jpeg:0.95');
        expect(drawImage).toHaveBeenCalledWith(video, 0, 0);
        expect(stream.stop).toHaveBeenCalled();
    });
});

import { trans } from 'laravel-vue-i18n';
import { onBeforeUnmount, onMounted } from 'vue';
import { toast } from 'vue-sonner';
import { isNewerVersion, isReleasedVersion } from '@/lib/versions';
import { version } from '@/routes';

/** Ten minutes between two checks while the page stays open in the front. */
const EVERY = 600_000;

/** How the check runs: all optional, the tests pass their own. */
export type NewVersionOptions = {
    /** The version of the page (`__APP_BUILD__` of the build). */
    current?: string;
    /** Asks the server its version; `null` when it cannot tell. */
    check?: () => Promise<string | null>;
    /** Milliseconds between two checks while the page is visible. */
    every?: number;
};

/**
 * Asks `GET /version` its version, never from a cache.
 *
 * @returns the version of the server, or `null` when the answer is not one (network down, an error page)
 */
async function askServer(): Promise<string | null> {
    const response = await fetch(version().url, {
        cache: 'no-store',
        headers: { Accept: 'application/json' },
    });

    if (!response.ok) {
        return null;
    }

    const body: unknown = await response.json();

    return typeof body === 'object' &&
        body !== null &&
        'version' in body &&
        typeof body.version === 'string'
        ? body.version
        : null;
}

/**
 * Tells the person that a new version was released while the page was open: it asks the server its version when
 * the page comes back to the front and every few minutes while it is visible, and when the server is newer it shows,
 * once, a message with *Reload* that stays until it is closed. A form left half filled is guarded by the browser's
 * own question before leaving (`useUnsavedChanges`). In development (`dev`) it never asks.
 *
 * @param options - the version of the page, the check and the interval (for the tests)
 */
export function useNewVersion(options: NewVersionOptions = {}): void {
    const current = options.current ?? __APP_BUILD__;
    const check = options.check ?? askServer;
    const every = options.every ?? EVERY;

    let timer: ReturnType<typeof setInterval> | undefined;
    let done = false;

    /** Stops asking: the notice is out, or the page is gone. */
    function stop(): void {
        done = true;
        clearInterval(timer);
        document.removeEventListener('visibilitychange', onVisibility);
    }

    /** One check, only while the page is visible; a failed one is simply tried again later. */
    async function run(): Promise<void> {
        if (done || document.visibilityState !== 'visible') {
            return;
        }

        const server = await check().catch(() => null);

        if (done || server === null || !isNewerVersion(server, current)) {
            return;
        }

        stop();
        toast.info(trans("There's a new version"), {
            duration: Infinity,
            action: {
                label: trans('Reload'),
                onClick: () => window.location.reload(),
            },
        });
    }

    /** The page came back to the front (the phone reopened the app): the moment a release is most likely missed. */
    function onVisibility(): void {
        void run();
    }

    onMounted(() => {
        if (!isReleasedVersion(current)) {
            return;
        }

        document.addEventListener('visibilitychange', onVisibility);
        timer = setInterval(() => void run(), every);
    });

    onBeforeUnmount(stop);
}

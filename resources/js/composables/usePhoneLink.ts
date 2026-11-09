import { useIntervalFn } from '@vueuse/core';
import type { Ref } from 'vue';
import { onBeforeUnmount, ref, watch } from 'vue';
import { index } from '@/routes/devices';

/** How often the panel asks which phones are linked, in milliseconds. */
export const PHONE_POLL_INTERVAL = 2000;

/** What `usePhoneLink` gives to the panel. */
export type UsePhoneLink = {
    /** The code, its QR and the phones; null until the first answer. */
    link: Ref<App.Data.PhoneLinkData | null>;
    /** The server could not make the code, or the phones could not be asked. */
    failed: Ref<boolean>;
    /** Makes a new code (the panel opened, or the person retries). */
    load: () => Promise<void>;
};

/**
 * Asks the server for a JSON address.
 *
 * @param url - the address
 * @returns the answer, or null when it is not a success
 */
async function getJson<T>(url: string): Promise<T | null> {
    const response = await fetch(url, {
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    });

    return response.ok ? ((await response.json()) as T) : null;
}

/**
 * The data of the panel *Link the phone*: while it is open it makes one code (a new one at every opening),
 * then every 2 seconds asks only for the linked phones (`?only=devices`), so a phone that just entered shows
 * up without a new code. When the code expires with the panel still open, it makes another. It stops when the
 * panel closes and when the component goes away.
 *
 * @param open - whether the panel is open
 * @returns the data and `load`
 */
export function usePhoneLink(open: () => boolean): UsePhoneLink {
    const link = ref<App.Data.PhoneLinkData | null>(null);
    const failed = ref(false);
    let busy = false;

    /** Makes a new code and takes the answer as it is. */
    async function load(): Promise<void> {
        failed.value = false;
        busy = true;

        try {
            const data = await getJson<App.Data.PhoneLinkData>(index().url);

            if (open()) {
                link.value = data;
                failed.value = data === null;
            }
        } catch {
            failed.value = true;
        } finally {
            busy = false;
        }
    }

    /** Asks for the phones only, or for a new code once the one shown has expired. */
    async function refresh(): Promise<void> {
        if (busy || link.value === null) {
            return;
        }

        if (Date.parse(link.value.expires_at) <= Date.now()) {
            await load();

            return;
        }

        busy = true;

        try {
            const data = await getJson<{
                devices: App.Data.LinkedDeviceData[];
            }>(index({ query: { only: 'devices' } }).url);

            if (data !== null && open() && link.value !== null) {
                link.value = { ...link.value, devices: data.devices };
            }
        } catch {
            // A reload that fails is tried again 2 seconds later
        } finally {
            busy = false;
        }
    }

    const poll = useIntervalFn(refresh, PHONE_POLL_INTERVAL, {
        immediate: false,
    });

    watch(
        open,
        (isOpen) => {
            if (isOpen) {
                link.value = null;
                void load();
                poll.resume();
            } else {
                poll.pause();
            }
        },
        { immediate: true },
    );

    onBeforeUnmount(() => poll.pause());

    return { link, failed, load };
}

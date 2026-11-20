/**
 * The app on the Home screen, seen from the page: the manifest (added by script, as iPhone and iPad must not have it),
 * the service worker, and the invitation to install that Android makes once, early, so a page opened later can still
 * call it. Started once by `app.ts`.
 */
import { shallowRef } from 'vue';
import { isIos } from '@/lib/devices';

/** The invitation of the phone (`beforeinstallprompt`), which the DOM types do not have. */
export type InstallPromptEvent = Event & {
    /** Shows the question of the phone. */
    prompt: () => Promise<void>;
    /** What the person answered. */
    userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
};

/** The invitation the phone made, until it is used or the app is installed. */
export const installPrompt = shallowRef<InstallPromptEvent | null>(null);

/**
 * Adds the manifest of the app, once. An iPhone or an iPad gets a manifest of its own: the scope of the whole site,
 * so that Safari does not show its bars when the app opened from the icon leaves the folder of the page the icon was
 * added from (an icon added from /dashboard saw /login and /balance as other sites), and no start address, so that
 * the icon opens the page it was added from, with the secret of the linked phone after the `#`. The icon keeps a
 * storage of its own anyway, which is why the secret travels in the address.
 */
export function addManifestLink(): void {
    if (document.head.querySelector('link[rel="manifest"]')) return;

    const link = document.createElement('link');
    link.rel = 'manifest';
    link.href = isIos() ? '/manifest-ios.webmanifest' : '/manifest.webmanifest';
    document.head.appendChild(link);
}

/**
 * Registers the service worker with the scope of the whole site. A browser without one, an address that is not
 * secure (`http`) or a registration that fails leave the app as it was: the worker is only an addition.
 */
export async function registerServiceWorker(): Promise<void> {
    if (!('serviceWorker' in navigator) || !window.isSecureContext) return;

    try {
        await navigator.serviceWorker.register('/sw.js', { scope: '/' });
    } catch {
        // the app works without it
    }
}

/**
 * Keeps the invitation of the phone. It is left alone (no `preventDefault`): the browser may still show its own
 * banner, and the sheet only adds a way to call it again.
 */
export function startInstallWatch(): void {
    window.addEventListener('beforeinstallprompt', (event) => {
        installPrompt.value = event as InstallPromptEvent;
    });
    window.addEventListener('appinstalled', () => {
        installPrompt.value = null;
    });
}

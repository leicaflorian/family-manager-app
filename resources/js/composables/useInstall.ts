/**
 * The sheet *Install Family Manager*: when it comes up on a phone and what its buttons do. It comes up when a phone
 * opens the page in the browser over https and the app is not on the Home screen yet; on an iPhone it shows the
 * steps, on Android the *Install* button that calls the invitation of the phone. *Not now* closes it until the next
 * opening: nothing is remembered. Never on `http`, where the phone cannot install.
 */
import type { Ref } from 'vue';
import { onBeforeUnmount, ref } from 'vue';
import { isIos, isPhone, isStandalone } from '@/lib/devices';
import { installPrompt } from '@/lib/pwa/install';

/** Set once the app is installed from this browser, so the sheet does not come up again. */
export const INSTALLED_KEY = 'fm.installed';

/** What `useInstall` gives to the page. */
export type UseInstall = {
    /** The sheet is open. */
    open: Ref<boolean>;
    /** An iPhone or an iPad: no button, only the steps. */
    ios: boolean;
    /** The steps by hand are shown: always on iPhone, on Android when the phone made no invitation or it was refused. */
    showSteps: Ref<boolean>;
    /** *Install*: calls the invitation of the phone, or shows the steps if there is none. */
    install: () => Promise<void>;
    /** *Not now*: closes the sheet until the next opening. */
    later: () => void;
};

/**
 * Whether the app was already installed from this browser.
 *
 * @returns true when the flag is there; false also when the browser keeps no storage
 */
function wasInstalled(): boolean {
    try {
        return localStorage.getItem(INSTALLED_KEY) !== null;
    } catch {
        return false;
    }
}

/** Remembers that the app is installed. */
function rememberInstalled(): void {
    try {
        localStorage.setItem(INSTALLED_KEY, '1');
    } catch {
        // without storage the sheet may come up again, and that is all
    }
}

/**
 * The sheet *Install Family Manager* of a page.
 *
 * @returns the state of the sheet and its two actions
 */
export function useInstall(): UseInstall {
    const ios = isIos();
    const offer =
        isPhone() &&
        !isStandalone() &&
        globalThis.location.protocol === 'https:' &&
        !wasInstalled();
    const open = ref(offer);
    const showSteps = ref(ios);

    /** The app was installed, from the sheet or from the menu of the browser. */
    function installed(): void {
        rememberInstalled();
        open.value = false;
    }

    window.addEventListener('appinstalled', installed);
    onBeforeUnmount(() =>
        window.removeEventListener('appinstalled', installed),
    );

    /** *Install*: the invitation of the phone, or the steps by hand. */
    async function install(): Promise<void> {
        const invitation = installPrompt.value;

        // Already refused, or a browser that makes no invitation: the same thing by hand
        if (!invitation) {
            showSteps.value = true;

            return;
        }

        installPrompt.value = null;

        try {
            await invitation.prompt();

            if ((await invitation.userChoice).outcome === 'accepted') {
                installed();

                return;
            }
        } catch {
            // falls through to the steps
        }

        showSteps.value = true;
    }

    /** *Not now*: until the next opening. */
    function later(): void {
        open.value = false;
    }

    return { open, ios, showSteps, install, later };
}

import { autoAnimatePlugin } from '@formkit/auto-animate/vue';
import { plugin as formkit } from '@formkit/vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { I18n, i18nVue } from 'laravel-vue-i18n';
import formkitConfig from '@/formkit.config';
import { useTheme } from '@/composables/useTheme';
import { initializeFlashToast } from '@/lib/flashToast';
import {
    addManifestLink,
    registerServiceWorker,
    startInstallWatch,
} from '@/lib/pwa/install';
import { i18nOptions } from '@/lib/i18n';
import { pageLayout } from '@/lib/pageLayout';
import { forgetShared } from '@/lib/pwa/sharedFiles';
import { login, logout } from '@/routes';
import { pageVisitOptions } from '@/lib/transitions';
import { startVisitHistory } from '@/lib/visitHistory';

const appName = import.meta.env.VITE_APP_NAME || 'Family Manager';

// The server picks the language and writes it in <html lang>. The pages translate
// the texts of their layout as soon as they are loaded, before the app exists:
// the translations must be ready first.
const lang = document.documentElement.lang || 'en';
I18n.getSharedInstance(i18nOptions(lang));

// Back of the top bar needs the depth of the first page too: the listener must exist
// before Inertia fires the `initial` navigate, which happens before any layout mounts.
startVisitHistory();

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: pageLayout,
    withApp: (app) => {
        app.use(formkit, formkitConfig)
            .use(autoAnimatePlugin)
            .use(i18nVue, i18nOptions(lang));

        app.directive('focus', {
            mounted: (el: HTMLElement, shouldFocus) => {
                if (shouldFocus.value !== false) {
                    el.focus();
                }
            },
        });
    },
    progress: {
        // Inertia writes the colour into its CSS: the token follows the theme
        color: 'var(--action)',
    },
    defaults: {
        visitOptions: (_href, options) => pageVisitOptions(options),
    },
});

// Keeps the `dark` class of <html> right while the app is open. usePage() is global,
// so it works here, outside a component; the page props arrive with the first visit.
useTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();

// The app on the Home screen: the manifest (not on iPhone and iPad), the service worker, and the invitation of
// Android kept for the sheet *Install Family Manager*
addManifestLink();
startInstallWatch();
void registerServiceWorker();

// The files shared with the app belong to the device: signing out or in with a password empties them
router.on('before', ({ detail }) => {
    const { method, url } = detail.visit;

    if (
        method === 'post' &&
        [logout().url, login().url].includes(url.pathname)
    ) {
        void forgetShared(globalThis.caches);
    }
});

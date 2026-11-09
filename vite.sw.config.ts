import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vite-plus';

/**
 * The build of the service worker (`resources/js/sw.ts`), run by `npm run build` after the one of the app. It is a
 * separate build because the file must be one script with no imports and no hash in its name: a worker is registered
 * as a classic script from `/sw.js`, and the chunks the app build shares between its entries live in `/build/assets`,
 * where a relative import from the root would not find them. The file lands in `public/build/sw.js`, and
 * `ServiceWorkerController` serves it from the root.
 */
export default defineConfig({
    // Nothing to copy from `public/`: the main build already did
    publicDir: false,
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    build: {
        outDir: 'public/build',
        // The main build wrote its files there
        emptyOutDir: false,
        manifest: false,
        lib: {
            entry: 'resources/js/sw.ts',
            formats: ['iife'],
            name: 'familyManagerServiceWorker',
            fileName: () => 'sw.js',
        },
    },
});

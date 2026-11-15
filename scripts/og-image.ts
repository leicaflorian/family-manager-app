/**
 * Draws the preview image of the links (`og:image` of `PageMeta`) from `resources/design/og-image.svg`
 * into `public/fameja-app-og-1200x630.png`, 1200 × 630 px. The name of the app is written in the SVG,
 * like the manifest: `tests/Feature/AppNameTest.php` checks the file is there.
 * Runs in the Sail container, which has `rsvg-convert`: `npm run og-image`.
 */
import { execFileSync } from 'node:child_process';

execFileSync('rsvg-convert', [
    '--width=1200',
    '--height=630',
    'resources/design/og-image.svg',
    '--output=public/fameja-app-og-1200x630.png',
]);

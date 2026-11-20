/**
 * Draws the splash screens of iPhone and iPad (`App\Support\SplashScreens`): for every screen of
 * `resources/design/splash-screens.json`, in portrait and landscape and in the light and dark theme, the icon of
 * the app in the middle of the page background (`canvas` of `resources/design/tokens.json`), as the board
 * *Apertura dall'icona* of the canvas draws it. The pictures go to `public/splash/<theme>-<width>x<height>.png`.
 * Runs in the Sail container, which has `rsvg-convert`: `npm run splash-screens`.
 */
import { execFileSync } from 'node:child_process';
import {
    mkdirSync,
    mkdtempSync,
    readFileSync,
    rmSync,
    writeFileSync,
} from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

/** One screen: width and height in points, portrait, and pixel ratio. */
interface Screen {
    width: number;
    height: number;
    ratio: number;
}

/** A colour token of `tokens.json`. */
interface ColorToken {
    name: string;
    value: string | Record<string, string>;
}

type Theme = 'light' | 'dark';

const OUT = 'public/splash';

const { icon, screens } = JSON.parse(
    readFileSync('resources/design/splash-screens.json', 'utf8'),
) as { icon: number; screens: Screen[] };
const tokens = (
    JSON.parse(readFileSync('resources/design/tokens.json', 'utf8')) as {
        color: { tokens: ColorToken[] };
    }
).color.tokens;

/**
 * The value of a colour token in a theme.
 *
 * @param name - the token, for example `canvas`
 * @param theme - the theme
 * @returns the colour, `#rrggbb`
 * @throws Error when the token does not exist
 */
function color(name: string, theme: Theme): string {
    const token = tokens.find((candidate) => candidate.name === name);

    if (token === undefined) {
        throw new Error(`No colour token "${name}"`);
    }

    return typeof token.value === 'string'
        ? token.value
        : (token.value[theme] ?? '');
}

/**
 * The SVG of one splash screen: the page background with the icon of the app in the middle.
 *
 * @param width - the width, in pixels
 * @param height - the height, in pixels
 * @param size - the side of the icon, in pixels
 * @param theme - the theme
 * @returns the SVG
 */
function splash(
    width: number,
    height: number,
    size: number,
    theme: Theme,
): string {
    const scale = size / 500;
    const x = (width - size) / 2;
    const y = (height - size) / 2;
    const white = color('on-hero', theme);

    return `<svg xmlns="http://www.w3.org/2000/svg" width="${width}" height="${height}" viewBox="0 0 ${width} ${height}">
  <rect width="${width}" height="${height}" fill="${color('canvas', theme)}" />
  <g transform="translate(${x} ${y}) scale(${scale})">
    <rect width="500" height="500" rx="80" fill="${color('brand', theme)}" />
    <path d="M 60,300 L 250,110 L 440,300" stroke="${white}" stroke-width="55" stroke-linecap="round" stroke-linejoin="round" fill="none" />
    <circle cx="155" cy="300" r="65" fill="${white}" />
    <circle cx="345" cy="300" r="65" fill="${white}" />
    <circle cx="250" cy="315" r="30" fill="${white}" />
  </g>
</svg>`;
}

const work = mkdtempSync(join(tmpdir(), 'splash-'));
rmSync(OUT, { recursive: true, force: true });
mkdirSync(OUT, { recursive: true });

try {
    for (const screen of screens) {
        const portrait = [
            screen.width * screen.ratio,
            screen.height * screen.ratio,
        ];
        const sizes = [portrait, [portrait[1], portrait[0]]];

        for (const [width, height] of sizes) {
            for (const theme of ['light', 'dark'] as const) {
                const svg = join(work, `${theme}-${width}x${height}.svg`);
                writeFileSync(
                    svg,
                    splash(width, height, icon * screen.ratio, theme),
                );
                execFileSync('rsvg-convert', [
                    svg,
                    `--output=${OUT}/${theme}-${width}x${height}.png`,
                ]);
            }
        }
    }
} finally {
    rmSync(work, { recursive: true, force: true });
}

/**
 * Draws the icons of the app: the logo of `public/favicon.svg` on the brand colour (`brand` and `on-hero` of
 * `resources/design/tokens.json`), always as a full square, because macOS, iPhone and Android give the icon their
 * own rounded shape (an icon with transparent corners ends up shrunk on a white plate).
 * - `public/icons/icon-<size>.png` (`purpose: any`) and `public/apple-touch-icon.png`: the logo at its size.
 * - `public/icons/icon-maskable-<size>.png` (`purpose: maskable`): the logo smaller, inside the safe zone (the
 *   circle of 80% of the side in the middle), so that no shape cuts it.
 * Runs in the Sail container, which has `rsvg-convert`: `npm run app-icons`.
 */
import { execFileSync } from 'node:child_process';
import { mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

/** A colour token of `tokens.json`. */
interface ColorToken {
    name: string;
    value: string | Record<string, string>;
}

/** One picture to draw: where, its side in pixels and the scale of the logo. */
interface Icon {
    path: string;
    size: number;
    scale: number;
}

/**
 * The logo reaches 224 units from the middle of its 500 units (the round ends of the roof): 0.84 keeps it at 188,
 * inside the 200 of the safe zone of a maskable icon.
 */
const MASKABLE_SCALE = 0.84;

const ICONS: Icon[] = [
    { path: 'public/icons/icon-192.png', size: 192, scale: 1 },
    { path: 'public/icons/icon-512.png', size: 512, scale: 1 },
    {
        path: 'public/icons/icon-maskable-192.png',
        size: 192,
        scale: MASKABLE_SCALE,
    },
    {
        path: 'public/icons/icon-maskable-512.png',
        size: 512,
        scale: MASKABLE_SCALE,
    },
    { path: 'public/apple-touch-icon.png', size: 180, scale: 1 },
];

const tokens = (
    JSON.parse(readFileSync('resources/design/tokens.json', 'utf8')) as {
        color: { tokens: ColorToken[] };
    }
).color.tokens;

/**
 * The value of a colour token in the light theme.
 *
 * @param name - the token, for example `brand`
 * @returns the colour, `#rrggbb`
 * @throws Error when the token does not exist
 */
function color(name: string): string {
    const token = tokens.find((candidate) => candidate.name === name);

    if (token === undefined) {
        throw new Error(`No colour token "${name}"`);
    }

    return typeof token.value === 'string'
        ? token.value
        : (token.value.light ?? '');
}

/**
 * The SVG of one icon: the brand colour to the edges, the logo scaled around the middle.
 *
 * @param icon - the icon to draw
 * @returns the SVG
 */
function svg(icon: Icon): string {
    const white = color('on-hero');
    const offset = 250 * (1 - icon.scale);

    return `<svg xmlns="http://www.w3.org/2000/svg" width="${icon.size}" height="${icon.size}" viewBox="0 0 500 500">
  <rect width="500" height="500" fill="${color('brand')}" />
  <g transform="translate(${offset} ${offset}) scale(${icon.scale})">
    <path d="M 60,300 L 250,110 L 440,300" stroke="${white}" stroke-width="55" stroke-linecap="round" stroke-linejoin="round" fill="none" />
    <circle cx="155" cy="300" r="65" fill="${white}" />
    <circle cx="345" cy="300" r="65" fill="${white}" />
    <circle cx="250" cy="315" r="30" fill="${white}" />
  </g>
</svg>`;
}

const work = mkdtempSync(join(tmpdir(), 'app-icons-'));

try {
    for (const icon of ICONS) {
        const file = join(work, 'icon.svg');
        writeFileSync(file, svg(icon));
        execFileSync('rsvg-convert', [file, `--output=${icon.path}`]);
    }
} finally {
    rmSync(work, { recursive: true, force: true });
}

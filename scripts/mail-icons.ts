/**
 * Draws the icons of the mails (`resources/design/mail-icons.json`) as 48 px PNG files
 * in `public/mail-icons/`: mail programs do not draw the SVG of Lucide, so the icons
 * travel as pictures, in the colour of the design system (`resources/design/tokens.json`).
 * Runs in the Sail container, which has `rsvg-convert`: `npm run mail-icons`.
 */
import { execFileSync } from 'node:child_process';
import { mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

/** One icon of the mails: the Lucide icon and the colour token of its stroke. */
interface MailIcon {
    lucide: string;
    color: string;
    tile: string;
}

/** A colour token of `tokens.json`. */
interface ColorToken {
    name: string;
    value: string | Record<string, string>;
}

/** An element of a Lucide icon: its tag and its attributes. */
type IconNode = [string, Record<string, string>][];

const SIZE = 48;

const icons = JSON.parse(
    readFileSync('resources/design/mail-icons.json', 'utf8'),
) as Record<string, MailIcon>;
const tokens = (
    JSON.parse(readFileSync('resources/design/tokens.json', 'utf8')) as {
        color: { tokens: ColorToken[] };
    }
).color.tokens;

/**
 * The light value of a colour token.
 *
 * @param name - the token, for example `vehicles-text`
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
 * The SVG of a Lucide icon with its stroke in one colour.
 *
 * @param node - the elements of the icon
 * @param stroke - the colour of the stroke
 * @returns the SVG text
 */
function svg(node: IconNode, stroke: string): string {
    const children = node
        .map(([tag, attributes]) => {
            const written = Object.entries(attributes)
                .filter(([name]) => name !== 'key')
                .map(([name, value]) => `${name}="${value}"`)
                .join(' ');

            return `<${tag} ${written}/>`;
        })
        .join('');

    return `<svg xmlns="http://www.w3.org/2000/svg" width="${SIZE}" height="${SIZE}" viewBox="0 0 24 24" fill="none" stroke="${stroke}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${children}</svg>`;
}

const work = mkdtempSync(join(tmpdir(), 'mail-icons-'));

try {
    for (const [name, icon] of Object.entries(icons)) {
        const module = (await import(
            `../node_modules/@lucide/vue/dist/esm/icons/${icon.lucide}.mjs`
        )) as { __iconData: { node: IconNode } };
        const file = join(work, `${name}.svg`);

        writeFileSync(file, svg(module.__iconData.node, color(icon.color)));
        execFileSync('rsvg-convert', [
            '-w',
            String(SIZE),
            '-h',
            String(SIZE),
            '-o',
            `public/mail-icons/${name}.png`,
            file,
        ]);
    }
} finally {
    rmSync(work, { recursive: true, force: true });
}

console.log(`${Object.keys(icons).length} icons in public/mail-icons`);

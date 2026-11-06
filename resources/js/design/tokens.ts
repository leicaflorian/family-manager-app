/**
 * Turns the design tokens of Claude Design (`resources/design/tokens.json`) into
 * `resources/css/tokens.css`, the only place where the app's colours, text
 * styles, radii, shadows and sizes are written. `npm run tokens` rewrites the file.
 */

/** A value that is the same in both themes, or one value for each theme id. */
export type ThemedValue = string | Record<string, string>;

/** A token with a name, a value and a note on where it is used. */
export interface Token<V extends ThemedValue = string> {
    name: string;
    value: V;
    usage?: string;
}

/** One text style: size, unitless line height and weight. */
export interface TextStyle {
    name: string;
    fontSize: string;
    lineHeight: number;
    fontWeight: number;
}

/** The shape of `tokens.json`. */
export interface DesignTokens {
    color: {
        themes: { id: string; name: string }[];
        tokens: Token<ThemedValue>[];
    };
    type: {
        families: Record<string, string>;
        groups: { name: string; styles: TextStyle[] }[];
    };
    spacing: { tokens: Token[] };
    radius: { tokens: Token[] };
    shadow: { tokens: Token<ThemedValue>[] };
    size: { tokens: Token[] };
}

/** Thrown when a token cannot be turned into CSS; the message names the token. */
export class TokenError extends Error {
    /**
     * @param token Name of the token that is wrong.
     * @param reason What is wrong with it.
     */
    constructor(token: string, reason: string) {
        super(`Token "${token}": ${reason}`);
        this.name = 'TokenError';
    }
}

const NOTICE =
    '/* Family Manager — generated from resources/design/tokens.json by npm run tokens: do not edit */';

const COLOR_PATTERN = /^(#[0-9a-f]{3,8}|(rgb|rgba|hsl|hsla)\([^)]*\))$/i;
const ALIAS_PATTERN = /^\{([^}]+)\}$/;

/**
 * The value of a token for one theme: the theme's own value, or the light one
 * when the token has no value for that theme.
 *
 * @param token Name of the token, for the error message.
 * @param value A plain value or one value per theme.
 * @param theme Theme id (`light`, `dark`).
 * @returns The value to write.
 * @throws TokenError When the value has neither the theme nor a light value.
 */
function forTheme(token: string, value: ThemedValue, theme: string): string {
    if (typeof value === 'string') {
        return value;
    }

    const found = value[theme] ?? value.light;

    if (found === undefined) {
        throw new TokenError(
            token,
            `has no value for the "${theme}" theme and no light value to fall back on`,
        );
    }

    return found;
}

/**
 * Writes one colour value: an alias becomes `var(--name)`, anything else must
 * be a hex, rgb or hsl colour and is written as it is.
 *
 * @param token Name of the colour, for the error message.
 * @param value The value from the tokens.
 * @param names Names of all the colours, to check aliases.
 * @returns The CSS value.
 * @throws TokenError When the value is not a colour or the alias points nowhere.
 */
function colorValue(token: string, value: string, names: Set<string>): string {
    const alias = ALIAS_PATTERN.exec(value);

    if (alias) {
        if (!names.has(alias[1])) {
            throw new TokenError(
                token,
                `the alias ${value} points to a colour that does not exist`,
            );
        }

        return `var(--${alias[1]})`;
    }

    if (!COLOR_PATTERN.test(value)) {
        throw new TokenError(token, `"${value}" is not a colour`);
    }

    return value;
}

/**
 * Checks that every spacing is a multiple of 2px, so that Tailwind (4px per
 * step) can name it with a half step at the least.
 *
 * @param tokens The spacing tokens.
 * @throws TokenError For a spacing that is not a multiple of 2px.
 */
function checkSpacing(tokens: Token[]): void {
    for (const { name, value } of tokens) {
        const px = /^(\d+(?:\.\d+)?)px$/.exec(value);

        if (!px || Number(px[1]) % 2 !== 0) {
            throw new TokenError(name, `"${value}" is not a multiple of 2px`);
        }
    }
}

/**
 * Builds `tokens.css`: raw colours and shadow values for the light theme in `:root`
 * and for the dark one in `.dark`, Tailwind's names for them in `@theme inline`,
 * and text styles, font, radii and sizes in `@theme`. The output follows the
 * order of the tokens and ends with one newline.
 *
 * @param tokens The content of `tokens.json`.
 * @returns The CSS text.
 * @throws TokenError For a colour that cannot be read or a spacing that is not a Tailwind step.
 */
export function buildTokensCss(tokens: DesignTokens): string {
    checkSpacing(tokens.spacing.tokens);

    const names = new Set(tokens.color.tokens.map((token) => token.name));
    const colors = (theme: string): string[] =>
        tokens.color.tokens.map(
            ({ name, value }) =>
                `    --${name}: ${colorValue(name, forTheme(name, value, theme), names)};`,
        );

    const inline = tokens.color.tokens.map(
        ({ name }) => `    --color-${name}: var(--${name});`,
    );

    const fonts = Object.entries(tokens.type.families).map(
        ([name, family]) => `    --font-${name}: ${family};`,
    );
    const texts = tokens.type.groups.flatMap((group) =>
        group.styles.flatMap((style) => [
            `    --text-${style.name}: ${style.fontSize};`,
            `    --text-${style.name}--line-height: ${style.lineHeight};`,
            `    --text-${style.name}--font-weight: ${style.fontWeight};`,
        ]),
    );
    const radii = tokens.radius.tokens.map(
        ({ name, value }) => `    --${name}: ${value};`,
    );
    const sizes = tokens.size.tokens.map(
        ({ name, value }) => `    --spacing-${name}: ${value};`,
    );
    // Tailwind parses a shadow written in @theme and inlines its colour, so a
    // dark override would never apply: the values are raw variables per theme
    // (`--<name>-value`) and `@theme inline` points the shadow at them.
    const shadows = (theme: string): string[] =>
        tokens.shadow.tokens.map(
            ({ name, value }) =>
                `    --${name}-value: ${forTheme(name, value, theme)};`,
        );
    const inlineShadows = tokens.shadow.tokens.map(
        ({ name }) => `    --${name}: var(--${name}-value);`,
    );

    const block = (selector: string, lines: string[]): string =>
        `${selector} {\n${lines.join('\n')}\n}`;

    return (
        [
            NOTICE,
            block(':root', [...colors('light'), ...shadows('light')]),
            block('.dark', [...colors('dark'), ...shadows('dark')]),
            block('@theme inline', [...inline, ...inlineShadows]),
            block('@theme', [...fonts, ...texts, ...radii, ...sizes]),
        ].join('\n\n') + '\n'
    );
}

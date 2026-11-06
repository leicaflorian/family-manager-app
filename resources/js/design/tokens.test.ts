// @vitest-environment node
/// <reference types="node" />
import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vite-plus/test';
import type { DesignTokens } from './tokens';
import { buildTokensCss, TokenError } from './tokens';

/** A small set of tokens, with the given parts replaced. */
function fixture(parts: Partial<DesignTokens> = {}): DesignTokens {
    return {
        color: {
            themes: [
                { id: 'light', name: 'Light' },
                { id: 'dark', name: 'Dark' },
            ],
            tokens: [
                {
                    name: 'ink',
                    value: { light: '#18212F', dark: '#E8ECF2' },
                    usage: '',
                },
            ],
        },
        type: {
            families: { sans: 'Inter, ui-sans-serif, sans-serif' },
            groups: [
                {
                    name: 'Text',
                    styles: [
                        {
                            name: 'body',
                            fontSize: '16px',
                            lineHeight: 1.45,
                            fontWeight: 400,
                        },
                    ],
                },
            ],
        },
        spacing: { tokens: [{ name: 'space-1', value: '4px', usage: '' }] },
        radius: { tokens: [{ name: 'radius-card', value: '16px', usage: '' }] },
        shadow: {
            tokens: [
                {
                    name: 'shadow-menu',
                    value: {
                        light: '0 12px 32px rgba(1,2,3,0.16)',
                        dark: '0 12px 32px rgba(0,0,0,0.55)',
                    },
                    usage: '',
                },
            ],
        },
        size: { tokens: [{ name: 'control-md', value: '44px', usage: '' }] },
        ...parts,
    };
}

/** The text of the first block that starts with the given selector. */
function block(css: string, selector: string): string {
    const start = css.indexOf(`${selector} {`);
    expect(start).toBeGreaterThanOrEqual(0);

    return css.slice(start, css.indexOf('\n}', start));
}

/** A fixture whose colours are the given tokens. */
function withColors(tokens: DesignTokens['color']['tokens']): DesignTokens {
    return fixture({ color: { themes: fixture().color.themes, tokens } });
}

describe('buildTokensCss', () => {
    it('writes each colour for the light theme in :root and for the dark one in .dark', () => {
        const css = buildTokensCss(fixture());

        expect(block(css, ':root')).toContain('--ink: #18212F;');
        expect(block(css, '.dark')).toContain('--ink: #E8ECF2;');
        expect(block(css, ':root')).not.toContain('#E8ECF2');
    });

    it('maps every colour into Tailwind', () => {
        expect(block(buildTokensCss(fixture()), '@theme inline')).toContain(
            '--color-ink: var(--ink);',
        );
    });

    it('keeps the light value when a colour has no dark one', () => {
        const css = buildTokensCss(
            withColors([{ name: 'plain', value: '#ffffff', usage: '' }]),
        );

        expect(block(css, ':root')).toContain('--plain: #ffffff;');
        expect(block(css, '.dark')).toContain('--plain: #ffffff;');
    });

    it('writes translucent colours as they are', () => {
        const css = buildTokensCss(
            withColors([
                {
                    name: 'overlay',
                    value: { light: 'rgba(24,33,47,0.45)', dark: '#0008' },
                    usage: '',
                },
            ]),
        );

        expect(block(css, ':root')).toContain(
            '--overlay: rgba(24,33,47,0.45);',
        );
        expect(block(css, '.dark')).toContain('--overlay: #0008;');
    });

    it('resolves an alias to another colour', () => {
        const css = buildTokensCss(
            withColors([
                { name: 'action', value: '#2563eb', usage: '' },
                { name: 'link', value: '{action}', usage: '' },
            ]),
        );

        expect(block(css, ':root')).toContain('--link: var(--action);');
    });

    it('writes text styles with line height and weight', () => {
        const css = buildTokensCss(fixture());

        expect(css).toContain('--text-body: 16px;');
        expect(css).toContain('--text-body--line-height: 1.45;');
        expect(css).toContain('--text-body--font-weight: 400;');
    });

    it('writes radii, shadows and control sizes', () => {
        const css = buildTokensCss(fixture());

        expect(block(css, '@theme')).toContain('--radius-card: 16px;');
        expect(css).toContain('--spacing-control-md: 44px;');
    });

    it('writes each shadow as a raw variable per theme, mapped into Tailwind by reference', () => {
        const css = buildTokensCss(fixture());

        expect(block(css, ':root')).toContain(
            '--shadow-menu-value: 0 12px 32px rgba(1,2,3,0.16);',
        );
        expect(block(css, '.dark')).toContain(
            '--shadow-menu-value: 0 12px 32px rgba(0,0,0,0.55);',
        );
        expect(block(css, '@theme inline')).toContain(
            '--shadow-menu: var(--shadow-menu-value);',
        );
        // Tailwind would inline a literal value and ignore the dark override
        expect(block(css, '@theme')).not.toMatch(/--shadow-menu: \d/);
    });

    it('keeps the light value of a shadow that has no dark one', () => {
        const css = buildTokensCss(
            fixture({
                shadow: {
                    tokens: [
                        {
                            name: 'shadow-flat',
                            value: '0 1px 2px #000',
                            usage: '',
                        },
                    ],
                },
            }),
        );

        expect(block(css, '.dark')).toContain(
            '--shadow-flat-value: 0 1px 2px #000;',
        );
    });

    it('throws a TokenError naming the token when a themed value has neither the theme nor a light value', () => {
        const bad = fixture({
            shadow: {
                tokens: [
                    {
                        name: 'shadow-odd',
                        value: { dark: '0 0 1px #000' },
                        usage: '',
                    },
                ],
            },
        });

        expect(() => buildTokensCss(bad)).toThrow(TokenError);
        expect(() => buildTokensCss(bad)).toThrow(/shadow-odd/);
    });

    it('writes the font family', () => {
        expect(buildTokensCss(fixture())).toContain(
            '--font-sans: Inter, ui-sans-serif, sans-serif;',
        );
    });

    it('does not write the spacings', () => {
        expect(buildTokensCss(fixture())).not.toContain('space-1');
    });

    it('throws a TokenError naming the token for an unreadable colour', () => {
        const bad = withColors([{ name: 'edge', value: 'red', usage: '' }]);

        expect(() => buildTokensCss(bad)).toThrow(TokenError);
        expect(() => buildTokensCss(bad)).toThrow(/edge/);
    });

    it('throws a TokenError naming the token for an alias to a missing colour', () => {
        const bad = withColors([
            { name: 'link', value: '{nowhere}', usage: '' },
        ]);

        expect(() => buildTokensCss(bad)).toThrow(TokenError);
        expect(() => buildTokensCss(bad)).toThrow(/link/);
    });

    it('checks that every spacing is a Tailwind step', () => {
        const bad = fixture({
            spacing: {
                tokens: [{ name: 'space-odd', value: '5px', usage: '' }],
            },
        });

        expect(() => buildTokensCss(bad)).toThrow(/space-odd/);
    });

    it('ends with a newline and starts with the generated notice', () => {
        const css = buildTokensCss(fixture());

        expect(css.startsWith('/* Family Manager')).toBe(true);
        expect(css.endsWith('\n')).toBe(true);
        expect(css.endsWith('\n\n')).toBe(false);
    });

    it('keeps tokens.css in step with tokens.json', async () => {
        const json = JSON.parse(
            readFileSync('resources/design/tokens.json', 'utf8'),
        ) as DesignTokens;

        await expect(buildTokensCss(json)).toMatchFileSnapshot(
            '../../css/tokens.css',
        );
    });
});

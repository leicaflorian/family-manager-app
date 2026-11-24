import { describe, expect, it } from 'vite-plus/test';
import type { PageInfo } from './pageInfo';
import { PAGE_INFO_KEYS, pageInfo } from './pageInfo';

// No type argument on the glob: see `.claude/learnings.md`, the Vitest file
// cache would keep an old list of files.
const sources: Record<string, string> = import.meta.glob(
    ['../pages/**/*.vue', '!../pages/**/*.test.ts'],
    { query: '?raw', import: 'default', eager: true },
);

/** A page that shows a title the «i» goes beside. */
const TITLED = /<(FmTitle|FmCardHeader|FmSettingsPage|FmFamilyViews)\b/;

/** The key a page gives: `info: 'key'` to `usePageMeta`, `info-key="key"` to `FmPageInfo`, or its view to `FmFamilyViews`. */
const KEY =
    /\binfo(?:-key)?(?::\s*'|=")([a-z-]+)['"]|<FmFamilyViews[^>]*\bcurrent="([a-z-]+)"/g;

/** Every text of an explanation, in one list. */
function texts(info: PageInfo): string[] {
    return [
        info.title,
        info.purpose,
        ...info.steps.flatMap((step) => [step.text, step.link?.label ?? '']),
        ...(info.notes ?? []),
        info.link?.label ?? '',
    ].filter((text) => text !== '');
}

/** The features of a family with the Budget off and everything else on. */
const BUDGET_OFF = {
    budget: false,
    mail: true,
    bank_feed: false,
    consumption: false,
    gallery: true,
};

describe('pageInfo', () => {
    it('gives a key to every page with a title', () => {
        const missing = Object.entries(sources)
            .filter(([, source]) => {
                if (!TITLED.test(source)) {
                    return false;
                }

                const keys = [...source.matchAll(KEY)].map(
                    (match) => match[1] ?? match[2],
                );

                return !(
                    keys.length > 0 &&
                    keys.every((key) =>
                        (PAGE_INFO_KEYS as readonly string[]).includes(key),
                    )
                );
            })
            .map(([path]) => path);

        expect(missing).toEqual([]);
    });

    it('gives a key to the scanner and to the linked phone, which draw their own title', () => {
        for (const page of ['scan/Scanner.vue', 'connect/Linked.vue']) {
            const source = sources[`../pages/${page}`];

            expect(source, page).toBeDefined();
            expect([...(source ?? '').matchAll(KEY)].length, page).toBe(1);
        }
    });

    it('uses every key on a page', () => {
        const used = new Set(
            Object.values(sources).flatMap((source) =>
                [...source.matchAll(KEY)].map((match) => match[1] ?? match[2]),
            ),
        );

        expect(PAGE_INFO_KEYS.filter((key) => !used.has(key))).toEqual([]);
    });

    it('has a title, a purpose and at least one step for every page', () => {
        for (const key of PAGE_INFO_KEYS) {
            const info = pageInfo(key, null);

            expect(info.title, key).not.toBe('');
            expect(info.purpose, key).not.toBe('');
            expect(info.steps.length, key).toBeGreaterThan(0);
        }
    });

    it('says nothing about money with the Budget off, on every page the family can open', () => {
        const money =
            /\b(expenses?|incomes?|payments?|pay|paid|money|budget|banks?|amounts?|spent|euros?)\b|€/i;

        const found = PAGE_INFO_KEYS.filter((key) => {
            const info = pageInfo(key, BUDGET_OFF);

            return (
                (info.feature === undefined || BUDGET_OFF[info.feature]) &&
                texts(info).some((text) => money.test(text))
            );
        });

        expect(found).toEqual([]);
    });

    it('takes away the lines of a feature that is off and keeps them with everything on', () => {
        const on = pageInfo('home', null);
        const off = pageInfo('home', BUDGET_OFF);

        expect(texts(on).join(' ')).toMatch(/pay/i);
        expect(texts(off).join(' ')).not.toMatch(/pay/i);
    });

    it('drops the link of a feature that is off', () => {
        expect(pageInfo('mail', null).link).toBeDefined();
        expect(
            pageInfo('to-sort', { ...BUDGET_OFF, mail: false }).link,
        ).toBeUndefined();
    });

    it('writes the mail page as the board Posta-info: the address to copy and the link to To sort', () => {
        const info = pageInfo('mail', null);

        expect(info.feature).toBe('mail');
        expect(info.steps[0]?.copy).toBe('address');
        expect(info.steps[2]?.text).toContain(':link');
        expect(info.steps[2]?.link?.href()).toBe('/to-sort');
        expect(info.link?.href()).toBe('/settings/mail');
    });
});

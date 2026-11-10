import { readdirSync, readFileSync } from 'node:fs';
import { dirname, join, relative, resolve } from 'node:path';
import { describe, expect, it } from 'vite-plus/test';
import { pageLayout } from '@/lib/pageLayout';
import { isSectionPage } from '@/lib/pageTrail';
import AppLayout from '@/layouts/AppLayout.vue';

/** The folder of the pages. */
const root = join(process.cwd(), 'resources/js/pages');

/**
 * The page components, without the tests.
 *
 * @param folder - where to look
 * @returns the paths of the `.vue` files, from the folder of the pages
 */
function pages(folder: string = root): string[] {
    return readdirSync(folder, { withFileTypes: true }).flatMap((entry) => {
        const path = join(folder, entry.name);

        if (entry.isDirectory()) {
            return pages(path);
        }

        return entry.name.endsWith('.vue') ? [relative(root, path)] : [];
    });
}

describe('the pages', () => {
    // A page that another page imports at the top becomes a shared piece of the
    // build: the manifest loses its name, and Laravel's @vite cannot find it
    // (500 on that page once built). One page inside another loads it with
    // import(), which keeps it a page of its own.
    it('never import another page at the top', () => {
        const offenders = pages().filter((page) =>
            [
                ...readFileSync(join(root, page), 'utf8').matchAll(
                    /^import\s+\w+\s+from\s+['"]([^'"]+\.vue)['"]/gm,
                ),
            ].some(([, path]) => {
                const target = path.startsWith('@/')
                    ? join(process.cwd(), 'resources/js', path.slice(2))
                    : resolve(dirname(join(root, page)), path);

                return target.startsWith(`${root}/`);
            }),
        );

        expect(offenders).toEqual([]);
    });

    // The top bar shows the trail the page writes: a page of the app layout that
    // is not a section (or one of its tabs) must call usePageTrail().
    it('every page of the app layout tells its trail', () => {
        const missing = pages()
            .map((page) => page.replace(/\.vue$/, ''))
            .filter(
                (name) =>
                    pageLayout(name) === AppLayout && !isSectionPage(name),
            )
            .filter(
                (name) =>
                    !readFileSync(join(root, `${name}.vue`), 'utf8').includes(
                        'usePageTrail(',
                    ),
            );

        expect(missing).toEqual([]);
    });
});

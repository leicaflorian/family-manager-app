/// <reference types="node" />
/**
 * Reads the components of the canvas (`resources/js/components/fm/`) for the
 * component catalogue: props, events and slots from `vue-component-meta`,
 * description and group from the TSDoc, the exported types and where each
 * component is used. Node only: it reads the files from the disk.
 */
import { readdirSync, readFileSync } from 'node:fs';
import { join, relative } from 'node:path';
import { createChecker } from 'vue-component-meta';

/** One prop of a component, as the catalogue shows it. */
export type CatalogProp = {
    name: string;
    type: string;
    required: boolean;
    default: string | null;
    description: string;
};

/** One component of the canvas, as the catalogue shows it. */
export type CatalogEntry = {
    /** The name of the component, as `FmRow`. */
    name: string;
    /** The path from the root, as `resources/js/components/fm/FmRow.vue`. */
    path: string;
    /** The value of `@group`, even when it is not one of the known groups. */
    group: string | null;
    /** The TSDoc of the component without the tags, '' when missing. */
    description: string;
    /** The props, without the global ones. */
    props: CatalogProp[];
    events: string[];
    slots: string[];
    /** The `export type …` lines of the component, whole. */
    types: string[];
    /** Paths from `resources/js` of the files that import it, sorted. */
    usedBy: string[];
};

const componentsDir = 'resources/js/components/fm';
const sourcesDir = 'resources/js';
const scriptTag = '<script setup lang="ts">';

/**
 * The description and the `@group` of a component, from the first `/** … *\/`
 * block after `<script setup lang="ts">`.
 *
 * @param source - The source of the `.vue` file.
 * @returns The description without the tags ('' without a TSDoc) and the group.
 */
export function parseDoc(source: string): {
    description: string;
    group: string | null;
} {
    const script = source.indexOf(scriptTag);
    const block =
        script === -1
            ? null
            : /\/\*\*([\s\S]*?)\*\//.exec(
                  source.slice(script + scriptTag.length),
              );

    if (!block) {
        return { description: '', group: null };
    }

    const lines = (block[1] ?? '')
        .split('\n')
        .map((line) => line.replace(/^\s*\* ?/, '').trimEnd());
    const firstTag = lines.findIndex((line) => line.startsWith('@'));
    const description = (firstTag === -1 ? lines : lines.slice(0, firstTag))
        .join('\n')
        .trim();
    const group = /^@group[ \t]+(\S+)/m.exec(lines.join('\n'));

    return { description, group: group?.[1] ?? null };
}

/**
 * Every component of the canvas, read with one `vue-component-meta` checker
 * on the `tsconfig.json` of the root.
 *
 * @param root - The root of the project.
 * @returns The entries of the catalogue, sorted by name.
 */
export function readCatalog(root: string): CatalogEntry[] {
    const checker = createChecker(join(root, 'tsconfig.json'), {
        schema: false,
    });
    const sources = sourceFiles(root);

    return readdirSync(join(root, componentsDir))
        .filter((file) => file.endsWith('.vue'))
        .sort()
        .map((file): CatalogEntry => {
            const name = file.replace(/\.vue$/, '');
            const path = `${componentsDir}/${file}`;
            const source = readFileSync(join(root, path), 'utf8');
            const meta = checker.getComponentMeta(join(root, path));

            return {
                name,
                path,
                ...parseDoc(source),
                props: meta.props
                    .filter((prop) => !prop.global)
                    .map((prop) => ({
                        name: prop.name,
                        // Optional props come as `T | undefined`: `required` says it already
                        type: prop.required
                            ? prop.type
                            : prop.type.replace(/ \| undefined$/, ''),
                        required: prop.required,
                        default: prop.default ?? null,
                        description: prop.description,
                    })),
                events: meta.events.map((event) => event.name),
                slots: meta.slots.map((slot) => slot.name),
                types: source.match(/^export type .*$/gm) ?? [],
                usedBy: usedBy(name, sources),
            };
        });
}

/**
 * The `.vue` and `.ts` files of `resources/js` that can use a component:
 * without the tests and the examples of the catalogue.
 *
 * @param root - The root of the project.
 * @returns Each file's path from `resources/js` with its source.
 */
function sourceFiles(root: string): { path: string; source: string }[] {
    const dir = join(root, sourcesDir);

    return readdirSync(dir, { recursive: true, encoding: 'utf8' })
        .filter(
            (file) =>
                /\.(vue|ts)$/.test(file) &&
                !/\.(test|examples)\.ts$/.test(file),
        )
        .map((file) => ({
            path: relative(dir, join(dir, file)),
            source: readFileSync(join(dir, file), 'utf8'),
        }));
}

/**
 * The files that import a component.
 *
 * @param name - The name of the component.
 * @param sources - The files to look in.
 * @returns The paths from `resources/js`, sorted.
 */
function usedBy(
    name: string,
    sources: { path: string; source: string }[],
): string[] {
    return sources
        .filter(({ source }) => importsComponent(source, name))
        .map(({ path }) => path)
        .sort();
}

/**
 * Whether a source imports a component, by its file name at the end of the
 * path: a static import at the start of a line (with names, over more lines
 * too, or only for its side effects) or a dynamic `import()` of the file. A
 * mention of the file in a comment or in a string does not count.
 *
 * @param source - The source of a `.vue` or `.ts` file.
 * @param name - The name of the component, as `FmRow`.
 * @returns True when the source imports it.
 */
export function importsComponent(source: string, name: string): boolean {
    const path = `['"](?:[^'"]*/)?${name}\\.vue['"]`;
    // Anchored at the start of a line, so a comment line (` * …`) never matches
    const staticImport = new RegExp(
        `^\\s*import\\s+(?:[^'";]*?\\s+from\\s+)?${path}`,
        'm',
    );
    const dynamicImport = new RegExp(`\\bimport\\(\\s*${path}\\s*\\)`);

    return staticImport.test(source) || dynamicImport.test(source);
}

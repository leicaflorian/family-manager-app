/// <reference types="node" />
/**
 * The check of the component catalogue: what a component of the canvas still
 * lacks to be in it (TSDoc, a known `@group`, its `.examples.ts` with cases, its test).
 * The catalogue test fails when the list is not empty.
 */
import { existsSync } from 'node:fs';
import { join } from 'node:path';
import type { ComponentExamples } from './examples';
import { groups } from './groups';
import type { CatalogEntry } from './meta';

/**
 * One line for each thing a component lacks.
 *
 * @param entries - The components, as `readCatalog` reads them.
 * @param examples - The examples by component name, as `loadExamples` gives them.
 * @param root - The root the paths of the entries are relative to.
 * @returns The problems, as `FmX: no TSDoc`; empty when all is in place.
 */
export function problems(
    entries: CatalogEntry[],
    examples: Record<string, ComponentExamples>,
    root: string = process.cwd(),
): string[] {
    return entries.flatMap((entry) => {
        const found: string[] = [];
        const cases = examples[entry.name];

        if (entry.description === '') {
            found.push(`${entry.name}: no TSDoc`);
        }

        if (entry.group === null) {
            // A component without TSDoc has no group either: one line is enough
            if (entry.description !== '') {
                found.push(`${entry.name}: no @group`);
            }
        } else if (!(groups as readonly string[]).includes(entry.group)) {
            found.push(`${entry.name}: unknown group "${entry.group}"`);
        }

        if (!cases) {
            found.push(`${entry.name}: no ${entry.name}.examples.ts`);
        } else if (cases.examples.length === 0) {
            found.push(`${entry.name}: no cases`);
        }

        if (!existsSync(join(root, entry.path.replace(/\.vue$/, '.test.ts')))) {
            found.push(`${entry.name}: no ${entry.name}.test.ts`);
        }

        return found;
    });
}

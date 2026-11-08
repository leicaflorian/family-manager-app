/// <reference types="node" />
/**
 * The check of the stories: what a component of the canvas still lacks to be
 * in Storybook (TSDoc, a known `@group`, its test, its `.stories.ts` with at
 * least one story under the right title). `stories.test.ts` fails when the
 * list is not empty.
 */
import { existsSync, readdirSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import { parseDoc } from './doc';
import type { Group } from './groups';
import { groupLabel, groups } from './groups';

/** A story file as the tests load it: the `meta` and one export for each story. */
export type StoryModule = {
    default: { title?: string };
    [story: string]: unknown;
};

const componentsDir = 'resources/js/components/fm';

/**
 * One line for each thing a component lacks, in the order of the names.
 *
 * @param root - The root of the project.
 * @param stories - The story files by component name, as `loadStories` gives them.
 * @returns The problems, as `FmX: no TSDoc`; empty when all is in place.
 */
export function problems(
    root: string,
    stories: Record<string, StoryModule>,
): string[] {
    return readdirSync(join(root, componentsDir))
        .filter((file) => file.endsWith('.vue'))
        .sort()
        .flatMap((file) => {
            const name = file.replace(/\.vue$/, '');
            const { description, group } = parseDoc(
                readFileSync(join(root, componentsDir, file), 'utf8'),
            );
            const found: string[] = [];

            if (description === '') {
                found.push(`${name}: no TSDoc`);
            }

            // A component without TSDoc has no group either: one line is enough
            if (group === null) {
                if (description !== '') {
                    found.push(`${name}: no @group`);
                }
            } else if (!(groups as readonly string[]).includes(group)) {
                found.push(`${name}: unknown group "${group}"`);
            }

            if (!existsSync(join(root, componentsDir, `${name}.test.ts`))) {
                found.push(`${name}: no ${name}.test.ts`);
            }

            found.push(...storyProblems(name, group, stories[name]));

            return found;
        });
}

/**
 * What the story file of a component lacks.
 *
 * @param name - The name of the component.
 * @param group - Its `@group`, or null.
 * @param module - Its story file, or undefined when there is none.
 * @returns The problems of the file.
 */
function storyProblems(
    name: string,
    group: string | null,
    module: StoryModule | undefined,
): string[] {
    if (!module) {
        return [`${name} has no stories: add ${name}.stories.ts`];
    }

    const found: string[] = [];

    if (Object.keys(module).every((key) => key === 'default')) {
        found.push(`${name}: no stories`);
    }

    if ((groups as readonly string[]).includes(group ?? '')) {
        const expected = `${groupLabel(group as Group)}/${name}`;

        if (module.default.title !== expected) {
            found.push(
                `${name}: the title is "${module.default.title}", expected "${expected}"`,
            );
        }
    }

    return found;
}

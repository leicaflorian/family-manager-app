/**
 * Reads the TSDoc of a component of the canvas (`resources/js/components/fm/`):
 * its description and its `@group`, which the check of the stories needs.
 */

// A generic component writes its attributes on several lines.
const scriptTag = /<script\b[^>]*\bsetup\b[^>]*>/;

/**
 * The description and the `@group` of a component, from the first `/** … *\/`
 * block after its `<script setup>` tag.
 *
 * @param source - The source of the `.vue` file.
 * @returns The description without the tags ('' without a TSDoc) and the group.
 */
export function parseDoc(source: string): {
    description: string;
    group: string | null;
} {
    const script = scriptTag.exec(source);
    const block = script
        ? /\/\*\*([\s\S]*?)\*\//.exec(
              source.slice(script.index + script[0].length),
          )
        : null;

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

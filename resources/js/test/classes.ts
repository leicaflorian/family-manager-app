/**
 * Reading the Tailwind classes written in the source files, for the tests of
 * the design system (`resources/js/design/*.test.ts`): the classes of one
 * element are written in one `cn(…)` call or in one `class="…"`.
 */

/**
 * The text of each `cn(…)` call of a file, up to its closing bracket.
 *
 * @param source - The text of the file.
 * @returns The arguments of every call, as written.
 */
export function cnCalls(source: string): string[] {
    const calls: string[] = [];
    const opening = /\bcn\(/g;
    let match: RegExpExecArray | null;

    while ((match = opening.exec(source)) !== null) {
        let depth = 1;
        let at = match.index + match[0].length;
        let quote: string | null = null;

        while (at < source.length && depth > 0) {
            const char = source[at];

            if (quote !== null) {
                quote =
                    char === quote && source[at - 1] !== '\\' ? null : quote;
            } else if (char === "'" || char === '"' || char === '`') {
                quote = char;
            } else if (char === '(') {
                depth++;
            } else if (char === ')') {
                depth--;
            }

            at++;
        }

        calls.push(source.slice(match.index + match[0].length, at - 1));
    }

    return calls;
}

/**
 * The classes written in a piece of code: the words of its quoted strings.
 *
 * @param code - Arguments of a call, or the value of a `class`.
 * @returns Every class, once.
 */
export function classesOf(code: string): Set<string> {
    const strings = code.match(/'[^']*'|"[^"]*"|`[^`]*`/g) ?? [];

    return new Set(
        strings.flatMap((string) => string.slice(1, -1).split(/\s+/)),
    );
}

/**
 * The groups of classes of a file, one per element: every `cn(…)` call and
 * every `class="…"`.
 *
 * @param source - The text of the file.
 * @returns The classes of each group.
 */
export function classGroups(source: string): string[][] {
    return [
        ...cnCalls(source),
        ...[...source.matchAll(/\sclass="([^"]*)"/g)].map(
            (match) => `"${match[1]}"`,
        ),
    ].map((group) => [...classesOf(group)]);
}

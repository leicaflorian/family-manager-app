import type { InertiaLinkProps } from '@inertiajs/vue3';
import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { extendTailwindMerge } from 'tailwind-merge';
import { radius, shadow, size, type as text } from '../../design/tokens.json';

/**
 * The names of a group of tokens as Tailwind writes them, without the prefix
 * of the group (`shadow-menu` becomes `menu`).
 *
 * @param tokens - The tokens of the group.
 * @param prefix - The prefix of their names, if they have one.
 * @returns The names after the prefix.
 */
function names(tokens: { name: string }[], prefix = ''): string[] {
    return tokens.map(({ name }) => name.slice(prefix.length));
}

/**
 * tailwind-merge with the sizes, shadows, radii and text styles of the design
 * tokens: without them `size-control-md` and `size-10` would not clash, and
 * `text-meta` would pass for a colour and push `text-ink` out.
 */
const twMerge = extendTailwindMerge({
    extend: {
        theme: {
            spacing: names(size.tokens),
            shadow: names(shadow.tokens, 'shadow-'),
            radius: names(radius.tokens, 'radius-'),
            text: text.groups.flatMap((group) => names(group.styles)),
        },
    },
});

/**
 * Joins Tailwind classes, leaving out the empty ones; of two classes that clash
 * the last one wins. Every class written in the code outside the template goes
 * through here, so the editor suggests the Tailwind classes there too.
 *
 * @param inputs - strings, conditions, arrays or objects of classes
 * @returns the classes in one string
 */
export function cn(...inputs: ClassValue[]): string {
    return twMerge(clsx(inputs));
}

export function toUrl(href: NonNullable<InertiaLinkProps['href']>) {
    return typeof href === 'string' ? href : href?.url;
}

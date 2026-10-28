import type { InertiaLinkProps } from '@inertiajs/vue3';
import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

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

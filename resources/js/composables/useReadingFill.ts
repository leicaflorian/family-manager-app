import type { ComputedRef } from 'vue';
import { computed, ref } from 'vue';
import type { DocumentFormValues, DocumentLink } from '@/lib/documents';
import { linkKey } from '@/lib/documents';

/** Which fields the reading may write: the ones not touched, or only the empty ones. */
export type FillMode = 'untouched' | 'empty';

/** What the composable gives back. */
export type ReadingFill = {
    /** Writes the values read into the fields the mode allows. */
    apply: (values: Partial<DocumentFormValues>) => void;
    /** Puts the values read back, as they were read. */
    restore: () => void;
    /** True when something was read and the form no longer holds it. */
    canRestore: ComputedRef<boolean>;
};

/**
 * Whether a value is empty for the form: no text, or no item.
 *
 * @param value - the value of a field
 * @returns true when nothing is written
 */
function isEmpty(value: DocumentFormValues[keyof DocumentFormValues]): boolean {
    return Array.isArray(value) ? value.length === 0 : value === '';
}

/**
 * Whether two values of a field are the same.
 *
 * @param a - one value
 * @param b - the other
 * @returns true when they are equal
 */
function same(
    a: DocumentFormValues[keyof DocumentFormValues],
    b: DocumentFormValues[keyof DocumentFormValues],
): boolean {
    return JSON.stringify(a) === JSON.stringify(b);
}

/**
 * The reading of a document written into a form without trampling the person:
 * a field is filled only if the person did not touch it (it still holds its
 * starting value) or, in `empty` mode, if it is empty; the links add up to the
 * ones already chosen. What was read is remembered, so `restore()` can put it
 * back after the person changed it. A field the reading filled is the person's
 * once its value differs from what was written, so a second reading never
 * overwrites an edit, while a different one still updates what is untouched.
 *
 * @param form - the reactive values of the form
 * @param initial - the values the form started from
 * @param mode - which fields the reading may write
 * @returns `apply()`, `restore()` and `canRestore`
 */
export function useReadingFill(
    form: DocumentFormValues,
    initial: DocumentFormValues,
    mode: FillMode,
): ReadingFill {
    /** The values read so far, as the form holds them after applying. */
    const read = ref<Partial<DocumentFormValues>>({});

    /** The value this composable last wrote in each field: any other value there is the person's. */
    const written: Partial<DocumentFormValues> = {};

    /**
     * Writes one field.
     *
     * @param key - the field
     * @param value - its new value
     */
    function write<K extends keyof DocumentFormValues>(
        key: K,
        value: DocumentFormValues[K],
    ): void {
        form[key] = value;
        read.value = { ...read.value, [key]: value };
        written[key] = value;
    }

    /**
     * Adds to the links of the form the ones not there yet.
     *
     * @param links - the links read
     * @returns the links of the form after the sum
     */
    function sumLinks(links: DocumentLink[]): DocumentLink[] {
        const chosen = new Set(form.links.map(linkKey));

        return [...form.links, ...links.filter((l) => !chosen.has(linkKey(l)))];
    }

    /**
     * Whether the reading may write a field.
     *
     * @param key - the field
     * @returns true when the person has not made it theirs
     */
    function writable(key: keyof DocumentFormValues): boolean {
        const last = written[key];

        // A field already filled by a reading is the person's as soon as it differs from it,
        // even if they emptied it or typed the starting value back
        if (last !== undefined) {
            return same(form[key], last);
        }

        return mode === 'empty'
            ? isEmpty(form[key])
            : same(form[key], initial[key]);
    }

    /**
     * Writes the values read into the fields the mode allows.
     *
     * @param values - the values the reading found
     */
    function apply(values: Partial<DocumentFormValues>): void {
        const { links, ...fields } = values;

        for (const key of Object.keys(fields) as (keyof typeof fields)[]) {
            const value = fields[key];

            if (value !== undefined && writable(key)) {
                write(key, value as never);
            }
        }

        if (links !== undefined) {
            // Only the links read are remembered, not the ones chosen before
            form.links = sumLinks(links);
            read.value = { ...read.value, links };
        }
    }

    /** Puts the values read back; the links added by hand stay. */
    function restore(): void {
        const { links, ...fields } = read.value;

        for (const key of Object.keys(fields) as (keyof typeof fields)[]) {
            form[key] = fields[key] as never;
            written[key] = fields[key] as never;
        }

        if (links !== undefined) {
            form.links = sumLinks(links);
        }
    }

    const canRestore = computed(() =>
        (Object.keys(read.value) as (keyof DocumentFormValues)[]).some((key) =>
            key === 'links'
                ? (read.value.links ?? []).some(
                      (l) => !form.links.map(linkKey).includes(linkKey(l)),
                  )
                : !same(form[key], read.value[key] as never),
        ),
    );

    return { apply, restore, canRestore };
}

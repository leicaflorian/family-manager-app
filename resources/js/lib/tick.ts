import { cn } from '@/lib/utils';

/**
 * The classes of the square tick box of a checkbox row (the row of *Also do* of *Import*, the transactions of
 * the panel that writes a bank rule): blue with the tick when ticked, an empty bordered square when not, with the
 * focus ring of the hidden input that is its `peer`.
 *
 * @param ticked - whether the box is ticked
 * @returns the classes of the box
 */
export function tickBoxClass(ticked: boolean): string {
    return cn(
        'flex size-[22px] shrink-0 items-center justify-center rounded-sm peer-focus-visible:ring-2 peer-focus-visible:ring-action peer-focus-visible:ring-offset-2',
        ticked
            ? 'bg-action text-on-action'
            : 'border-2 border-ink-faint bg-card',
    );
}

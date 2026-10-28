/**
 * Whether a component sits on a coloured background, such as the top of a
 * card page: the buttons there turn translucent white, so the colour shows.
 */
import type { InjectionKey } from 'vue';
import { inject, provide } from 'vue';

const onColorKey: InjectionKey<boolean> = Symbol('onColor');

/**
 * Tells the components inside whether they sit on a colour. Called in the
 * `setup` of the component with the coloured background, and with `false` in
 * a white surface that can open from there (a panel in a portal still sees
 * what its parents provide).
 *
 * @param onColor - true on a colour, false on white
 */
export function provideOnColor(onColor = true): void {
    provide(onColorKey, onColor);
}

/**
 * Whether the component sits on a colour.
 *
 * @returns true inside a component that called `provideOnColor`
 */
export function useOnColor(): boolean {
    return inject(onColorKey, false);
}

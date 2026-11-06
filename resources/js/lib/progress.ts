/**
 * The colour of the bar that Inertia shows while a page loads: the `action`
 * token, read from the page when the app starts, so that it is the one of
 * `tokens.css` and not a colour written by hand.
 *
 * @returns The value of `--action`, empty when the tokens are not loaded.
 */
export function progressColor(): string {
    return getComputedStyle(document.documentElement)
        .getPropertyValue('--action')
        .trim();
}

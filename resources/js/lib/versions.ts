/** A released version, `major.minor.patch` (the `VERSION` file). */
const VERSION = /^(\d+)\.(\d+)\.(\d+)$/;

/**
 * Whether a value is a released version: `dev` in development, or an odd answer, is not.
 *
 * @param value - the version to look at
 * @returns true for `major.minor.patch`
 */
export function isReleasedVersion(value: string): boolean {
    return VERSION.test(value);
}

/**
 * Whether the server runs a newer version than the page. Anything that is not `major.minor.patch` on either side
 * (`dev` in development, an odd answer) is never newer, so it never asks the person to reload.
 *
 * @param server - the version the server answers
 * @param page - the version the page was built with (`__APP_BUILD__`)
 * @returns true only when the server's version is higher
 */
export function isNewerVersion(server: string, page: string): boolean {
    const a = VERSION.exec(server);
    const b = VERSION.exec(page);

    if (a === null || b === null) {
        return false;
    }

    for (let part = 1; part <= 3; part++) {
        const difference = Number(a[part]) - Number(b[part]);

        if (difference !== 0) {
            return difference > 0;
        }
    }

    return false;
}

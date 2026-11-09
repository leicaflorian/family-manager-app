/**
 * What the pages of the phone linking need to know about the browser: whether it is an iPhone, whether the page
 * was opened from the icon on the Home screen, and the secret of the phone kept after the `#` of the address.
 */

import { isIos, isStandalone } from './devices';

/** The start of the part after the `#` that holds the secret of the phone. */
const SECRET_KEY = 'd=';

/**
 * The secret of the phone in the address.
 *
 * @param hash - the part after the `#`, with or without it
 * @returns the secret, or null if there is none
 */
export function secretFromHash(hash: string): string | null {
    const value = hash.replace(/^#/, '');

    return value.startsWith(SECRET_KEY) && value.length > SECRET_KEY.length
        ? decodeURIComponent(value.slice(SECRET_KEY.length))
        : null;
}

/**
 * Puts the secret of the phone after the `#`, so the icon of the iPhone, which does not see the cookies of
 * Safari, starts from an address that holds it. The `#` never reaches the server or its logs.
 *
 * @param secret - the secret of the phone
 */
export function keepSecretInAddress(secret: string): void {
    history.replaceState(
        null,
        '',
        `${location.pathname}${location.search}#${SECRET_KEY}${encodeURIComponent(secret)}`,
    );
}

/**
 * An address of the app that carries the secret of this address with it: the scan, opened from the icon, needs it
 * to enter again when the session ends (the icon has no cookies).
 *
 * @param url - the address to go to
 * @returns the address, with the `#` of the secret when this address has one
 */
export function withKeptSecret(url: string): string {
    const secret = secretFromHash(location.hash);

    return secret ? `${url}#${SECRET_KEY}${encodeURIComponent(secret)}` : url;
}

/**
 * Takes the secret out of the address. On an iPhone in Safari it stays until the page is opened from the icon:
 * the icon saves the address as it is at that moment.
 */
export function cleanAddress(): void {
    if (isIos() && !isStandalone()) {
        return;
    }

    if (location.hash) {
        history.replaceState(null, '', location.pathname + location.search);
    }
}

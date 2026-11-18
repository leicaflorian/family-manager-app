import type { FormKitNode } from '@formkit/core';
/**
 * The menus searched by typing and the dates: a text box, but never a name or
 * a password to fill. The plain dropdown is a button, which they leave alone.
 */
const IGNORED = new Set(['autocomplete', 'taglist', 'datepicker']);

/**
 * The attributes that tell Bitwarden, 1Password and LastPass to leave a field
 * alone, plus `autocomplete="off"` for the browser.
 */
const IGNORE_ATTRS = {
    autocomplete: 'off',
    'data-bwignore': 'true',
    'data-1p-ignore': 'true',
    'data-lpignore': 'true',
} as const;

/**
 * A FormKit plugin: the password managers do not offer their logins on a menu
 * or a date. Bitwarden took the search box of «Link it to» for a name and
 * opened its list of identities over the options of the menu.
 *
 * @param node - a field that FormKit is creating
 */
export function ignorePasswordManagers(node: FormKitNode): void {
    if (!IGNORED.has(String(node.props.type))) {
        return;
    }

    // Attributes the page writes on the field win over these
    node.props.attrs = { ...IGNORE_ATTRS, ...node.props.attrs };
}

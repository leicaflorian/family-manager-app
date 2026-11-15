/**
 * The title of the browser tab: the title of the page, then the name of the app («Accedi - Fameja.app»).
 *
 * @param title - the title of the page, empty for none
 * @param app - the name of the app, shared by the server
 * @returns the whole title, or the name of the app alone
 */
export function pageTitle(title: string, app: string): string {
    return title ? `${title} - ${app}` : app;
}

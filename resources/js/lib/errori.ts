/**
 * Gli errori di validazione che arrivano da Laravel, nella forma che chiede FormKit.
 */

/**
 * Trasforma il messaggio di errore di un campo (`form.errors.email`) nell'elenco
 * di messaggi della prop `errors` di `<FormKit>`.
 *
 * @param messaggio - il messaggio del server, se c'è
 * @returns l'elenco dei messaggi da mostrare sotto il campo
 */
export function errori(messaggio: string | undefined): string[] {
    return messaggio ? [messaggio] : [];
}

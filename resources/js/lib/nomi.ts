/**
 * Unisce i nomi come si scrivono in una frase: «Marco, Laura e Giulia».
 *
 * @param nomi - i nomi, nell'ordine in cui si leggono
 * @returns la frase, vuota se non ci sono nomi
 */
export function elencoNomi(nomi: string[]): string {
    if (nomi.length < 2) {
        return nomi.join('');
    }

    return `${nomi.slice(0, -1).join(', ')} e ${nomi.at(-1)}`;
}

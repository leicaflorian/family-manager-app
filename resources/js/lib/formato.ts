/**
 * Come l'app scrive importi e date: `84,20 €` e `15/10/2026`.
 * Le pagine non li scrivono a mano: usano queste funzioni, di solito
 * attraverso il composable `useFormato`.
 */
import { format } from '@formkit/tempo';

const euro = new Intl.NumberFormat('it-IT', {
    style: 'currency',
    currency: 'EUR',
});

/**
 * Scrive un importo in euro all'italiana.
 *
 * @param importo - l'importo in euro, per esempio `1234.5`
 * @returns l'importo scritto, per esempio `1.234,50 €`
 */
export function formatEuro(importo: number): string {
    // it-IT non raggruppa le migliaia sotto 10.000: si forza il raggruppamento.
    // Gli spazi non separabili diventano spazi normali, così i testi si confrontano.
    return euro
        .format(importo)
        .replace(/^(-?\d)(\d{3}),/, '$1.$2,')
        .replace(/\s/g, ' ');
}

/**
 * Trasforma una data del server in un `Date` locale.
 *
 * Una data senza ora (`2026-10-15`) va letta come giorno del calendario: con
 * `new Date('2026-10-15')` sarebbe mezzanotte UTC, che in alcuni fusi è il
 * giorno prima. Con l'ora si prende solo la parte della data, che è quella
 * scritta dal server nel fuso di Roma.
 *
 * @param data - `aaaa-mm-gg`, `aaaa-mm-ggThh:mm:ss…` oppure un `Date`
 * @returns la data a mezzanotte, nell'ora locale
 */
export function daData(data: string | Date): Date {
    if (data instanceof Date) {
        return data;
    }

    const [anno, mese, giorno] = data.slice(0, 10).split('-').map(Number);

    return new Date(anno, mese - 1, giorno);
}

/**
 * Scrive una data come gg/mm/aaaa.
 *
 * @param data - una data del server o un `Date`
 * @returns la data scritta, per esempio `15/10/2026`
 */
export function formatData(data: string | Date): string {
    return format(daData(data), 'DD/MM/YYYY', 'it');
}

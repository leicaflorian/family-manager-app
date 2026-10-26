import { formatData, formatEuro } from '@/lib/formato';

/** Le funzioni di scrittura che `useFormato` dà alle pagine. */
export type UseFormato = {
    /** Un importo come `84,20 €`; vuoto se manca. */
    euro: (importo: number | null | undefined) => string;
    /** Una data come `15/10/2026`; vuota se manca. */
    data: (data: string | Date | null | undefined) => string;
};

/**
 * Importi e date come li scrive l'app, per i template delle pagine.
 * Un valore che manca (un documento senza data) diventa una stringa vuota,
 * così il template non deve controllarlo ogni volta.
 *
 * @returns le funzioni `euro` e `data`
 */
export function useFormato(): UseFormato {
    return {
        euro: (importo) =>
            importo === null || importo === undefined
                ? ''
                : formatEuro(importo),
        data: (data) =>
            data === null || data === undefined || data === ''
                ? ''
                : formatData(data),
    };
}

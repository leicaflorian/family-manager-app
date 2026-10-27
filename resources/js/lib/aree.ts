/**
 * Le aree della vita familiare e i loro colori, come sulla tela.
 *
 * Le classi sono scritte per intero perché Tailwind le trovi nel codice: una
 * classe composta al volo (`bg-${area}`) non finirebbe nel CSS.
 */
export type Area =
    | 'casa'
    | 'veicoli'
    | 'assicurazioni'
    | 'banche'
    | 'salute'
    | 'scuola'
    | 'lavoro'
    | 'abbonamenti'
    | 'pratiche'
    | 'trasversali';

/** Il colore pieno di ogni area, per pallini e icone. */
export const sfondoArea: Record<Area, string> = {
    casa: 'bg-casa',
    veicoli: 'bg-veicoli',
    assicurazioni: 'bg-assicurazioni',
    banche: 'bg-banche',
    salute: 'bg-salute',
    scuola: 'bg-scuola',
    lavoro: 'bg-lavoro',
    abbonamenti: 'bg-abbonamenti',
    pratiche: 'bg-pratiche',
    trasversali: 'bg-trasversali',
};

/** Il fondo tenue e il colore dell'icona di ogni area, per le tessere delle icone. */
export const tesseraArea: Record<Area, string> = {
    casa: 'bg-casa/15 text-casa',
    veicoli: 'bg-veicoli/15 text-veicoli',
    assicurazioni: 'bg-assicurazioni/15 text-assicurazioni',
    banche: 'bg-banche/15 text-banche',
    salute: 'bg-salute/15 text-salute',
    scuola: 'bg-scuola/15 text-scuola',
    lavoro: 'bg-lavoro/15 text-lavoro',
    abbonamenti: 'bg-abbonamenti/15 text-abbonamenti',
    pratiche: 'bg-pratiche/15 text-pratiche',
    trasversali: 'bg-trasversali/15 text-trasversali',
};

/**
 * L'area di un colore salvato nel database, se è una delle aree.
 *
 * @param colore - il valore di `color`, per esempio `casa`
 * @returns l'area, o undefined
 */
export function areaDi(colore: string | null): Area | undefined {
    return colore !== null && colore in sfondoArea
        ? (colore as Area)
        : undefined;
}

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

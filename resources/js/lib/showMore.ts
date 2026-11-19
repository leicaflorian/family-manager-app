import { transChoice } from 'laravel-vue-i18n';

/**
 * What the rows under a *Show more* are, for its text: in Italian the tasks,
 * the policies and the other things with a feminine name take «altre», the
 * documents and the things with a masculine name take «altri».
 */
export type ShowMoreKind = 'feminine' | 'masculine' | 'documents';

/**
 * The types of the links whose name is feminine in Italian (*Case*, *Utenze*,
 * *Manutenzioni*, *Polizze*, *Pratiche fiscali*, *Scuola e attività*): the
 * others (*Veicoli*, *Conti e carte*, *Abbonamenti*…) are masculine.
 */
const FEMININE_LINKS: readonly string[] = [
    'home',
    'utility',
    'maintenance_record',
    'insurance_policy',
    'filing',
    'enrollment',
];

/**
 * The text of the *Show more* button under a list shown a few rows at a time:
 * «Show 5 more · 19 left». It never promises more rows than are left.
 *
 * @param left - how many rows are still hidden
 * @param step - how many rows a tap shows
 * @param kind - what the rows are, for the Italian «altre» or «altri»
 * @returns the text of the button
 */
export function showMoreLabel(
    left: number,
    step: number,
    kind: ShowMoreKind = 'feminine',
): string {
    const shown = String(Math.min(step, left));

    return translatedLabel(kind, left, shown);
}

/**
 * The translated text for each kind: the keys are written out so the check of
 * the translations finds them.
 *
 * @param kind - what the rows are
 * @param left - how many rows are still hidden
 * @param shown - how many rows a tap shows
 * @returns the text of the button
 */
function translatedLabel(
    kind: ShowMoreKind,
    left: number,
    shown: string,
): string {
    switch (kind) {
        case 'documents':
            return transChoice(
                '{1} Show :shown more documents · 1 left|[2,*] Show :shown more documents · :count left',
                left,
                { shown },
            );
        case 'masculine':
            return transChoice(
                '{1} Show :shown other · 1 left|[2,*] Show :shown others · :count left',
                left,
                { shown },
            );
        default:
            return transChoice(
                '{1} Show :shown more · 1 left|[2,*] Show :shown more · :count left',
                left,
                { shown },
            );
    }
}

/**
 * Whether the records of a type of the links take «altre» or «altri».
 *
 * @param type - the type of the records (`insurance_policy`, `vehicle`…)
 * @returns the kind for `showMoreLabel`
 */
export function linkKind(type: string): ShowMoreKind {
    return FEMININE_LINKS.includes(type) ? 'feminine' : 'masculine';
}

import { transChoice } from 'laravel-vue-i18n';

/**
 * The text of the *Show more* button under a list shown a few rows at a time:
 * «Show 5 more · 19 left». It never promises more rows than are left.
 *
 * @param left - how many rows are still hidden
 * @param step - how many rows a tap shows
 * @param documents - the rows are documents: in Italian they take «altri», the
 *   tasks and the cards «altre»
 * @returns the text of the button
 */
export function showMoreLabel(
    left: number,
    step: number,
    documents = false,
): string {
    const shown = String(Math.min(step, left));

    return documents
        ? transChoice(
              '{1} Show :shown more documents · 1 left|[2,*] Show :shown more documents · :count left',
              left,
              { shown },
          )
        : transChoice(
              '{1} Show :shown more · 1 left|[2,*] Show :shown more · :count left',
              left,
              { shown },
          );
}

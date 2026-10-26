/**
 * Configurazione di FormKit: testi in italiano e lo stile dei campi della tela
 * (pagina *Componenti*). È il «FmCampo» del piano: non un componente, ma le
 * classi che ogni `<FormKit>` riceve.
 */
import { it } from '@formkit/i18n';
import { generateClasses } from '@formkit/themes';
import { defaultConfig } from '@formkit/vue';

export default defaultConfig({
    locales: { it },
    locale: 'it',
    // Niente icone scaricate da internet: il menu a tendina usa la freccia del telefono
    iconLoader: () => undefined,
    iconLoaderUrl: () => '',
    config: {
        classes: generateClasses({
            global: {
                outer: 'mb-3.5 formkit-disabled:opacity-50',
                label: 'block mb-1.5 text-sm font-semibold text-secondario',
                inner: 'flex items-center h-12 rounded-xl border border-bordo bg-white px-3.5 focus-within:border-azione focus-within:ring-2 focus-within:ring-azione/20',
                input: 'w-full min-w-0 bg-transparent text-base text-testo outline-none placeholder:text-secondario',
                help: 'mt-1 text-xs text-secondario',
                messages: 'mt-1 list-none p-0',
                message: 'text-sm font-medium text-scaduto',
            },
            // Le classi di un tipo si aggiungono a quelle di global, non le sostituiscono:
            // $reset le toglie (bg-transparent di global copriva bg-azione).
            submit: {
                outer: 'mt-2 mb-0',
                inner: '$reset',
                input: '$reset h-14 w-full rounded-2xl bg-azione text-[17px] font-bold text-white active:bg-azione-scuro disabled:opacity-50',
            },
            textarea: {
                inner: '$reset flex rounded-xl border border-bordo bg-white px-3.5 py-3 focus-within:border-azione focus-within:ring-2 focus-within:ring-azione/20',
                input: 'min-h-24',
            },
            'family:box': {
                inner: '$reset flex',
                wrapper:
                    'flex items-center gap-3 min-h-12 rounded-xl border border-bordo bg-white px-3.5 has-[:checked]:border-2 has-[:checked]:border-azione has-[:checked]:bg-[#F3F8FF]',
                input: '$reset size-5 shrink-0 accent-azione',
                label: 'mb-0 text-base font-normal text-testo',
                options: 'flex flex-col gap-2 list-none p-0 m-0',
                legend: 'mb-1.5 text-sm font-semibold text-secondario',
                fieldset: 'border-0 p-0 m-0',
            },
        }),
    },
});

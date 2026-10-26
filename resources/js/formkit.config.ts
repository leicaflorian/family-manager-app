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
                outer: 'mb-3.5 formkit-disattivato:opacity-50',
                label: 'block mb-1.5 text-[15px] font-semibold text-testo',
                inner: 'flex items-center h-13 rounded-[14px] border-[1.5px] border-bordo bg-white px-3.5 focus-within:border-azione focus-within:ring-2 focus-within:ring-azione/20 formkit-errori:border-scaduto',
                input: 'w-full min-w-0 bg-transparent text-base text-testo outline-none placeholder:text-secondario',
                help: 'mt-1 text-[13px] text-secondario',
                messages: 'mt-1 list-none p-0',
                message: 'text-sm font-semibold text-scaduto',
            },
            // Le classi di un tipo si aggiungono a quelle di global, non le sostituiscono:
            // $reset le toglie (bg-transparent di global copriva bg-azione).
            submit: {
                outer: 'mt-2 mb-0',
                inner: '$reset',
                input: '$reset h-14 w-full rounded-2xl bg-azione text-[17px] font-bold text-white active:bg-azione-scuro disabled:bg-bordo disabled:text-secondario',
            },
            textarea: {
                inner: '$reset flex rounded-[14px] border-[1.5px] border-bordo bg-white px-3.5 py-3 focus-within:border-azione focus-within:ring-2 focus-within:ring-azione/20 formkit-errori:border-scaduto',
                input: 'min-h-24',
            },
            'family:box': {
                inner: '$reset flex',
                wrapper:
                    'flex items-center gap-3 min-h-13 rounded-[14px] border-[1.5px] border-bordo bg-white px-3.5 has-[:checked]:border-2 has-[:checked]:border-azione has-[:checked]:bg-azione-tenue',
                input: '$reset size-[22px] shrink-0 accent-azione',
                label: 'mb-0 text-base font-normal text-testo',
                options: 'flex flex-col gap-2 list-none p-0 m-0',
                legend: 'mb-1.5 text-[15px] font-semibold text-testo',
                fieldset: 'border-0 p-0 m-0',
            },
            // Le caselle sono semplici, come «Resta collegato»; le scelte (radio) restano riquadri.
            checkbox: {
                wrapper: '$reset flex items-start gap-2.5 min-h-11 py-2.5',
                label: '$reset text-[15px] leading-snug text-testo',
            },
        }),
    },
});

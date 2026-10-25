import { it } from '@formkit/i18n'
import { generateClasses } from '@formkit/themes'
import { defaultConfig } from '@formkit/vue'

// Lo stile dei campi segue le schermate approvate su Claude Design
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
      // $reset toglie le classi di global, che altrimenti vincono (bg-transparent copriva bg-azione)
      submit: {
        outer: 'mt-2 mb-0',
        inner: '$reset',
        input: '$reset h-14 w-full rounded-2xl bg-azione text-[17px] font-bold text-white active:bg-azione-scuro',
      },
      'family:box': {
        inner: '$reset flex',
        wrapper: 'flex items-center gap-3 min-h-12 rounded-xl border border-bordo bg-white px-3.5 has-[:checked]:border-2 has-[:checked]:border-azione has-[:checked]:bg-[#F3F8FF]',
        input: '$reset size-5 shrink-0 accent-azione',
        label: 'mb-0 text-base font-normal text-testo',
        options: 'flex flex-col gap-2 list-none p-0 m-0',
        legend: 'mb-1.5 text-sm font-semibold text-secondario',
        fieldset: 'border-0 p-0 m-0',
      },
    }),
  },
})

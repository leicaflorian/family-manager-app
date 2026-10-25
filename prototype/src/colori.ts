import type { Area } from './data/tipi'

// I token sono in style.css: il chiaro per pallini e icone, lo scuro per il testo
export const coloreArea = (area: Area) => `var(--color-${area})`
export const coloreAreaScuro = (area: Area) => `var(--color-${area}-scuro, var(--color-${area}))`

export const maiuscola = (testo: string) => testo.charAt(0).toUpperCase() + testo.slice(1)

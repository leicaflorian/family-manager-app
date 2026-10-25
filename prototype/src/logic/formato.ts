import { addDay, dayStart, diffDays, format, monthEnd } from '@formkit/tempo'

const euro = new Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR' })

/** 1234.5 → "1.234,50 €" (con spazi normali, non separabili) */
export function formatEuro(importo: number): string {
  // it-IT non raggruppa le migliaia sotto 10.000: si forza il raggruppamento
  return euro
    .formatToParts(importo)
    .map((p) => p.value)
    .join('')
    .replace(/^(\d)(\d{3}),/, '$1.$2,')
    .replace(/\s/g, ' ')
}

/** gg/mm/aaaa */
export function formatData(data: Date): string {
  return format(data, 'DD/MM/YYYY', 'it')
}

/** Giorni di calendario da `da` ad `a`, ignorando l'ora */
export function giorniTra(da: Date, a: Date): number {
  return diffDays(dayStart(a), dayStart(da))
}

/** La scadenza detta a parole rispetto a oggi */
export function quando(scadenza: Date, oggi: Date): string {
  const giorni = giorniTra(oggi, scadenza)
  if (giorni === 0) return 'oggi'
  if (giorni === 1) return 'domani'
  if (giorni > 1) return `tra ${giorni} giorni`
  return giorni === -1 ? 'scaduta da 1 giorno' : `scaduta da ${-giorni} giorni`
}

export function spostaGiorni(data: Date, giorni: number): Date {
  return addDay(dayStart(data), giorni)
}

/** L'ultimo giorno del mese, a mezzanotte */
export function fineMese(data: Date): Date {
  return dayStart(monthEnd(data))
}

/** Il valore di un `<input type="date">`: aaaa-mm-gg */
export function perCampoData(data: Date): string {
  return format(data, 'YYYY-MM-DD', 'it')
}

export function daCampoData(valore: string): Date {
  const [anno, mese, giorno] = valore.split('-').map(Number)
  return new Date(anno, mese - 1, giorno)
}

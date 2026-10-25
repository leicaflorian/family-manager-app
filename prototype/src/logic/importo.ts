/**
 * L'importo scritto dalla persona: accetta la virgola della tastiera italiana,
 * il punto e i punti delle migliaia. null se non è un importo valido.
 */
export function leggiImporto(testo: string): number | null {
  let t = testo.replace(/[€\s]/g, '')
  if (t.includes(',')) t = t.replace(/\./g, '').replace(',', '.')
  if (!/^\d+(\.\d{1,2})?$/.test(t)) return null
  return Number(t)
}

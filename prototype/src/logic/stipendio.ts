import { dayStart } from '@formkit/tempo'

/** Il giorno di paga in un mese: nei mesi più corti, l'ultimo giorno */
function giornoDiPaga(anno: number, mese: number, giorno: number): Date {
  const ultimo = new Date(anno, mese + 1, 0).getDate()
  return new Date(anno, mese, Math.min(giorno, ultimo))
}

/** Quando arriva il prossimo stipendio: oggi, se arriva oggi */
export function prossimoStipendio(giorno: number, oggi: Date): Date {
  const o = dayStart(oggi)
  const questoMese = giornoDiPaga(o.getFullYear(), o.getMonth(), giorno)
  return questoMese >= o ? questoMese : giornoDiPaga(o.getFullYear(), o.getMonth() + 1, giorno)
}

/** Gli ultimi `quanti` stipendi già arrivati, dal più recente */
export function stipendiPassati(giorno: number, oggi: Date, quanti: number): Date[] {
  const o = dayStart(oggi)
  const parti = giornoDiPaga(o.getFullYear(), o.getMonth(), giorno) <= o ? 0 : 1
  return Array.from({ length: quanti }, (_, i) => giornoDiPaga(o.getFullYear(), o.getMonth() - parti - i, giorno))
}

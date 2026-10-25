import { describe, expect, it } from 'vitest'
import { creaFamiglia } from '../data/famiglia'
import { cercaDocumenti } from './ricerca'

const f = creaFamiglia(new Date(2026, 9, 25))

describe('cercaDocumenti', () => {
  it('trova i documenti di una persona', () => {
    expect(cercaDocumenti(f, 'giulia').map((d) => d.titolo).sort()).toEqual([
      "Carta d'identità", 'Certificato medico sportivo', 'Iscrizione danza 2026/27',
    ])
  })
  it('ignora maiuscole e accenti', () => {
    expect(cercaDocumenti(f, 'IDENTITA').map((d) => d.titolo)).toEqual(["Carta d'identità"])
  })
  it('cerca anche nel nome della scheda', () => {
    expect(cercaDocumenti(f, 'panda').map((d) => d.titolo).sort()).toEqual(['Bollo 2026', 'Polizza RC auto 2026', 'Ultima revisione'])
  })
  it('senza testo restituisce tutto, dal più recente', () => {
    const tutti = cercaDocumenti(f, '  ')
    expect(tutti.length).toBe(f.documenti.length)
    expect(tutti[0].data >= tutti[tutti.length - 1].data).toBe(true)
  })
})

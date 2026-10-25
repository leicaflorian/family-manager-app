import { describe, expect, it } from 'vitest'
import { creaFamiglia } from '../data/famiglia'
import { daPagareEntro, prossime, totale } from './scadenze'
import { fineMese } from './formato'

const oggi = new Date(2026, 9, 31)
const { scadenze } = creaFamiglia(oggi)

describe('daPagareEntro', () => {
  it('somma le bollette del mese e quella scaduta', () => {
    const entro = daPagareEntro(scadenze, oggi, fineMese(oggi))
    expect(entro.map((s) => s.titolo)).toEqual(['Bolletta luce', 'Bolletta gas', 'Bolletta acqua'])
    expect(totale(entro)).toBeCloseTo(71.3 + 84.2 + 37.9)
  })

  it('lascia fuori le scadenze senza importo', () => {
    const tutte = daPagareEntro(scadenze, oggi, new Date(2027, 11, 31))
    expect(tutte.some((s) => s.titolo === 'Revisione Panda')).toBe(false)
  })
})

describe('prossime', () => {
  it('prende i prossimi 30 giorni in ordine di data, scadute comprese', () => {
    const titoli = prossime(scadenze, oggi, 30).map((s) => s.titolo)
    expect(titoli[0]).toBe('Bolletta luce')
    expect(titoli).toContain('Rata RC auto')
    expect(titoli).not.toContain('Retta danza')
    expect(titoli).not.toContain('Revisione Panda')
  })
})

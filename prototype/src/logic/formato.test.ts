import { describe, expect, it } from 'vitest'
import { fineMese, formatData, formatEuro, giorniTra, quando, spostaGiorni } from './formato'

describe('formatEuro', () => {
  it('scrive gli importi all\'italiana', () => {
    expect(formatEuro(84.2)).toBe('84,20 €')
    expect(formatEuro(1234.5)).toBe('1.234,50 €')
    expect(formatEuro(0)).toBe('0,00 €')
  })
})

describe('formatData', () => {
  it('scrive gg/mm/aaaa', () => {
    expect(formatData(new Date(2026, 9, 5))).toBe('05/10/2026')
  })
})

describe('giorniTra', () => {
  it('conta i giorni di calendario, non le ore', () => {
    expect(giorniTra(new Date(2026, 9, 25, 23, 30), new Date(2026, 9, 26, 0, 10))).toBe(1)
  })
})

describe('quando', () => {
  const oggi = new Date(2026, 9, 25, 23, 30)
  it('dice oggi, domani, tra N giorni', () => {
    expect(quando(new Date(2026, 9, 25), oggi)).toBe('oggi')
    expect(quando(new Date(2026, 9, 26), oggi)).toBe('domani')
    expect(quando(new Date(2026, 9, 28), oggi)).toBe('tra 3 giorni')
  })
  it('dice da quanto è scaduta', () => {
    expect(quando(new Date(2026, 9, 24), oggi)).toBe('scaduta da 1 giorno')
    expect(quando(new Date(2026, 9, 20), oggi)).toBe('scaduta da 5 giorni')
  })
})

describe('spostaGiorni e fineMese', () => {
  it('attraversano il mese', () => {
    expect(spostaGiorni(new Date(2026, 9, 31), 1)).toEqual(new Date(2026, 10, 1))
    expect(fineMese(new Date(2026, 1, 10))).toEqual(new Date(2026, 1, 28))
  })
})

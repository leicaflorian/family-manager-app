import { describe, expect, it } from 'vitest'
import { fineMese } from '../logic/formato'
import { creaFamiglia } from './famiglia'

const giorni = [new Date(2026, 10, 15), new Date(2026, 9, 31), new Date(2026, 9, 25)]

describe.each(giorni)('creaFamiglia(%s)', (oggi) => {
  const f = creaFamiglia(oggi)

  it('mette almeno 2 pagamenti tra oggi e fine mese', () => {
    const fine = fineMese(oggi)
    const nelMese = f.scadenze.filter((s) => !s.pagata && s.importo !== null && s.data >= oggi && s.data <= fine)
    expect(nelMese.length).toBeGreaterThanOrEqual(2)
  })

  it('ha id unici', () => {
    const ids = [...f.persone, ...f.schede, ...f.scadenze, ...f.documenti, ...f.posta].map((x) => x.id)
    expect(new Set(ids).size).toBe(ids.length)
  })

  it('collega solo cose che esistono', () => {
    const schede = new Set(f.schede.map((s) => s.id))
    const persone = new Set(f.persone.map((p) => p.id))
    const documenti = new Set(f.documenti.map((d) => d.id))
    for (const s of f.scadenze) expect(schede.has(s.schedaId)).toBe(true)
    for (const d of f.documenti) {
      if (d.schedaId) expect(schede.has(d.schedaId)).toBe(true)
      if (d.personaId) expect(persone.has(d.personaId)).toBe(true)
    }
    for (const p of f.posta) expect(documenti.has(p.documentoId)).toBe(true)
  })

  it('ha una sola scadenza già passata e non pagata: la bolletta della luce', () => {
    const scadute = f.scadenze.filter((s) => !s.pagata && s.data < oggi)
    expect(scadute.map((s) => s.titolo)).toEqual(['Bolletta luce'])
  })

  it('parte dal primo avvio', () => {
    expect(f.primoAvvioFatto).toBe(false)
  })
})

describe('le schede del lavoro', () => {
  const f = creaFamiglia(new Date(2026, 9, 25))
  const lavoro = f.schede.filter((s) => s.area === 'lavoro')

  it('hanno lo stipendio', () => {
    expect(lavoro.length).toBe(2)
    for (const s of lavoro) {
      expect(s.stipendio?.netto).toBeGreaterThan(0)
      expect(s.stipendio?.giorno).toBeGreaterThanOrEqual(1)
    }
  })

  it('hanno le ultime tre buste paga, del mese in cui sono arrivate', () => {
    const buste = (id: string) => f.documenti.filter((d) => d.schedaId === id && d.titolo.startsWith('Busta paga')).map((d) => d.titolo)
    // Marco lo riceve il 27, Laura il 23: il 25 ottobre quella di ottobre è arrivata solo a Laura
    expect(buste('lavoro-marco')).toEqual(['Busta paga di settembre', 'Busta paga di agosto', 'Busta paga di luglio'])
    expect(buste('lavoro-laura')).toEqual(['Busta paga di ottobre', 'Busta paga di settembre', 'Busta paga di agosto'])
  })
})

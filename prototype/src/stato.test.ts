import { beforeEach, describe, expect, it } from 'vitest'
import { fineMese, spostaGiorni } from './logic/formato'
import { daPagareEntro, prossime, totale } from './logic/scadenze'
import { aggiungiDocumento, completaPrimoAvvio, oggi, ricomincia, segnaPagata, useFamiglia } from './stato'

const daPagareNelMese = () => totale(daPagareEntro(useFamiglia().scadenze, oggi(), fineMese(oggi())))
const pagamento = () => ({ data: oggi(), conto: 'Conto BPER', ricevuta: false })

describe('stato', () => {
  beforeEach(() => ricomincia())

  it('oggi è a mezzanotte', () => {
    expect(oggi().getHours()).toBe(0)
    expect(oggi().getMinutes()).toBe(0)
  })

  it('useFamiglia restituisce sempre lo stesso oggetto', () => {
    expect(useFamiglia()).toBe(useFamiglia())
  })

  it('segna pagata la bolletta del gas una volta sola', () => {
    const prima = daPagareNelMese()
    expect(segnaPagata('s-gas', pagamento())).toBe(true)
    expect(daPagareNelMese()).toBeCloseTo(prima - 84.2)
    expect(segnaPagata('s-gas', pagamento())).toBe(false)
    expect(daPagareNelMese()).toBeCloseTo(prima - 84.2)
  })

  it('non paga una scadenza che non esiste', () => {
    expect(segnaPagata('nessuna', pagamento())).toBe(false)
  })

  it('aggiunge la bolletta caricata alle prossime scadenze', () => {
    aggiungiDocumento({ titolo: 'Bolletta luce di ottobre', schedaId: 'luce', categoria: 'Bollette e fatture', ente: 'Edison', importo: 62.4, scadenza: spostaGiorni(oggi(), 11) })
    const f = useFamiglia()
    expect(prossime(f.scadenze, oggi(), 30).some((s) => s.titolo === 'Bolletta luce di ottobre' && s.importo === 62.4)).toBe(true)
    expect(f.documenti.some((d) => d.titolo === 'Bolletta luce di ottobre' && d.schedaId === 'luce' && d.categoria === 'Bollette e fatture' && d.ente === 'Edison')).toBe(true)
  })

  it('ricomincia riporta tutto come all\'inizio', () => {
    segnaPagata('s-gas', pagamento())
    completaPrimoAvvio()
    aggiungiDocumento({ titolo: 'X', schedaId: 'luce', categoria: 'Bollette e fatture', ente: null, importo: 1, scadenza: oggi() })
    ricomincia()
    const f = useFamiglia()
    expect(f.primoAvvioFatto).toBe(false)
    expect(f.scadenze.find((s) => s.id === 's-gas')?.pagata).toBeNull()
    expect(f.documenti.some((d) => d.titolo === 'X')).toBe(false)
  })
})

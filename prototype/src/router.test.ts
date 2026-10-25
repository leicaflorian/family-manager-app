import { describe, expect, it } from 'vitest'
import { router, scorrimento } from './router'
import { completaPrimoAvvio, ricomincia } from './stato'

const percorso = (path: string, query: Record<string, string> = {}) => ({ path, query }) as never

describe('scorrimento', () => {
  it('porta al documento evidenziato', () => {
    expect(scorrimento(percorso('/documenti', { evidenzia: 'd-polizza' }), percorso('/scheda/panda'), null)).toEqual({
      el: '#doc-d-polizza',
      block: 'center',
    })
  })
  it('tornando indietro ritrova la posizione', () => {
    expect(scorrimento(percorso('/'), percorso('/scadenza/s-gas'), { left: 0, top: 420 })).toEqual({ left: 0, top: 420 })
  })
  it('altrimenti parte dall\'alto', () => {
    expect(scorrimento(percorso('/schede'), percorso('/'), null)).toEqual({ top: 0 })
  })
})

describe('benvenuto', () => {
  it('dopo il primo avvio non si riapre', async () => {
    ricomincia()
    completaPrimoAvvio()
    await router.push('/benvenuto')
    expect(router.currentRoute.value.path).toBe('/')
  })
})

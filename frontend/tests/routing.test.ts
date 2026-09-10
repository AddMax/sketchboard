import { describe, expect, it } from 'vitest'
import { drawPath, parseRoute } from '../src/ui/routing/routes'

describe('маршруты', () => {
  it('корень — доска заметок', () => {
    expect(parseRoute('/')).toEqual({ kind: 'board' })
  })

  it('распознаёт страницу рисования по заметке', () => {
    expect(parseRoute('/notes/abc-123/draw')).toEqual({ kind: 'draw', noteId: 'abc-123' })
    expect(parseRoute('/notes/abc-123/draw/')).toEqual({ kind: 'draw', noteId: 'abc-123' })
  })

  it('неизвестный путь ведёт на доску', () => {
    expect(parseRoute('/notes/abc-123')).toEqual({ kind: 'board' })
    expect(parseRoute('/что-то')).toEqual({ kind: 'board' })
  })

  it('собранный путь разбирается обратно в тот же идентификатор', () => {
    const id = 'id с пробелом/и слэшем'

    expect(parseRoute(drawPath(id))).toEqual({ kind: 'draw', noteId: id })
  })
})

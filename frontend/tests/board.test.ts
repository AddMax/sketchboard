import { describe, expect, it } from 'vitest'
import { applyRealtimeEvent, emptyBoard, replaceBoard } from '../src/domain/board/Board'
import type { Note } from '../src/domain/note/Note'

const note = (id: string, overrides: Partial<Note> = {}): Note => ({
  id,
  text: `заметка ${id}`,
  x: 0,
  y: 0,
  color: '#ffd166',
  author: 'автор',
  createdAt: '2026-09-01T10:00:00+00:00',
  ...overrides,
})

describe('доска', () => {
  it('добавляет пришедшую заметку', () => {
    const board = applyRealtimeEvent(emptyBoard, {
      event: 'note.created',
      payload: note('a'),
      at: '2026-09-01T10:00:00+00:00',
    })

    expect(board.map((n) => n.id)).toEqual(['a'])
  })

  it('не дублирует заметку при повторном событии', () => {
    const created = { event: 'note.created', payload: note('a'), at: '' } as const

    const board = applyRealtimeEvent(applyRealtimeEvent(emptyBoard, created), created)

    expect(board).toHaveLength(1)
  })

  it('заменяет перемещённую заметку на месте', () => {
    const start = replaceBoard([note('a'), note('b')])

    const board = applyRealtimeEvent(start, {
      event: 'note.moved',
      payload: note('a', { x: 300, y: 150 }),
      at: '',
    })

    expect(board.find((n) => n.id === 'a')).toMatchObject({ x: 300, y: 150 })
    expect(board).toHaveLength(2)
  })

  it('удаление отсутствующей заметки ничего не ломает', () => {
    const start = replaceBoard([note('a')])

    const board = applyRealtimeEvent(start, { event: 'note.deleted', payload: { id: 'нет' }, at: '' })

    expect(board.map((n) => n.id)).toEqual(['a'])
  })

  it('присутствие и курсоры доску не меняют', () => {
    const start = replaceBoard([note('a')])

    expect(applyRealtimeEvent(start, { event: 'presence', payload: { clients: 3 }, at: '' })).toBe(
      start,
    )
    expect(
      applyRealtimeEvent(start, {
        event: 'cursor',
        payload: { author: 'кто-то', x: 1, y: 2 },
        at: '',
      }),
    ).toBe(start)
  })

  it('держит порядок «свежие первыми»', () => {
    const board = replaceBoard([
      note('старая', { createdAt: '2026-09-01T09:00:00+00:00' }),
      note('новая', { createdAt: '2026-09-01T11:00:00+00:00' }),
    ])

    expect(board.map((n) => n.id)).toEqual(['новая', 'старая'])
  })
})

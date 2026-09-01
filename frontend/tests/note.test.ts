import { describe, expect, it } from 'vitest'
import { BOARD_MAX, NOTE_TEXT_MAX_LENGTH, draftNote } from '../src/domain/note/Note'
import { DomainError } from '../src/domain/note/errors'
import { parseRealtimeEvent } from '../src/domain/realtime/RealtimeEvent'

const valid = { text: 'привет', x: 10, y: 20, color: '#06D6A0', author: 'я' }

describe('заготовка заметки', () => {
  it('обрезает текст и приводит цвет к нижнему регистру', () => {
    expect(draftNote({ ...valid, text: '  привет  ' })).toMatchObject({
      text: 'привет',
      color: '#06d6a0',
    })
  })

  it('не принимает пустой текст', () => {
    expect(() => draftNote({ ...valid, text: '   ' })).toThrow(DomainError)
  })

  it('не принимает слишком длинный текст', () => {
    expect(() => draftNote({ ...valid, text: 'я'.repeat(NOTE_TEXT_MAX_LENGTH + 1) })).toThrow(
      DomainError,
    )
  })

  it('не принимает цвет вне формата #rrggbb', () => {
    expect(() => draftNote({ ...valid, color: 'красный' })).toThrow(DomainError)
  })

  it('прижимает координаты к границам доски', () => {
    expect(draftNote({ ...valid, x: -50, y: BOARD_MAX + 500 })).toMatchObject({
      x: 0,
      y: BOARD_MAX,
    })
  })
})

describe('разбор события канала', () => {
  it('пропускает известное событие', () => {
    expect(parseRealtimeEvent('{"event":"presence","payload":{"clients":2},"at":""}')).toMatchObject({
      event: 'presence',
    })
  })

  it('отбрасывает мусор и неизвестные события', () => {
    expect(parseRealtimeEvent('не json')).toBeNull()
    expect(parseRealtimeEvent('{"event":"что-то своё"}')).toBeNull()
    expect(parseRealtimeEvent('[]')).toBeNull()
  })
})

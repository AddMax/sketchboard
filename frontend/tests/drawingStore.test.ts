import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { DrawingStore } from '../src/application/drawing/DrawingStore'
import type { DrawingRepository } from '../src/application/ports/DrawingRepository'
import type { DrawingLine } from '../src/domain/drawing/Drawing'

const line = (id: string): DrawingLine => ({
  id,
  points: [{ x: 0, y: 0 }],
  color: '#000000',
  width: 3,
})

/** Хранилище в памяти, которому можно велеть падать на сохранении. */
class InMemoryDrawings implements DrawingRepository {
  readonly saved: DrawingLine[][] = []
  failing = false

  constructor(private readonly stored: DrawingLine[] = []) {}

  async load(): Promise<DrawingLine[]> {
    return [...this.stored]
  }

  async save(_noteId: string, lines: DrawingLine[]): Promise<void> {
    if (this.failing) throw new Error('сеть')

    this.saved.push(lines)
  }
}

const DELAY = 1500

describe('стор рисунка', () => {
  beforeEach(() => {
    vi.useFakeTimers()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('загружает штрихи и отмечает готовность', async () => {
    const store = new DrawingStore('n1', new InMemoryDrawings([line('a')]), DELAY)

    expect(store.loaded).toBe(false)

    await store.load()

    expect(store.loaded).toBe(true)
    expect(store.lines.map((l) => l.id)).toEqual(['a'])
  })

  it('серию быстрых штрихов сохраняет одним запросом с последним состоянием', async () => {
    const drawings = new InMemoryDrawings()
    const store = new DrawingStore('n1', drawings, DELAY)

    store.replaceLines([line('a')])
    await vi.advanceTimersByTimeAsync(DELAY / 2)
    store.replaceLines([line('a'), line('b')])

    expect(store.saveStatus).toBe('saving')
    expect(drawings.saved).toHaveLength(0)

    await vi.advanceTimersByTimeAsync(DELAY)

    expect(drawings.saved).toHaveLength(1)
    expect(drawings.saved[0].map((l) => l.id)).toEqual(['a', 'b'])
    expect(store.saveStatus).toBe('saved')
  })

  it('ошибка сохранения видна в статусе, а повтор отправляет снова', async () => {
    const drawings = new InMemoryDrawings()
    drawings.failing = true
    const store = new DrawingStore('n1', drawings, DELAY)

    store.replaceLines([line('a')])
    await vi.advanceTimersByTimeAsync(DELAY)

    expect(store.saveStatus).toBe('error')

    drawings.failing = false
    store.retrySave()
    await vi.advanceTimersByTimeAsync(0)

    expect(drawings.saved).toHaveLength(1)
    expect(store.saveStatus).toBe('saved')
  })

  it('уход со страницы отправляет отложенное сохранение сразу', async () => {
    const drawings = new InMemoryDrawings()
    const store = new DrawingStore('n1', drawings, DELAY)

    store.replaceLines([line('a')])
    store.dispose()
    await vi.advanceTimersByTimeAsync(0)

    expect(drawings.saved).toHaveLength(1)
  })

  it('повтор без ошибки ничего не делает', async () => {
    const drawings = new InMemoryDrawings()
    const store = new DrawingStore('n1', drawings, DELAY)

    store.retrySave()
    await vi.advanceTimersByTimeAsync(0)

    expect(drawings.saved).toHaveLength(0)
    expect(store.saveStatus).toBe('saved')
  })
})

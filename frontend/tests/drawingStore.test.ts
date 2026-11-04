import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { DrawingStore } from '../src/application/drawing/DrawingStore'
import type { DrawingRepository } from '../src/application/ports/DrawingRepository'
import type { FileRepository } from '../src/application/ports/FileRepository'
import type { DrawingElement } from '../src/domain/drawing/Drawing'
import type { FileDraft, StoredFile } from '../src/domain/file/StoredFile'

const line = (id: string): DrawingElement => ({
  type: 'freedraw',
  id,
  points: [[0, 0]],
  strokeColor: '#000000',
  strokeWidth: 1,
})

/** Хранилище в памяти, которому можно велеть падать на сохранении. */
class InMemoryDrawings implements DrawingRepository {
  readonly saved: DrawingElement[][] = []
  failing = false

  constructor(private readonly stored: DrawingElement[] = []) {}

  async load(): Promise<DrawingElement[]> {
    return [...this.stored]
  }

  async save(_noteId: string, elements: DrawingElement[]): Promise<void> {
    if (this.failing) throw new Error('сеть')

    this.saved.push(elements)
  }
}

const image = (id: string, fileId: string | null): DrawingElement => ({ type: 'image', id, fileId })

/** Хранилище файлов в памяти: описания по id и загрузки, которые можно завалить. */
class InMemoryFiles implements FileRepository {
  readonly uploaded: FileDraft[] = []
  failing = false
  private sequence = 0

  constructor(private readonly known: Record<string, StoredFile> = {}) {}

  async upload(_data: Blob, draft: FileDraft): Promise<StoredFile> {
    if (this.failing) throw new Error('Хранилище файлов недоступно')

    this.uploaded.push(draft)

    return stored(`f${++this.sequence}`, { name: draft.name, mimeType: draft.mimeType, visibility: draft.visibility })
  }

  async get(id: string): Promise<StoredFile> {
    const file = this.known[id]

    if (file === undefined) throw new Error(`Файл ${id} не найден`)

    return file
  }

  async remove(): Promise<void> {}

  async download(id: string): Promise<Blob> {
    const file = await this.get(id)

    return new Blob([file.name], { type: file.mimeType })
  }

  contentUrl(id: string, requester: string): string {
    return `/api/files/${id}/content?requester=${requester}`
  }
}

const stored = (id: string, overrides: Partial<StoredFile> = {}): StoredFile => ({
  id,
  name: 'img.png',
  mimeType: 'image/png',
  size: 3,
  visibility: 'public',
  owner: 'Аня',
  url: `/files/2026/11/${id}.png`,
  uploadedAt: '2026-11-04T12:00:00+00:00',
  ...overrides,
})

const DELAY = 1500

const makeStore = (drawings: DrawingRepository, files: FileRepository = new InMemoryFiles()) =>
  new DrawingStore('n1', drawings, files, 'Аня', DELAY)

describe('стор рисунка', () => {
  beforeEach(() => {
    vi.useFakeTimers()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('загружает элементы и отмечает готовность', async () => {
    const store = makeStore(new InMemoryDrawings([line('a')]))

    expect(store.loaded).toBe(false)

    await store.load()

    expect(store.loaded).toBe(true)
    expect(store.elements.map((e) => e.id)).toEqual(['a'])
  })

  it('серию быстрых штрихов сохраняет одним запросом с последним состоянием', async () => {
    const drawings = new InMemoryDrawings()
    const store = makeStore(drawings)

    store.replaceElements([line('a')])
    await vi.advanceTimersByTimeAsync(DELAY / 2)
    store.replaceElements([line('a'), line('b')])

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
    const store = makeStore(drawings)

    store.replaceElements([line('a')])
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
    const store = makeStore(drawings)

    store.replaceElements([line('a')])
    store.dispose()
    await vi.advanceTimersByTimeAsync(0)

    expect(drawings.saved).toHaveLength(1)
  })

  it('повтор без ошибки ничего не делает', async () => {
    const drawings = new InMemoryDrawings()
    const store = makeStore(drawings)

    store.retrySave()
    await vi.advanceTimersByTimeAsync(0)

    expect(drawings.saved).toHaveLength(0)
    expect(store.saveStatus).toBe('saved')
  })

  it('при загрузке забирает содержимое картинок сцены, пропуская недоступные', async () => {
    const files = new InMemoryFiles({
      f1: stored('f1', { name: 'png' }),
      f2: stored('f2', { name: 'svg', mimeType: 'image/svg+xml' }),
    })
    const store = makeStore(new InMemoryDrawings([image('i1', 'f1'), image('i2', 'f1'), image('i3', 'f2'), image('i4', 'удалён'), image('i5', null), line('a')]), files)

    await store.load()

    expect(Object.keys(store.images).sort()).toEqual(['f1', 'f2'])
    expect(store.images.f1).toEqual({ id: 'f1', mimeType: 'image/png', dataUrl: `data:image/png;base64,${btoa('png')}` })
    expect(store.images.f2.mimeType).toBe('image/svg+xml')
    expect(store.loaded).toBe(true)
  })

  it('вставленная картинка уходит в хранилище публичной и получает серверный идентификатор', async () => {
    const files = new InMemoryFiles()
    const store = makeStore(new InMemoryDrawings(), files)

    const id = await store.storeImage(new File([new Uint8Array(3)], 'схема.png', { type: 'image/png' }))

    expect(id).toBe('f1')
    expect(files.uploaded[0]).toMatchObject({ name: 'схема.png', mimeType: 'image/png', size: 3, visibility: 'public', owner: 'Аня' })
  })

  it('картинке без имени даёт имя по типу', async () => {
    const files = new InMemoryFiles()
    const store = makeStore(new InMemoryDrawings(), files)

    await store.storeImage(new Blob([new Uint8Array(3)], { type: 'image/jpeg' }))

    expect(files.uploaded[0].name).toBe('image.jpg')
  })

  it('ошибка загрузки картинки пробрасывается редактору, сцена её не получает', async () => {
    const files = new InMemoryFiles()
    files.failing = true
    const store = makeStore(new InMemoryDrawings(), files)

    await expect(store.storeImage(new File([new Uint8Array(3)], 'a.png', { type: 'image/png' }))).rejects.toThrow('недоступно')
  })
})

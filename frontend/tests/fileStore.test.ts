import { describe, expect, it } from 'vitest'
import { FileStore } from '../src/application/file/FileStore'
import type { FileRepository } from '../src/application/ports/FileRepository'
import type { FileDraft, StoredFile } from '../src/domain/file/StoredFile'

/** Хранилище в памяти: отвечает описанием, как сервер, и помнит, что у него просили. */
class InMemoryFiles implements FileRepository {
  readonly uploaded: Array<{ size: number; draft: FileDraft }> = []
  readonly removed: Array<{ id: string; requester: string }> = []
  failure: Error | null = null
  private sequence = 0

  async upload(data: Blob, draft: FileDraft): Promise<StoredFile> {
    if (this.failure !== null) throw this.failure

    this.uploaded.push({ size: data.size, draft })

    return this.stored(`f${++this.sequence}`, draft)
  }

  async get(id: string, requester: string): Promise<StoredFile> {
    if (this.failure !== null) throw this.failure

    return this.stored(id, { name: 'x.png', mimeType: 'image/png', size: 1, visibility: 'private', owner: requester })
  }

  async remove(id: string, requester: string): Promise<void> {
    if (this.failure !== null) throw this.failure

    this.removed.push({ id, requester })
  }

  async download(): Promise<Blob> {
    return new Blob()
  }

  contentUrl(id: string, requester: string): string {
    return `/api/files/${id}/content?requester=${requester}`
  }

  private stored(id: string, draft: FileDraft): StoredFile {
    return {
      id,
      name: draft.name,
      mimeType: draft.mimeType,
      size: draft.size,
      visibility: draft.visibility,
      owner: draft.owner,
      url: draft.visibility === 'public' ? `/files/2026/11/${id}.png` : null,
      uploadedAt: '2026-11-04T12:00:00+00:00',
    }
  }
}

const png = (bytes = 4) => new Blob([new Uint8Array(bytes)], { type: 'image/png' })

describe('стор файлов', () => {
  it('загружает файл и добавляет описание в список', async () => {
    const files = new InMemoryFiles()
    const store = new FileStore(files)

    const promise = store.upload({ data: png(), name: 'схема.png', visibility: 'public', owner: 'Аня' })

    expect(store.uploading).toBe(true)

    const file = await promise

    expect(file?.id).toBe('f1')
    expect(store.uploading).toBe(false)
    expect(store.files.map((f) => f.id)).toEqual(['f1'])
    expect(files.uploaded[0].draft).toMatchObject({ name: 'схема.png', mimeType: 'image/png', size: 4, owner: 'Аня' })
  })

  it('не отправляет файл, нарушающий правила, и показывает ошибку', async () => {
    const files = new InMemoryFiles()
    const store = new FileStore(files)

    const file = await store.upload({
      data: new Blob(['<html>'], { type: 'text/html' }),
      name: 'page.html',
      visibility: 'public',
      owner: 'Аня',
    })

    expect(file).toBeNull()
    expect(files.uploaded).toHaveLength(0)
    expect(store.error).toContain('text/html')
    expect(store.uploading).toBe(false)
  })

  it('ошибка сервера остаётся в сторе, а удачная загрузка её снимает', async () => {
    const files = new InMemoryFiles()
    const store = new FileStore(files)

    files.failure = new Error('Хранилище файлов недоступно')
    await store.upload({ data: png(), name: 'a.png', visibility: 'public', owner: 'Аня' })
    expect(store.error).toBe('Хранилище файлов недоступно')

    files.failure = null
    await store.upload({ data: png(), name: 'a.png', visibility: 'public', owner: 'Аня' })
    expect(store.error).toBeNull()
  })

  it('удаляет файл из списка от имени участника', async () => {
    const files = new InMemoryFiles()
    const store = new FileStore(files)
    await store.upload({ data: png(), name: 'a.png', visibility: 'public', owner: 'Аня' })

    await store.remove('f1', 'Аня')

    expect(store.files).toHaveLength(0)
    expect(files.removed).toEqual([{ id: 'f1', requester: 'Аня' }])
  })

  it('отказ в удалении не трогает список', async () => {
    const files = new InMemoryFiles()
    const store = new FileStore(files)
    await store.upload({ data: png(), name: 'a.png', visibility: 'public', owner: 'Аня' })

    files.failure = new Error('Удалить файл f1 может только владелец')
    await store.remove('f1', 'Боря')

    expect(store.files).toHaveLength(1)
    expect(store.error).toContain('только владелец')
  })

  it('адрес публичного файла — прямая ссылка, приватного — через API', async () => {
    const files = new InMemoryFiles()
    const store = new FileStore(files)
    const pub = await store.upload({ data: png(), name: 'a.png', visibility: 'public', owner: 'Аня' })
    const priv = await store.upload({ data: png(), name: 'b.png', visibility: 'private', owner: 'Аня' })

    expect(store.urlOf(pub!, 'Аня')).toBe('/files/2026/11/f1.png')
    expect(store.urlOf(priv!, 'Аня')).toBe('/api/files/f2/content?requester=Аня')
  })

  it('запрошенное описание попадает в список без дублей', async () => {
    const files = new InMemoryFiles()
    const store = new FileStore(files)

    await store.fetch('f9', 'Аня')
    await store.fetch('f9', 'Аня')

    expect(store.files.map((f) => f.id)).toEqual(['f9'])
  })
})

import { describe, expect, it } from 'vitest'
import { BoardStore } from '../src/application/board/BoardStore'
import type { NoteRepository } from '../src/application/ports/NoteRepository'
import type { RealtimeChannel, RealtimeHandlers } from '../src/application/ports/RealtimeChannel'
import type { Note, NoteDraft } from '../src/domain/note/Note'

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

/** Репозиторий в памяти: заметка появляется на доске только событием из канала, как и в жизни. */
class InMemoryNotes implements NoteRepository {
  readonly created: NoteDraft[] = []
  readonly removed: string[] = []

  constructor(
    private readonly stored: Note[] = [],
    private readonly failure: Error | null = null,
  ) {}

  async list(): Promise<Note[]> {
    if (this.failure !== null) throw this.failure

    return [...this.stored]
  }

  async create(draft: NoteDraft): Promise<Note> {
    this.created.push(draft)

    return note('новая', draft)
  }

  async move(id: string, x: number, y: number): Promise<Note> {
    return note(id, { x, y })
  }

  async remove(id: string): Promise<void> {
    if (this.failure !== null) throw this.failure

    this.removed.push(id)
  }
}

/** Канал, которым управляет тест: события и смена состояния вызываются вручную. */
class FakeChannel implements RealtimeChannel {
  handlers: RealtimeHandlers | null = null
  disconnected = 0
  readonly sent: Array<{ event: string; payload: unknown }> = []

  connect(handlers: RealtimeHandlers): () => void {
    this.handlers = handlers

    return () => {
      this.disconnected++
    }
  }

  send(event: string, payload: unknown): void {
    this.sent.push({ event, payload })
  }
}

function setup(notes = new InMemoryNotes()) {
  const channel = new FakeChannel()
  const store = new BoardStore(notes, channel)

  return { store, channel, notes }
}

describe('стор доски', () => {
  it('загружает заметки свежими первыми и отмечает завершение загрузки', async () => {
    const { store } = setup(
      new InMemoryNotes([
        note('старая', { createdAt: '2026-09-01T09:00:00+00:00' }),
        note('новая', { createdAt: '2026-09-01T11:00:00+00:00' }),
      ]),
    )

    expect(store.loaded).toBe(false)

    await store.load()

    expect(store.loaded).toBe(true)
    expect(store.notes.map((n) => n.id)).toEqual(['новая', 'старая'])
  })

  it('ошибка загрузки попадает в error, но загрузка считается завершённой', async () => {
    const { store } = setup(new InMemoryNotes([], new Error('сервер недоступен')))

    await store.load()

    expect(store.loaded).toBe(true)
    expect(store.error).toBe('сервер недоступен')
  })

  it('меняет доску по событиям канала и считает клиентов по presence', () => {
    const { store, channel } = setup()
    store.start()

    channel.handlers?.onEvent({ event: 'note.created', payload: note('a'), at: '' })
    channel.handlers?.onEvent({ event: 'presence', payload: { clients: 3 }, at: '' })
    channel.handlers?.onStateChange('online')

    expect(store.notes.map((n) => n.id)).toEqual(['a'])
    expect(store.clients).toBe(3)
    expect(store.connection).toBe('online')
  })

  it('команда не меняет доску сама: изменение придёт событием', async () => {
    const { store, notes } = setup()

    await store.addNote({ text: '  привет  ', color: '#FFD166', author: 'я' })

    expect(notes.created).toHaveLength(1)
    expect(notes.created[0]).toMatchObject({ text: 'привет', color: '#ffd166' })
    expect(store.notes).toHaveLength(0)
    expect(store.error).toBeNull()
  })

  it('нарушение правила домена не уходит в сеть и показывается как ошибка', async () => {
    const { store, notes } = setup()

    await store.addNote({ text: '   ', color: '#ffd166', author: 'я' })

    expect(notes.created).toHaveLength(0)
    expect(store.error).toBe('Текст заметки не может быть пустым')

    store.dismissError()

    expect(store.error).toBeNull()
  })

  it('удачная команда снимает прошлую ошибку', async () => {
    const { store } = setup()

    await store.addNote({ text: '', color: '#ffd166', author: 'я' })
    expect(store.error).not.toBeNull()

    await store.removeNote('a')

    expect(store.error).toBeNull()
  })

  it('ping уходит в канал напрямую, а остановка отключает канал', () => {
    const { store, channel } = setup()
    const stop = store.start()

    store.ping('я')
    stop()

    expect(channel.sent[0]).toMatchObject({ event: 'ping', payload: { author: 'я' } })
    expect(channel.disconnected).toBe(1)
  })
})

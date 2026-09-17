import { makeAutoObservable, observableRef, runInAction } from 'mobx'
import { applyRealtimeEvent, emptyBoard, replaceBoard } from '../../domain/board/Board'
import type { Board } from '../../domain/board/Board'
import { draftNote } from '../../domain/note/Note'
import type { ConnectionState, RealtimeEvent } from '../../domain/realtime/RealtimeEvent'
import type { NoteRepository } from '../ports/NoteRepository'
import type { RealtimeChannel } from '../ports/RealtimeChannel'
import { describeError } from '../shared/describeError'

/**
 * Состояние доски и сценарии работы с ней: первичная загрузка через
 * репозиторий, дальше — поток событий из realtime-канала.
 *
 * Собственных изменений состояния после команд нет: их вернёт тот же канал,
 * поэтому все клиенты, включая автора, обновляются одинаково. Стор —
 * единственное место, где состояние меняется; компоненты только наблюдают.
 */
export class BoardStore {
  notes: Board = emptyBoard
  /** Первичная загрузка завершена (успехом или ошибкой): пустая доска — уже не «ещё грузится». */
  loaded = false
  connection: ConnectionState = 'connecting'
  clients = 0
  error: string | null = null

  constructor(
    private readonly repository: NoteRepository,
    private readonly realtime: RealtimeChannel,
  ) {
    // Доска заменяется целиком новым массивом, внутрь заметок MobX лезть не нужно
    makeAutoObservable<this, 'repository' | 'realtime'>(
      this,
      { repository: false, realtime: false, notes: observableRef },
      { autoBind: true },
    )
  }

  /**
   * Запускает загрузку и подписку на канал.
   *
   * @returns функция отключения от канала
   */
  start(): () => void {
    void this.load()

    return this.realtime.connect({
      onEvent: this.receive,
      onStateChange: this.setConnection,
    })
  }

  async load(): Promise<void> {
    try {
      const fetched = await this.repository.list()

      runInAction(() => {
        this.notes = replaceBoard(fetched)
      })
    } catch (cause) {
      runInAction(() => {
        this.error = describeError(cause)
      })
    } finally {
      runInAction(() => {
        this.loaded = true
      })
    }
  }

  receive(event: RealtimeEvent): void {
    if (event.event === 'presence') {
      this.clients = event.payload.clients
    }

    this.notes = applyRealtimeEvent(this.notes, event)
  }

  setConnection(state: ConnectionState): void {
    this.connection = state
  }

  async addNote(input: { text: string; color: string; author: string }): Promise<void> {
    await this.run(() => {
      // Доменные правила проверяются до сети: бессмысленный запрос не уходит
      const draft = draftNote({
        ...input,
        x: Math.floor(Math.random() * 600),
        y: Math.floor(Math.random() * 300),
      })

      return this.repository.create(draft)
    })
  }

  async moveNote(id: string, x: number, y: number): Promise<void> {
    await this.run(() => this.repository.move(id, x, y))
  }

  async removeNote(id: string): Promise<void> {
    await this.run(() => this.repository.remove(id))
  }

  ping(author: string): void {
    this.realtime.send('ping', { author, at: Date.now() })
  }

  dismissError(): void {
    this.error = null
  }

  /** Команда либо проходит и снимает прошлую ошибку, либо оставляет текст новой. */
  private async run(command: () => Promise<unknown>): Promise<void> {
    try {
      await command()

      runInAction(() => {
        this.error = null
      })
    } catch (cause) {
      runInAction(() => {
        this.error = describeError(cause)
      })
    }
  }
}

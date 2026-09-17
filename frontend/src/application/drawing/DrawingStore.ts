import { makeAutoObservable, observableRef, runInAction } from 'mobx'
import type { DrawingLine } from '../../domain/drawing/Drawing'
import type { DrawingRepository } from '../ports/DrawingRepository'
import { Debounce } from '../shared/debounce'
import { describeError } from '../shared/describeError'

export type SaveStatus = 'saved' | 'saving' | 'error'

/** Пауза между последним штрихом и отправкой на сервер. */
export const SAVE_DELAY_MS = 1500

/**
 * Рисунок одной заметки: загрузка, текущий список штрихов и отложенное
 * сохранение. Полотно только рисует и сообщает новый список линий —
 * когда и что отправлять, решает стор.
 */
export class DrawingStore {
  /** Штрихи целиком: список заменяется, а не мутируется. */
  lines: DrawingLine[] = []
  /** Рисунок загружен — полотно нельзя показывать пустым раньше времени. */
  loaded = false
  loadError: string | null = null
  saveStatus: SaveStatus = 'saved'

  private readonly save: Debounce<[DrawingLine[]]>
  /** Ответы могут прийти не по порядку: статус выставляет только последний запрос. */
  private saveSequence = 0

  constructor(
    readonly noteId: string,
    private readonly drawings: DrawingRepository,
    saveDelayMs: number = SAVE_DELAY_MS,
  ) {
    this.save = new Debounce((snapshot: DrawingLine[]) => this.persist(snapshot), saveDelayMs)

    // Линий тысячи, точек в них — десятки тысяч: наблюдаем ссылку на массив,
    // а не каждую точку, иначе MobX оборачивал бы весь рисунок в прокси
    makeAutoObservable<this, 'drawings' | 'save' | 'saveSequence' | 'persist'>(
      this,
      {
        noteId: false,
        drawings: false,
        save: false,
        saveSequence: false,
        persist: false,
        lines: observableRef,
      },
      { autoBind: true },
    )
  }

  async load(): Promise<void> {
    try {
      const loaded = await this.drawings.load(this.noteId)

      runInAction(() => {
        this.lines = loaded
        this.loaded = true
      })
    } catch (cause) {
      runInAction(() => {
        this.loadError = describeError(cause)
      })
    }
  }

  /** Новый список штрихов: показать сразу, сохранить с задержкой. */
  replaceLines(next: DrawingLine[]): void {
    this.lines = next
    // «Сохранение…» показываем сразу: изменения уже есть, на сервере их ещё нет
    this.saveStatus = 'saving'
    this.save.schedule(next)
  }

  retrySave(): void {
    if (this.saveStatus !== 'error') return

    this.save.cancel()
    this.persist(this.lines)
  }

  /**
   * Уход со страницы: отложенное сохранение выполняется сразу, а не теряется —
   * потерять последний штрих хуже, чем отправить его на секунду раньше.
   */
  dispose(): void {
    this.save.flush()
  }

  private persist(snapshot: DrawingLine[]): void {
    const sequence = ++this.saveSequence

    runInAction(() => {
      this.saveStatus = 'saving'
    })

    this.drawings
      .save(this.noteId, snapshot)
      .then(() => this.finishSave(sequence, 'saved'))
      .catch(() => this.finishSave(sequence, 'error'))
  }

  private finishSave(sequence: number, status: SaveStatus): void {
    if (sequence !== this.saveSequence) return

    runInAction(() => {
      this.saveStatus = status
    })
  }
}

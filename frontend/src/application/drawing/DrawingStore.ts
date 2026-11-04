import { makeAutoObservable, observableRef, runInAction } from 'mobx'
import { imageFileIds, imageFileName } from '../../domain/drawing/Drawing'
import type { DrawingElement, DrawingImage } from '../../domain/drawing/Drawing'
import { draftFile } from '../../domain/file/StoredFile'
import type { DrawingRepository } from '../ports/DrawingRepository'
import type { FileRepository } from '../ports/FileRepository'
import { toDataUrl } from '../shared/dataUrl'
import { Debounce } from '../shared/debounce'
import { describeError } from '../shared/describeError'

export type SaveStatus = 'saved' | 'saving' | 'error'

/** Пауза между последним штрихом и отправкой на сервер. */
export const SAVE_DELAY_MS = 1500

/**
 * Рисунок одной заметки: загрузка, текущий список элементов (штрихов,
 * фигур и картинок) и отложенное сохранение. Полотно только рисует и
 * сообщает новый список — когда и что отправлять, решает стор.
 *
 * Картинки сцена хранит не сама: элемент `image` ссылается на файл в
 * хранилище, а стор знает, откуда редактору его взять. Файл загружается
 * в момент вставки и сразу получает серверный идентификатор, поэтому в
 * сцене никогда не бывает ссылок на незагруженные картинки.
 */
export class DrawingStore {
  /** Элементы целиком: список заменяется, а не мутируется. */
  elements: DrawingElement[] = []
  /** Картинки сцены по идентификатору файла; словарь заменяется целиком. */
  images: Record<string, DrawingImage> = {}
  /** Рисунок загружен — полотно нельзя показывать пустым раньше времени. */
  loaded = false
  loadError: string | null = null
  saveStatus: SaveStatus = 'saved'

  private readonly save: Debounce<[DrawingElement[]]>
  /** Ответы могут прийти не по порядку: статус выставляет только последний запрос. */
  private saveSequence = 0

  constructor(
    readonly noteId: string,
    private readonly drawings: DrawingRepository,
    private readonly files: FileRepository,
    /** От чьего имени загружаются картинки и запрашиваются их описания. */
    private readonly participant: string,
    saveDelayMs: number = SAVE_DELAY_MS,
  ) {
    this.save = new Debounce((snapshot: DrawingElement[]) => this.persist(snapshot), saveDelayMs)

    // Элементов тысячи, точек в штрихах — десятки тысяч: наблюдаем ссылку на
    // массив, а не каждую точку, иначе MobX оборачивал бы весь рисунок в прокси
    makeAutoObservable<this, 'drawings' | 'files' | 'participant' | 'save' | 'saveSequence' | 'persist' | 'describeImages' | 'fetchImage'>(
      this,
      {
        noteId: false,
        drawings: false,
        files: false,
        participant: false,
        save: false,
        saveSequence: false,
        persist: false,
        describeImages: false,
        fetchImage: false,
        elements: observableRef,
        images: observableRef,
      },
      { autoBind: true },
    )
  }

  async load(): Promise<void> {
    try {
      const loaded = await this.drawings.load(this.noteId)
      const images = await this.describeImages(imageFileIds(loaded))

      runInAction(() => {
        this.elements = loaded
        this.images = images
        this.loaded = true
      })
    } catch (cause) {
      runInAction(() => {
        this.loadError = describeError(cause)
      })
    }
  }

  /** Новый список элементов: показать сразу, сохранить с задержкой. */
  replaceElements(next: DrawingElement[]): void {
    this.elements = next
    // «Сохранение…» показываем сразу: изменения уже есть, на сервере их ещё нет
    this.saveStatus = 'saving'
    this.save.schedule(next)
  }

  /**
   * Вставка картинки: файл уходит в хранилище сразу, а его серверный
   * идентификатор становится `fileId` элемента. Ошибка пробрасывается —
   * редактор покажет её и не вставит элемент, так что сцена без файла
   * не бывает. Содержимое редактор держит сам, в `images` оно не нужно.
   * Картинки публичные: рисунок видят все участники доски.
   */
  async storeImage(image: Blob & { readonly name?: string }): Promise<string> {
    const draft = draftFile({
      name: image.name ?? imageFileName(image.type),
      mimeType: image.type,
      size: image.size,
      visibility: 'public',
      owner: this.participant,
    })
    const stored = await this.files.upload(image, draft)

    return stored.id
  }

  retrySave(): void {
    if (this.saveStatus !== 'error') return

    this.save.cancel()
    this.persist(this.elements)
  }

  /**
   * Уход со страницы: отложенное сохранение выполняется сразу, а не теряется —
   * потерять последний штрих хуже, чем отправить его на секунду раньше.
   */
  dispose(): void {
    this.save.flush()
  }

  /** Содержимое картинок; недоступный файл пропускается — редактор покажет рамку без картинки. */
  private async describeImages(ids: string[]): Promise<Record<string, DrawingImage>> {
    const fetched = await Promise.all(ids.map((id) => this.fetchImage(id).catch(() => null)))
    const images: Record<string, DrawingImage> = {}

    for (const image of fetched) {
      if (image !== null) images[image.id] = image
    }

    return images
  }

  private async fetchImage(id: string): Promise<DrawingImage> {
    const blob = await this.files.download(id, this.participant)

    return { id, mimeType: blob.type.split(';')[0].trim(), dataUrl: await toDataUrl(blob) }
  }

  private persist(snapshot: DrawingElement[]): void {
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

import { makeAutoObservable, observableRef, runInAction } from 'mobx'
import { draftFile } from '../../domain/file/StoredFile'
import type { FileVisibility, StoredFile } from '../../domain/file/StoredFile'
import type { FileRepository } from '../ports/FileRepository'
import { describeError } from '../shared/describeError'

/**
 * Файлы, с которыми работает этот клиент: загруженные за сессию и
 * запрошенные по идентификатору. Списка на сервере нет, поэтому стор
 * ничего не подгружает сам — он копит то, что через него прошло.
 */
export class FileStore {
  /** Список заменяется целиком: внутрь описаний файлов MobX лезть незачем. */
  files: StoredFile[] = []
  /** Сколько загрузок в полёте: несколько файлов можно отправить разом. */
  pending = 0
  error: string | null = null

  constructor(private readonly repository: FileRepository) {
    makeAutoObservable<this, 'repository'>(
      this,
      { repository: false, files: observableRef },
      { autoBind: true },
    )
  }

  get uploading(): boolean {
    return this.pending > 0
  }

  /**
   * Загрузка: правила проверяются до сети, а описание файла попадает в
   * список после ответа сервера. Возвращает описание или null при ошибке —
   * текст ошибки остаётся в `error`.
   */
  async upload(input: {
    data: Blob
    name: string
    visibility: FileVisibility
    owner: string
  }): Promise<StoredFile | null> {
    this.pending += 1

    try {
      const draft = draftFile({
        name: input.name,
        mimeType: input.data.type,
        size: input.data.size,
        visibility: input.visibility,
        owner: input.owner,
      })
      const file = await this.repository.upload(input.data, draft)

      runInAction(() => {
        this.files = [...this.files.filter((known) => known.id !== file.id), file]
        this.error = null
      })

      return file
    } catch (cause) {
      runInAction(() => {
        this.error = describeError(cause)
      })

      return null
    } finally {
      runInAction(() => {
        this.pending -= 1
      })
    }
  }

  /** Описание файла по идентификатору — например, из сохранённого рисунка. */
  async fetch(id: string, requester: string): Promise<StoredFile | null> {
    try {
      const file = await this.repository.get(id, requester)

      runInAction(() => {
        this.files = [...this.files.filter((known) => known.id !== id), file]
        this.error = null
      })

      return file
    } catch (cause) {
      runInAction(() => {
        this.error = describeError(cause)
      })

      return null
    }
  }

  async remove(id: string, requester: string): Promise<void> {
    try {
      await this.repository.remove(id, requester)

      runInAction(() => {
        this.files = this.files.filter((file) => file.id !== id)
        this.error = null
      })
    } catch (cause) {
      runInAction(() => {
        this.error = describeError(cause)
      })
    }
  }

  /** Откуда брать содержимое: публичный — напрямую, приватный — через API. */
  urlOf(file: StoredFile, requester: string): string {
    return file.url ?? this.repository.contentUrl(file.id, requester)
  }

  dismissError(): void {
    this.error = null
  }
}

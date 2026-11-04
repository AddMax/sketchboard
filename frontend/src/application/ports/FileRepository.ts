import type { FileDraft, StoredFile } from '../../domain/file/StoredFile'

/**
 * Порт хранилища файлов. Байты уходят вместе с заготовкой; кто спрашивает
 * (`requester`) — передаётся при каждом чтении и удалении, потому что
 * авторизации нет и сервер верит имени на слово.
 */
export interface FileRepository {
  upload(data: Blob, draft: FileDraft): Promise<StoredFile>
  get(id: string, requester: string): Promise<StoredFile>
  remove(id: string, requester: string): Promise<void>
  /** Адрес содержимого через API — единственный путь к приватному файлу. */
  contentUrl(id: string, requester: string): string
}

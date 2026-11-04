import { DomainError } from '../note/errors'

export type FileVisibility = 'public' | 'private'

/** Файл в хранилище, каким его описывает сервер. */
export interface StoredFile {
  readonly id: string
  readonly name: string
  readonly mimeType: string
  readonly size: number
  readonly visibility: FileVisibility
  readonly owner: string
  /** Прямая ссылка есть только у публичного файла; приватный читается через API. */
  readonly url: string | null
  readonly uploadedAt: string
}

/** Заготовка загрузки: что отправляем, кроме самих байтов. */
export interface FileDraft {
  readonly name: string
  readonly mimeType: string
  readonly size: number
  readonly visibility: FileVisibility
  readonly owner: string
}

// Те же пределы, что и на сервере (Domain\File): сверх них загрузка
// ответит 422, но об этом лучше сказать до того, как байты ушли в сеть
export const FILE_MAX_BYTES = 10 * 1024 * 1024
export const FILE_NAME_MAX_LENGTH = 255
export const FILE_ALLOWED_TYPES: readonly string[] = [
  'image/png',
  'image/jpeg',
  'image/gif',
  'image/webp',
  'image/svg+xml',
  'application/pdf',
  'text/plain',
]

export function isAllowedFileType(mimeType: string): boolean {
  return FILE_ALLOWED_TYPES.includes(mimeType.split(';')[0].trim().toLowerCase())
}

/**
 * Правила загрузки — дубликат серверных, намеренный: клиент обязан
 * отказать до сетевого запроса, но последнее слово за доменом бэкенда.
 * Тип браузер ставит по расширению, а сервер определяет по содержимому,
 * поэтому пустой тип здесь не ошибка — пусть решает сервер.
 */
export function draftFile(input: {
  name: string
  mimeType: string
  size: number
  visibility: FileVisibility
  owner: string
}): FileDraft {
  // Имя бывает с путём (drag-and-drop из некоторых источников) — сервер его отрежет, но и нам он не нужен
  const name = input.name.replace(/\\/g, '/').split('/').pop()?.trim() ?? ''

  if (name === '' || name === '.' || name === '..') {
    throw new DomainError('У файла должно быть имя', 'name')
  }

  if (name.length > FILE_NAME_MAX_LENGTH) {
    throw new DomainError(`Имя файла длиннее ${FILE_NAME_MAX_LENGTH} символов`, 'name')
  }

  if (input.size <= 0) {
    throw new DomainError('Файл пуст', 'file')
  }

  if (input.size > FILE_MAX_BYTES) {
    throw new DomainError(`Файл больше ${FILE_MAX_BYTES / 1024 / 1024} МБ`, 'file')
  }

  const mimeType = input.mimeType.split(';')[0].trim().toLowerCase()

  if (mimeType !== '' && !isAllowedFileType(mimeType)) {
    throw new DomainError(`Файлы типа «${mimeType}» не принимаются`, 'mimeType')
  }

  return {
    name,
    mimeType,
    size: input.size,
    visibility: input.visibility,
    owner: input.owner,
  }
}

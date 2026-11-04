/**
 * Элемент сцены Excalidraw. Домен знает о нём ровно столько, сколько сервер:
 * идентификатор, тип и признак удаления. Геометрия, стиль и служебные поля
 * редактора (version, seed, index…) хранятся и возвращаются как есть —
 * модель принадлежит Excalidraw, а не нам.
 */
export interface DrawingElement {
  readonly id: string
  readonly type: string
  readonly isDeleted?: boolean
  readonly [field: string]: unknown
}

/**
 * Картинка сцены: файл в хранилище, на который элемент `image` ссылается
 * полем `fileId`. Редактор держит картинки data-URL-ами, поэтому и здесь
 * содержимое, а не адрес.
 */
export interface DrawingImage {
  readonly id: string
  readonly mimeType: string
  readonly dataUrl: string
}

// Тот же предел, что и на сервере (Domain\Drawing): сверх него сохранение
// ответит 422, и редактор покажет ошибку
export const DRAWING_MAX_ELEMENTS = 5_000

/** Файлы, на которые ссылаются живые картинки сцены, без повторов. */
export function imageFileIds(elements: readonly DrawingElement[]): string[] {
  const ids = new Set<string>()

  for (const element of liveElements(elements)) {
    if (element.type === 'image' && typeof element.fileId === 'string' && element.fileId !== '') {
      ids.add(element.fileId)
    }
  }

  return [...ids]
}

/** Имя для картинки, вставленной без имени (например, из буфера обмена). */
export function imageFileName(mimeType: string): string {
  const subtype = mimeType.split('/')[1]?.split('+')[0] ?? 'bin'

  return `image.${subtype === 'jpeg' ? 'jpg' : subtype}`
}

/**
 * Excalidraw держит удалённые элементы в сцене как надгробия — так он
 * сливает правки и откатывает удаление. В хранилище им делать нечего:
 * сервер такие отвергает, поэтому перед отправкой сцена очищается.
 */
export function liveElements<T extends DrawingElement>(elements: readonly T[]): T[] {
  return elements.filter((element) => element.isDeleted !== true)
}

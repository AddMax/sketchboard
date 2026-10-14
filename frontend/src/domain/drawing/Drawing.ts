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

// Тот же предел, что и на сервере (Domain\Drawing): сверх него сохранение
// ответит 422, и редактор покажет ошибку
export const DRAWING_MAX_ELEMENTS = 5_000

/**
 * Excalidraw держит удалённые элементы в сцене как надгробия — так он
 * сливает правки и откатывает удаление. В хранилище им делать нечего:
 * сервер такие отвергает, поэтому перед отправкой сцена очищается.
 */
export function liveElements<T extends DrawingElement>(elements: readonly T[]): T[] {
  return elements.filter((element) => element.isDeleted !== true)
}

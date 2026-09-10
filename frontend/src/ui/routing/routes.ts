/**
 * Экраны приложения. Маршрутов два, поэтому обходимся без библиотеки:
 * чистые функции разбора и сборки пути легко проверить тестом.
 */
export type Route = { readonly kind: 'board' } | { readonly kind: 'draw'; readonly noteId: string }

const DRAW_PATTERN = /^\/notes\/([^/]+)\/draw\/?$/

export const BOARD_PATH = '/'

export function drawPath(noteId: string): string {
  return `/notes/${encodeURIComponent(noteId)}/draw`
}

/** Неизвестный путь считаем доской: отдельной страницы 404 в одном экране нет. */
export function parseRoute(pathname: string): Route {
  const match = DRAW_PATTERN.exec(pathname)

  if (match !== null) {
    return { kind: 'draw', noteId: decodeURIComponent(match[1]) }
  }

  return { kind: 'board' }
}

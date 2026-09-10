/** Координаты точки в виртуальном пространстве бесконечной доски. */
export interface Point {
  x: number
  y: number
}

/** Один штрих: ломаная из точек, цвет и толщина кисти. */
export interface DrawingLine {
  id: string
  points: Point[]
  color: string
  width: number
}

// Те же пределы, что и на сервере (Domain\Drawing): клиент не даст
// нарисовать то, что сервер всё равно отвергнет
export const BRUSH_WIDTH_MIN = 1
export const BRUSH_WIDTH_MAX = 24
export const DRAWING_MAX_LINES = 5_000

/** Идентификатор линии назначает клиент: линия рождается в браузере. */
export function newLineId(): string {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID()
  }

  return `${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 10)}`
}

/** Две цифры после запятой хватает глазу, а JSON становится вдвое короче. */
export function roundPoint(point: Point): Point {
  return { x: Math.round(point.x * 100) / 100, y: Math.round(point.y * 100) / 100 }
}

/** Координаты точки в виртуальном пространстве бесконечной доски. */
export interface Point {
  x: number
  y: number
}

/** Один штрих: ломаная из точек, цвет и толщина кисти. */
export interface DrawingLine {
  type: 'line'
  id: string
  points: Point[]
  color: string
  width: number
}

export type ShapeKind = 'rect' | 'ellipse' | 'triangle'

export const SHAPE_KINDS: readonly ShapeKind[] = ['rect', 'ellipse', 'triangle']

/**
 * Примитивная фигура в ограничивающем прямоугольнике: x, y — левый верхний
 * угол, ширина и высота положительные. Эллипс вписан в прямоугольник,
 * треугольник стоит на его нижней стороне.
 */
export interface Shape {
  type: 'shape'
  id: string
  kind: ShapeKind
  x: number
  y: number
  width: number
  height: number
  strokeColor: string
  strokeWidth: number
  /** Цвет заливки; null — только контур. */
  fill: string | null
}

/** Элемент рисунка. Порядок в списке задаёт наложение. */
export type DrawingElement = DrawingLine | Shape

// Те же пределы, что и на сервере (Domain\Drawing): клиент не даст
// нарисовать то, что сервер всё равно отвергнет
export const BRUSH_WIDTH_MIN = 1
export const BRUSH_WIDTH_MAX = 24
export const DRAWING_MAX_ELEMENTS = 5_000

/** Меньше этого фигура вырождается в точку — сервер такую отвергнет. */
export const SHAPE_MIN_SIZE = 1

/** Идентификатор элемента назначает клиент: элемент рождается в браузере. */
export function newElementId(): string {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID()
  }

  return `${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 10)}`
}

/** Две цифры после запятой хватает глазу, а JSON становится вдвое короче. */
export function roundPoint(point: Point): Point {
  return { x: Math.round(point.x * 100) / 100, y: Math.round(point.y * 100) / 100 }
}

export interface ShapeStyle {
  strokeColor: string
  strokeWidth: number
  fill?: string | null
}

/**
 * Фигура по двум углам растягивания. Пользователь тянет в любую сторону,
 * а хранится всегда левый верхний угол и положительные размеры; при
 * `proportional` (Shift) фигура вписывается в квадрат по большей стороне,
 * растущий от точки начала.
 */
export function shapeBetween(
  id: string,
  kind: ShapeKind,
  start: Point,
  end: Point,
  style: ShapeStyle,
  proportional = false,
): Shape {
  let dx = end.x - start.x
  let dy = end.y - start.y

  if (proportional) {
    const side = Math.max(Math.abs(dx), Math.abs(dy))
    dx = Math.sign(dx || 1) * side
    dy = Math.sign(dy || 1) * side
  }

  const width = Math.max(SHAPE_MIN_SIZE, Math.abs(dx))
  const height = Math.max(SHAPE_MIN_SIZE, Math.abs(dy))
  const origin = roundPoint({
    x: dx < 0 ? start.x - width : start.x,
    y: dy < 0 ? start.y - height : start.y,
  })

  return {
    type: 'shape',
    id,
    kind,
    x: origin.x,
    y: origin.y,
    width: Math.round(width * 100) / 100,
    height: Math.round(height * 100) / 100,
    strokeColor: style.strokeColor,
    strokeWidth: style.strokeWidth,
    fill: style.fill ?? null,
  }
}

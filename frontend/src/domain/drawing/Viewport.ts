import type { Point } from './Drawing'

/**
 * Окно в бесконечную доску: сдвиг в экранных пикселях и масштаб.
 * Экранная точка s и точка доски w связаны как s = w * zoom + pan.
 * Всё здесь — чистые функции: компонент только хранит результат.
 */
export interface Viewport {
  readonly pan: Point
  readonly zoom: number
}

export const ZOOM_MIN = 0.1
export const ZOOM_MAX = 10

export const initialViewport: Viewport = { pan: { x: 0, y: 0 }, zoom: 1 }

export function clampZoom(zoom: number): number {
  return Math.min(ZOOM_MAX, Math.max(ZOOM_MIN, zoom))
}

export function screenToWorld(view: Viewport, screen: Point): Point {
  return { x: (screen.x - view.pan.x) / view.zoom, y: (screen.y - view.pan.y) / view.zoom }
}

export function worldToScreen(view: Viewport, world: Point): Point {
  return { x: world.x * view.zoom + view.pan.x, y: world.y * view.zoom + view.pan.y }
}

/**
 * Масштабирует так, чтобы точка доски под курсором осталась на месте:
 * иначе при зуме картинка «уезжает» от курсора.
 */
export function zoomAt(view: Viewport, anchor: Point, factor: number): Viewport {
  const zoom = clampZoom(view.zoom * factor)

  if (zoom === view.zoom) {
    return view
  }

  const ratio = zoom / view.zoom

  return {
    zoom,
    pan: {
      x: anchor.x - (anchor.x - view.pan.x) * ratio,
      y: anchor.y - (anchor.y - view.pan.y) * ratio,
    },
  }
}

export function panBy(view: Viewport, dx: number, dy: number): Viewport {
  return { zoom: view.zoom, pan: { x: view.pan.x + dx, y: view.pan.y + dy } }
}

export interface Bounds {
  readonly left: number
  readonly top: number
  readonly right: number
  readonly bottom: number
}

/** Какая часть доски сейчас видна — сетка рисуется только внутри неё. */
export function visibleBounds(view: Viewport, width: number, height: number): Bounds {
  const topLeft = screenToWorld(view, { x: 0, y: 0 })
  const bottomRight = screenToWorld(view, { x: width, y: height })

  return { left: topLeft.x, top: topLeft.y, right: bottomRight.x, bottom: bottomRight.y }
}

/**
 * Шаг сетки в единицах доски: «круглые» числа 1-2-5 (…, 10, 20, 50, 100, …),
 * подобранные так, чтобы на экране между линиями было не меньше minScreenGap
 * пикселей. При сильном отдалении сетка не сливается в серую заливку,
 * при приближении — не превращается в редкие одинокие линии.
 */
export function gridStep(zoom: number, minScreenGap = 24): number {
  const mantissas = [1, 2, 5]

  // Начинаем с порядка, при котором даже шаг «1» заведомо слишком мелок
  let magnitude = 1
  while (magnitude * zoom >= minScreenGap) {
    magnitude /= 10
  }

  for (;;) {
    for (const mantissa of mantissas) {
      const step = mantissa * magnitude

      if (step * zoom >= minScreenGap) {
        return step
      }
    }

    magnitude *= 10
  }
}

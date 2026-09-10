import { describe, expect, it } from 'vitest'
import {
  ZOOM_MAX,
  ZOOM_MIN,
  gridStep,
  initialViewport,
  panBy,
  screenToWorld,
  visibleBounds,
  worldToScreen,
  zoomAt,
} from '../src/domain/drawing/Viewport'

describe('вьюпорт доски', () => {
  it('переводит экранные координаты в координаты доски и обратно', () => {
    const view = { pan: { x: 100, y: -50 }, zoom: 2 }

    expect(screenToWorld(view, { x: 300, y: 150 })).toEqual({ x: 100, y: 100 })
    expect(worldToScreen(view, { x: 100, y: 100 })).toEqual({ x: 300, y: 150 })
  })

  it('при зуме точка доски под курсором остаётся на месте', () => {
    const view = { pan: { x: 40, y: 60 }, zoom: 1.5 }
    const cursor = { x: 320, y: 200 }
    const before = screenToWorld(view, cursor)

    const zoomed = zoomAt(view, cursor, 2)

    expect(zoomed.zoom).toBeCloseTo(3)
    expect(screenToWorld(zoomed, cursor).x).toBeCloseTo(before.x)
    expect(screenToWorld(zoomed, cursor).y).toBeCloseTo(before.y)
  })

  it('масштаб не выходит за пределы 0.1–10', () => {
    expect(zoomAt(initialViewport, { x: 0, y: 0 }, 1000).zoom).toBe(ZOOM_MAX)
    expect(zoomAt(initialViewport, { x: 0, y: 0 }, 0.0001).zoom).toBe(ZOOM_MIN)
  })

  it('на границе масштаба вьюпорт не меняется', () => {
    const atMax = { pan: { x: 5, y: 5 }, zoom: ZOOM_MAX }

    expect(zoomAt(atMax, { x: 100, y: 100 }, 2)).toBe(atMax)
  })

  it('сдвиг меняет только pan', () => {
    expect(panBy({ pan: { x: 1, y: 2 }, zoom: 3 }, 10, -20)).toEqual({ pan: { x: 11, y: -18 }, zoom: 3 })
  })

  it('видимая область считается с учётом pan и zoom', () => {
    expect(visibleBounds({ pan: { x: 100, y: 0 }, zoom: 2 }, 800, 600)).toEqual({
      left: -50,
      top: 0,
      right: 350,
      bottom: 300,
    })
  })

  it('шаг сетки держит расстояние между линиями не меньше минимума', () => {
    for (const zoom of [ZOOM_MIN, 0.3, 1, 2.7, ZOOM_MAX]) {
      expect(gridStep(zoom) * zoom).toBeGreaterThanOrEqual(24)
    }
  })

  it('при отдалении шаг сетки растёт ступенями 1-2-5', () => {
    expect(gridStep(1)).toBe(50)
    expect(gridStep(0.4)).toBe(100)
    expect(gridStep(0.2)).toBe(200)
    expect(gridStep(0.1)).toBe(500)
  })
})

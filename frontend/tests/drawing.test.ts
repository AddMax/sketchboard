import { describe, expect, it } from 'vitest'
import { SHAPE_MIN_SIZE, shapeBetween } from '../src/domain/drawing/Drawing'

const style = { strokeColor: '#1e88e5', strokeWidth: 3 }

describe('фигура по двум углам', () => {
  it('растягивание вправо-вниз даёт прямоугольник от точки начала', () => {
    const shape = shapeBetween('s', 'rect', { x: 10, y: 20 }, { x: 110, y: 60 }, style)

    expect(shape).toMatchObject({ type: 'shape', kind: 'rect', x: 10, y: 20, width: 100, height: 40, fill: null })
  })

  it('растягивание влево-вверх нормализуется: угол — левый верхний, размеры положительные', () => {
    const shape = shapeBetween('s', 'ellipse', { x: 110, y: 60 }, { x: 10, y: 20 }, style)

    expect(shape).toMatchObject({ x: 10, y: 20, width: 100, height: 40 })
  })

  it('вырожденная фигура получает минимальный размер, а не нулевой', () => {
    const shape = shapeBetween('s', 'triangle', { x: 5, y: 5 }, { x: 5, y: 5 }, style)

    expect(shape.width).toBe(SHAPE_MIN_SIZE)
    expect(shape.height).toBe(SHAPE_MIN_SIZE)
  })

  it('с Shift фигура вписывается в квадрат по большей стороне и растёт от точки начала', () => {
    const shape = shapeBetween('s', 'rect', { x: 0, y: 0 }, { x: -30, y: 80 }, style, true)

    expect(shape).toMatchObject({ x: -80, y: 0, width: 80, height: 80 })
  })

  it('переносит стиль кисти в контур и округляет координаты до сотых', () => {
    const shape = shapeBetween('s', 'rect', { x: 0.123, y: 0 }, { x: 10.456, y: 10 }, { ...style, fill: '#ffd166' })

    expect(shape).toMatchObject({ x: 0.12, width: 10.33, strokeColor: '#1e88e5', strokeWidth: 3, fill: '#ffd166' })
  })
})

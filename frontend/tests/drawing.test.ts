import { describe, expect, it } from 'vitest'
import { liveElements } from '../src/domain/drawing/Drawing'

describe('элементы сцены', () => {
  it('отбрасывает удалённые элементы, сохраняя порядок остальных', () => {
    const elements = [
      { id: 'a', type: 'rectangle' },
      { id: 'b', type: 'freedraw', isDeleted: true },
      { id: 'c', type: 'text', isDeleted: false },
    ]

    expect(liveElements(elements).map((element) => element.id)).toEqual(['a', 'c'])
  })

  it('не меняет исходный список и не трогает поля редактора', () => {
    const elements = [{ id: 'a', type: 'ellipse', version: 3, seed: 42, customData: { note: 'мой' } }]

    const live = liveElements(elements)

    expect(live).not.toBe(elements)
    expect(live[0]).toBe(elements[0])
  })
})

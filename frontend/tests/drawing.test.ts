import { describe, expect, it } from 'vitest'
import { imageFileIds, imageFileName, liveElements } from '../src/domain/drawing/Drawing'

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

  it('собирает файлы живых картинок без повторов', () => {
    const elements = [
      { id: 'a', type: 'image', fileId: 'f1' },
      { id: 'b', type: 'image', fileId: 'f1' },
      { id: 'c', type: 'image', fileId: 'f2', isDeleted: true },
      { id: 'd', type: 'image', fileId: null },
      { id: 'e', type: 'rectangle', fileId: 'не картинка' },
    ]

    expect(imageFileIds(elements)).toEqual(['f1'])
  })

  it('подбирает имя картинке по типу', () => {
    expect(imageFileName('image/jpeg')).toBe('image.jpg')
    expect(imageFileName('image/svg+xml')).toBe('image.svg')
    expect(imageFileName('')).toBe('image.bin')
  })
})

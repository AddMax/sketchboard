import { describe, expect, it } from 'vitest'
import { DomainError } from '../src/domain/note/errors'
import { FILE_MAX_BYTES, draftFile, isAllowedFileType } from '../src/domain/file/StoredFile'

const input = {
  name: 'схема.png',
  mimeType: 'image/png',
  size: 1024,
  visibility: 'public' as const,
  owner: 'Аня',
}

describe('заготовка файла', () => {
  it('оставляет от пути только имя', () => {
    expect(draftFile({ ...input, name: 'C:\\pictures\\схема.png' }).name).toBe('схема.png')
    expect(draftFile({ ...input, name: '/tmp/схема.png' }).name).toBe('схема.png')
  })

  it('не принимает файл без имени', () => {
    expect(() => draftFile({ ...input, name: '  ' })).toThrow(DomainError)
    expect(() => draftFile({ ...input, name: 'dir/' })).toThrow(DomainError)
  })

  it('отказывает пустому и слишком большому файлу до сети', () => {
    expect(() => draftFile({ ...input, size: 0 })).toThrow('Файл пуст')
    expect(() => draftFile({ ...input, size: FILE_MAX_BYTES + 1 })).toThrow('больше 10 МБ')
    expect(draftFile({ ...input, size: FILE_MAX_BYTES }).size).toBe(FILE_MAX_BYTES)
  })

  it('отвергает тип не из списка, но пропускает неизвестный — его определит сервер', () => {
    expect(() => draftFile({ ...input, mimeType: 'text/html' })).toThrow(DomainError)
    expect(draftFile({ ...input, mimeType: '' }).mimeType).toBe('')
    expect(draftFile({ ...input, mimeType: 'Image/PNG; charset=binary' }).mimeType).toBe('image/png')
  })

  it('ошибка указывает поле', () => {
    try {
      draftFile({ ...input, mimeType: 'text/html' })
      expect.unreachable('html не должен приниматься')
    } catch (error) {
      expect(error).toBeInstanceOf(DomainError)
      expect((error as DomainError).field).toBe('mimeType')
    }
  })

  it('знает список допустимых типов', () => {
    expect(isAllowedFileType('application/pdf')).toBe(true)
    expect(isAllowedFileType('application/zip')).toBe(false)
  })
})

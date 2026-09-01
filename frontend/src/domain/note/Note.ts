import { DomainError } from './errors'

/** Заметка на доске — то, чем обмениваются все участники. */
export interface Note {
  readonly id: string
  readonly text: string
  readonly x: number
  readonly y: number
  readonly color: string
  readonly author: string
  readonly createdAt: string
}

/** Заготовка новой заметки: идентификатор и время проставит сервер. */
export type NoteDraft = Omit<Note, 'id' | 'createdAt'>

export const NOTE_TEXT_MAX_LENGTH = 2000
export const BOARD_MIN = 0
export const BOARD_MAX = 10_000

const COLOR_PATTERN = /^#[0-9a-f]{6}$/

/**
 * Те же правила, что и на сервере. Дублирование намеренное: клиент
 * обязан сказать об ошибке до сетевого запроса, но последнее слово
 * всё равно за доменом бэкенда.
 */
export function draftNote(input: {
  text: string
  x: number
  y: number
  color: string
  author: string
}): NoteDraft {
  const text = input.text.trim()

  if (text === '') {
    throw new DomainError('Текст заметки не может быть пустым', 'text')
  }

  if (text.length > NOTE_TEXT_MAX_LENGTH) {
    throw new DomainError(`Текст заметки длиннее ${NOTE_TEXT_MAX_LENGTH} символов`, 'text')
  }

  const color = input.color.trim().toLowerCase()

  if (!COLOR_PATTERN.test(color)) {
    throw new DomainError('Цвет задаётся в формате #rrggbb', 'color')
  }

  return {
    text,
    color,
    author: input.author,
    x: clampToBoard(input.x, 'x'),
    y: clampToBoard(input.y, 'y'),
  }
}

export function clampToBoard(value: number, axis: 'x' | 'y'): number {
  if (!Number.isFinite(value)) {
    throw new DomainError(`Координата ${axis} должна быть числом`, axis)
  }

  return Math.min(BOARD_MAX, Math.max(BOARD_MIN, Math.round(value)))
}

import { DomainError } from '../../domain/note/errors'

/** Текст ошибки для человека: доменные — как есть, остальные — что нашлось. */
export function describeError(cause: unknown): string {
  if (cause instanceof DomainError) {
    return cause.message
  }

  return cause instanceof Error ? cause.message : 'Неизвестная ошибка'
}

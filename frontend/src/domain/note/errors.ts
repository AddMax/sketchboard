/**
 * Нарушение правила домена — в отличие от сетевой ошибки, показывается
 * пользователю рядом с полем, которое он может исправить.
 */
export class DomainError extends Error {
  constructor(
    message: string,
    readonly field: string = '',
  ) {
    super(message)
    this.name = 'DomainError'
  }
}

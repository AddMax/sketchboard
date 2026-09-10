import { DomainError } from '../../domain/note/errors'

/**
 * Общий вызов JSON API. Ответ 422 приходит от доменного слоя сервера,
 * поэтому превращаем его обратно в DomainError — UI показывает такую
 * ошибку как поправимую.
 */
export async function jsonRequest<T>(url: string, init?: RequestInit): Promise<T> {
  const response = await fetch(url, {
    headers: { 'Content-Type': 'application/json' },
    ...init,
  })

  if (!response.ok) {
    throw await toError(response)
  }

  return response.status === 204 ? (undefined as T) : ((await response.json()) as T)
}

async function toError(response: Response): Promise<Error> {
  const body: unknown = await response.json().catch(() => null)

  if (response.status === 422 && isValidationBody(body)) {
    const [field, message] = Object.entries(body.errors)[0]

    return new DomainError(message, field)
  }

  if (isErrorBody(body)) {
    return new Error(body.error)
  }

  return new Error(`${response.status} ${response.statusText}`)
}

function isValidationBody(body: unknown): body is { errors: Record<string, string> } {
  return (
    typeof body === 'object' &&
    body !== null &&
    'errors' in body &&
    typeof (body as { errors: unknown }).errors === 'object' &&
    (body as { errors: unknown }).errors !== null
  )
}

function isErrorBody(body: unknown): body is { error: string } {
  return (
    typeof body === 'object' &&
    body !== null &&
    'error' in body &&
    typeof (body as { error: unknown }).error === 'string'
  )
}

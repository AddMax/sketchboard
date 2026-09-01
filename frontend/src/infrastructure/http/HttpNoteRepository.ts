import type { NoteRepository } from '../../application/ports/NoteRepository'
import type { Note, NoteDraft } from '../../domain/note/Note'
import { DomainError } from '../../domain/note/errors'

/**
 * Адаптер порта заметок поверх REST API.
 *
 * Ответ 422 приходит от доменного слоя сервера, поэтому превращаем его
 * обратно в DomainError — UI показывает такую ошибку как поправимую.
 */
export class HttpNoteRepository implements NoteRepository {
  constructor(private readonly baseUrl: string) {}

  async list(limit = 200): Promise<Note[]> {
    const { items } = await this.request<{ items: Note[] }>(`/notes?limit=${limit}`)

    return items
  }

  create(draft: NoteDraft): Promise<Note> {
    return this.request<Note>('/notes', { method: 'POST', body: JSON.stringify(draft) })
  }

  move(id: string, x: number, y: number): Promise<Note> {
    return this.request<Note>(`/notes/${encodeURIComponent(id)}/position`, {
      method: 'PATCH',
      body: JSON.stringify({ x, y }),
    })
  }

  async remove(id: string): Promise<void> {
    await this.request<void>(`/notes/${encodeURIComponent(id)}`, { method: 'DELETE' })
  }

  private async request<T>(path: string, init?: RequestInit): Promise<T> {
    const response = await fetch(`${this.baseUrl}${path}`, {
      headers: { 'Content-Type': 'application/json' },
      ...init,
    })

    if (!response.ok) {
      throw await this.toError(response)
    }

    return response.status === 204 ? (undefined as T) : ((await response.json()) as T)
  }

  private async toError(response: Response): Promise<Error> {
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

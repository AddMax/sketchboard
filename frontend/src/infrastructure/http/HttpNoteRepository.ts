import type { NoteRepository } from '../../application/ports/NoteRepository'
import type { Note, NoteDraft } from '../../domain/note/Note'
import { jsonRequest } from './jsonRequest'

/** Адаптер порта заметок поверх REST API. */
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

  private request<T>(path: string, init?: RequestInit): Promise<T> {
    return jsonRequest<T>(`${this.baseUrl}${path}`, init)
  }
}

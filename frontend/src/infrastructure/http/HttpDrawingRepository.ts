import type { DrawingRepository } from '../../application/ports/DrawingRepository'
import type { DrawingElement } from '../../domain/drawing/Drawing'
import { jsonRequest } from './jsonRequest'

interface DrawingBody {
  noteId: string
  elements: DrawingElement[]
  updatedAt: string | null
}

/** Адаптер порта рисунков: GET/PUT одного JSON-документа на заметку. */
export class HttpDrawingRepository implements DrawingRepository {
  constructor(private readonly baseUrl: string) {}

  async load(noteId: string): Promise<DrawingElement[]> {
    const { elements } = await jsonRequest<DrawingBody>(this.url(noteId))

    return elements
  }

  async save(noteId: string, elements: DrawingElement[]): Promise<void> {
    await jsonRequest<DrawingBody>(this.url(noteId), {
      method: 'PUT',
      body: JSON.stringify({ elements }),
    })
  }

  private url(noteId: string): string {
    return `${this.baseUrl}/notes/${encodeURIComponent(noteId)}/drawing`
  }
}

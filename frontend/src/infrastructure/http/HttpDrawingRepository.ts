import type { DrawingRepository } from '../../application/ports/DrawingRepository'
import type { DrawingLine } from '../../domain/drawing/Drawing'
import { jsonRequest } from './jsonRequest'

interface DrawingBody {
  noteId: string
  lines: DrawingLine[]
  updatedAt: string | null
}

/** Адаптер порта рисунков: GET/PUT одного JSON-документа на заметку. */
export class HttpDrawingRepository implements DrawingRepository {
  constructor(private readonly baseUrl: string) {}

  async load(noteId: string): Promise<DrawingLine[]> {
    const { lines } = await jsonRequest<DrawingBody>(this.url(noteId))

    return lines
  }

  async save(noteId: string, lines: DrawingLine[]): Promise<void> {
    await jsonRequest<DrawingBody>(this.url(noteId), {
      method: 'PUT',
      body: JSON.stringify({ lines }),
    })
  }

  private url(noteId: string): string {
    return `${this.baseUrl}/notes/${encodeURIComponent(noteId)}/drawing`
  }
}

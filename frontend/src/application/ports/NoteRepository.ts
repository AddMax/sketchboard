import type { Note, NoteDraft } from '../../domain/note/Note'

/**
 * Порт доступа к заметкам. UI не знает, что за ним HTTP: в тестах на его
 * месте оказывается объект в памяти.
 */
export interface NoteRepository {
  list(limit?: number): Promise<Note[]>
  create(draft: NoteDraft): Promise<Note>
  move(id: string, x: number, y: number): Promise<Note>
  remove(id: string): Promise<void>
}

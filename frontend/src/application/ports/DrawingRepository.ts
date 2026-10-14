import type { DrawingElement } from '../../domain/drawing/Drawing'

/** Порт хранилища рисунков: рисунок читается и пишется целиком. */
export interface DrawingRepository {
  load(noteId: string): Promise<DrawingElement[]>
  save(noteId: string, elements: DrawingElement[]): Promise<void>
}

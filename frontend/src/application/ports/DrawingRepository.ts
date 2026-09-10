import type { DrawingLine } from '../../domain/drawing/Drawing'

/** Порт хранилища рисунков: рисунок читается и пишется целиком. */
export interface DrawingRepository {
  load(noteId: string): Promise<DrawingLine[]>
  save(noteId: string, lines: DrawingLine[]): Promise<void>
}

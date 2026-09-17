import type { DrawingRepository } from './ports/DrawingRepository'
import type { NoteRepository } from './ports/NoteRepository'
import type { RealtimeChannel } from './ports/RealtimeChannel'

/**
 * Адаптеры портов, из которых собираются сторы. Что за ними — HTTP и
 * Centrifugo или объекты в памяти для тестов — сторам безразлично.
 */
export interface Dependencies {
  readonly notes: NoteRepository
  readonly drawings: DrawingRepository
  readonly realtime: RealtimeChannel
}

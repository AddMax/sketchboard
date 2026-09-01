import type { BoardDependencies } from '../application/board/BoardDependencies'
import { config } from './config'
import { HttpNoteRepository } from './http/HttpNoteRepository'
import { WebSocketRealtimeChannel } from './realtime/WebSocketRealtimeChannel'

/**
 * Композиционный корень: единственное место, где выбираются конкретные
 * реализации портов. Всё остальное приложение видит только интерфейсы.
 */
export function createDependencies(): BoardDependencies {
  return {
    notes: new HttpNoteRepository(config.apiBase),
    realtime: new WebSocketRealtimeChannel(config.wsPath),
  }
}

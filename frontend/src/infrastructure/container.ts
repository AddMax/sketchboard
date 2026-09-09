import type { BoardDependencies } from '../application/board/BoardDependencies'
import { config, websocketUrl } from './config'
import { HttpNoteRepository } from './http/HttpNoteRepository'
import { HttpRealtimeAccessProvider } from './http/HttpRealtimeAccessProvider'
import { CentrifugoRealtimeChannel } from './realtime/CentrifugoRealtimeChannel'

/**
 * Композиционный корень: единственное место, где выбираются конкретные
 * реализации портов. Замена самописного WebSocket-сервера на Centrifugo
 * затронула только эту строку и сам адаптер.
 */
export function createDependencies(participant: string): BoardDependencies {
  return {
    notes: new HttpNoteRepository(config.apiBase),
    realtime: new CentrifugoRealtimeChannel(
      websocketUrl(),
      new HttpRealtimeAccessProvider(config.apiBase),
      participant,
    ),
  }
}

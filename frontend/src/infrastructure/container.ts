import { createStores } from '../application/Stores'
import type { Stores } from '../application/Stores'
import { config, websocketUrl } from './config'
import { HttpDrawingRepository } from './http/HttpDrawingRepository'
import { HttpNoteRepository } from './http/HttpNoteRepository'
import { HttpRealtimeAccessProvider } from './http/HttpRealtimeAccessProvider'
import { CentrifugoRealtimeChannel } from './realtime/CentrifugoRealtimeChannel'

/**
 * Композиционный корень: единственное место, где выбираются конкретные
 * реализации портов и из них собираются сторы. Замена самописного
 * WebSocket-сервера на Centrifugo затронула только эту строку и сам адаптер.
 */
export function createAppStores(participant: string): Stores {
  return createStores({
    notes: new HttpNoteRepository(config.apiBase),
    drawings: new HttpDrawingRepository(config.apiBase),
    realtime: new CentrifugoRealtimeChannel(
      websocketUrl(),
      new HttpRealtimeAccessProvider(config.apiBase),
      participant,
    ),
  })
}

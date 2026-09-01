import type { ConnectionState, RealtimeEvent } from '../../domain/realtime/RealtimeEvent'

export interface RealtimeHandlers {
  onEvent(event: RealtimeEvent): void
  onStateChange(state: ConnectionState): void
}

/**
 * Порт realtime-канала. Реализация отвечает за транспорт и переподключение,
 * сценарий приложения — только за реакцию на события.
 */
export interface RealtimeChannel {
  /** @returns функция отключения */
  connect(handlers: RealtimeHandlers): () => void
  send(event: string, payload: unknown): void
}

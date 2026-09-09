export interface RealtimeCredentials {
  readonly token: string
  readonly channel: string
  readonly expiresAt: string
}

/**
 * Порт получения доступа к каналу. Кто и по каким правилам выдаёт
 * учётные данные — забота бэкенда, клиенту важен сам факт доступа.
 */
export interface RealtimeAccessProvider {
  credentialsFor(participant: string): Promise<RealtimeCredentials>
}

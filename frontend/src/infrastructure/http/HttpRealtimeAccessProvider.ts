import type {
  RealtimeAccessProvider,
  RealtimeCredentials,
} from '../../application/ports/RealtimeAccessProvider'

/** Учётные данные подключения выдаёт то же API, что и заметки. */
export class HttpRealtimeAccessProvider implements RealtimeAccessProvider {
  constructor(private readonly baseUrl: string) {}

  async credentialsFor(participant: string): Promise<RealtimeCredentials> {
    const response = await fetch(
      `${this.baseUrl}/realtime/access?participant=${encodeURIComponent(participant)}`,
      { headers: { Accept: 'application/json' } },
    )

    if (!response.ok) {
      throw new Error(`Не удалось получить доступ к каналу: ${response.status}`)
    }

    return (await response.json()) as RealtimeCredentials
  }
}

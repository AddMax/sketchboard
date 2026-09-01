import { Centrifuge } from 'centrifuge'
import type { PublicationContext, Subscription } from 'centrifuge'
import type { RealtimeAccessProvider } from '../../application/ports/RealtimeAccessProvider'
import type { RealtimeChannel, RealtimeHandlers } from '../../application/ports/RealtimeChannel'
import { parseRealtimeEvent } from '../../domain/realtime/RealtimeEvent'
import type { RealtimeEvent } from '../../domain/realtime/RealtimeEvent'

/**
 * Адаптер realtime-канала поверх Centrifugo.
 *
 * Реализует тот же порт, что и прежний самописный WebSocket-клиент:
 * приложение и домен об этой замене не знают.
 *
 * Centrifugo сам переподключается, восстанавливает подписку и догоняет
 * пропущенные сообщения из истории канала — своего кода для этого больше нет.
 */
export class CentrifugoRealtimeChannel implements RealtimeChannel {
  private subscription: Subscription | null = null
  private clientId: string | null = null

  constructor(
    private readonly url: string,
    private readonly access: RealtimeAccessProvider,
    private readonly participant: string,
  ) {}

  connect(handlers: RealtimeHandlers): () => void {
    const centrifuge = new Centrifuge(this.url, {
      // Токен короткоживущий: библиотека сама придёт за новым, когда
      // текущий истечёт или соединение восстановится
      getToken: async () => (await this.access.credentialsFor(this.participant)).token,
    })

    handlers.onStateChange('connecting')

    centrifuge.on('connected', (ctx) => {
      this.clientId = ctx.client
      handlers.onStateChange('online')
    })
    centrifuge.on('connecting', () => handlers.onStateChange('connecting'))
    centrifuge.on('disconnected', () => handlers.onStateChange('offline'))
    centrifuge.on('error', (ctx) => console.warn('Centrifugo:', ctx.error.message))

    void this.subscribe(centrifuge, handlers).catch((cause: unknown) => {
      console.error('Не удалось подписаться на канал доски:', cause)
      handlers.onStateChange('offline')
    })

    centrifuge.connect()

    return () => {
      this.subscription?.unsubscribe()
      this.subscription = null
      centrifuge.disconnect()
      this.clientId = null
    }
  }

  send(event: string, payload: unknown): void {
    // Эфемерные события (курсор, ping) идут в канал напрямую, минуя бэкенд
    void this.subscription
      ?.publish({ event, payload, at: new Date().toISOString() })
      .catch((cause: unknown) => console.warn('Не удалось отправить событие:', cause))
  }

  private async subscribe(centrifuge: Centrifuge, handlers: RealtimeHandlers): Promise<void> {
    const { channel } = await this.access.credentialsFor(this.participant)
    const subscription = centrifuge.newSubscription(channel)
    this.subscription = subscription

    subscription.on('publication', (ctx: PublicationContext) => {
      // Своё же эфемерное событие возвращать в приложение незачем:
      // серверные публикации приходят без информации о клиенте
      if (ctx.info?.client !== undefined && ctx.info.client === this.clientId) {
        return
      }

      const event = toRealtimeEvent(ctx.data)

      if (event === null) {
        console.warn('Неизвестное сообщение канала:', ctx.data)

        return
      }

      handlers.onEvent(event)
    })

    // Присутствие считает сам Centrifugo — достаточно спросить его
    // при подписке и при каждом входе-выходе участника
    const refreshPresence = () => {
      void subscription
        .presenceStats()
        .then((stats) => {
          handlers.onEvent({
            event: 'presence',
            payload: { clients: stats.numClients },
            at: new Date().toISOString(),
          })
        })
        .catch(() => undefined)
    }

    subscription.on('subscribed', refreshPresence)
    subscription.on('join', refreshPresence)
    subscription.on('leave', refreshPresence)

    subscription.subscribe()
  }
}

/** Centrifugo отдаёт уже разобранный JSON, но его форму нужно проверить. */
function toRealtimeEvent(data: unknown): RealtimeEvent | null {
  try {
    return parseRealtimeEvent(JSON.stringify(data))
  } catch {
    return null
  }
}

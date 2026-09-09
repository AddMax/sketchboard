import type { ConnectionState } from '../../domain/realtime/RealtimeEvent'

const LABELS: Record<ConnectionState, string> = {
  online: 'realtime подключён',
  connecting: 'подключение…',
  offline: 'нет связи',
}

export function ConnectionStatus({
  state,
  clients,
  author,
  onPing,
}: {
  state: ConnectionState
  clients: number
  author: string
  onPing: () => void
}) {
  return (
    <div className="status">
      <span className={`status__dot status__dot--${state}`} />
      <span>{LABELS[state]}</span>

      {clients > 0 && <span className="status__badge">клиентов: {clients}</span>}
      <span className="status__badge">вы: {author}</span>

      <button type="button" className="status__ping" onClick={onPing} disabled={state !== 'online'}>
        ping
      </button>
    </div>
  )
}

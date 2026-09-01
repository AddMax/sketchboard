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
      <span className={`dot dot--${state}`} />
      <span>{LABELS[state]}</span>
      {clients > 0 && <span className="badge">клиентов: {clients}</span>}
      <span className="badge">вы: {author}</span>
      <button type="button" onClick={onPing} disabled={state !== 'online'}>
        ping
      </button>
    </div>
  )
}

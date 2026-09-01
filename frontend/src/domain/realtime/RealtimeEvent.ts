import type { Note } from '../note/Note'

/** Сообщения, которые сервер шлёт в WebSocket-канал доски. */
export type RealtimeEvent =
  | { event: 'note.created'; payload: Note; at: string }
  | { event: 'note.moved'; payload: Note; at: string }
  | { event: 'note.deleted'; payload: { id: string }; at: string }
  | { event: 'presence'; payload: { clients: number }; at: string }
  | { event: 'cursor'; payload: { author: string; x: number; y: number }; at: string }

export type ConnectionState = 'connecting' | 'online' | 'offline'

const KNOWN_EVENTS = ['note.created', 'note.moved', 'note.deleted', 'presence', 'cursor'] as const

/**
 * Из сокета приходит текст, а не тип: проверяем форму до того, как
 * пустить сообщение в доменную логику.
 */
export function parseRealtimeEvent(raw: string): RealtimeEvent | null {
  let candidate: unknown

  try {
    candidate = JSON.parse(raw)
  } catch {
    return null
  }

  if (typeof candidate !== 'object' || candidate === null) {
    return null
  }

  const event = (candidate as { event?: unknown }).event

  return typeof event === 'string' && (KNOWN_EVENTS as readonly string[]).includes(event)
    ? (candidate as RealtimeEvent)
    : null
}

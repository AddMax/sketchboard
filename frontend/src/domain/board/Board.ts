import type { Note } from '../note/Note'
import type { RealtimeEvent } from '../realtime/RealtimeEvent'

/** Состояние доски: заметки, свежие первыми. */
export type Board = readonly Note[]

export const emptyBoard: Board = []

/**
 * Как событие меняет доску — центральное правило клиента, и оно намеренно
 * оформлено чистой функцией: тестируется без React, сети и сокетов.
 *
 * Повторно пришедшее событие (переподключение, дубль от сервера) не должно
 * ломать доску, поэтому все ветки идемпотентны.
 */
export function applyRealtimeEvent(board: Board, event: RealtimeEvent): Board {
  switch (event.event) {
    case 'note.created':
      return board.some((note) => note.id === event.payload.id)
        ? board
        : sortByNewest([event.payload, ...board])

    case 'note.moved':
      return board.map((note) => (note.id === event.payload.id ? event.payload : note))

    case 'note.deleted':
      return board.filter((note) => note.id !== event.payload.id)

    // Присутствие и курсоры доску не меняют
    case 'presence':
    case 'cursor':
      return board
  }
}

export function replaceBoard(notes: readonly Note[]): Board {
  return sortByNewest([...notes])
}

function sortByNewest(notes: Note[]): Board {
  return notes.sort((a, b) => b.createdAt.localeCompare(a.createdAt))
}

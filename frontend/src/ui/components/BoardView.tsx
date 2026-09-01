import type { Board } from '../../domain/board/Board'
import { NoteCard } from './NoteCard'

export function BoardView({ board, onRemove }: { board: Board; onRemove: (id: string) => void }) {
  if (board.length === 0) {
    return (
      <main className="board">
        <p className="empty">Заметок пока нет — добавьте первую.</p>
      </main>
    )
  }

  return (
    <main className="board">
      {board.map((note) => (
        <NoteCard key={note.id} note={note} onRemove={onRemove} />
      ))}
    </main>
  )
}

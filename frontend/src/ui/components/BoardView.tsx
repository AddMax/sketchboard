import type { Board } from '../../domain/board/Board'
import { NoteCard } from './NoteCard'

export function BoardView({ board, onRemove }: { board: Board; onRemove: (id: string) => void }) {
  if (board.length === 0) {
    return <p className="board__empty">Заметок пока нет — добавьте первую.</p>
  }

  return (
    <section className="board">
      {board.map((note) => (
        <NoteCard key={note.id} note={note} onRemove={onRemove} />
      ))}
    </section>
  )
}

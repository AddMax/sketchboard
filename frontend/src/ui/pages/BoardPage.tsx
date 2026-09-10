import type { BoardState } from '../../application/board/useBoard'
import { BoardView } from '../components/BoardView'
import { Composer } from '../components/Composer'

const COLORS = ['#ffd166', '#06d6a0', '#118ab2', '#ef476f', '#c8b6ff']

export function BoardPage({ state, author }: { state: BoardState; author: string }) {
  const { board, error, addNote, removeNote, dismissError } = state

  return (
    <main className="content">
      <Composer
        onSubmit={(text) =>
          addNote({ text, author, color: COLORS[Math.floor(Math.random() * COLORS.length)] })
        }
      />

      {error !== null && (
        <p className="error" onClick={dismissError} role="alert">
          {error}
        </p>
      )}

      <BoardView board={board} onRemove={removeNote} />
    </main>
  )
}

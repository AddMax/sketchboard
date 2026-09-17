import { observer } from 'mobx-react-lite'
import type { BoardStore } from '../../application/board/BoardStore'
import { BoardView } from '../components/BoardView'
import { Composer } from '../components/Composer'

const COLORS = ['#ffd166', '#06d6a0', '#118ab2', '#ef476f', '#c8b6ff']

export const BoardPage = observer(function BoardPage({
  board,
  author,
}: {
  board: BoardStore
  author: string
}) {
  return (
    <main className="content">
      <Composer
        onSubmit={(text) =>
          board.addNote({ text, author, color: COLORS[Math.floor(Math.random() * COLORS.length)] })
        }
      />

      {board.error !== null && (
        <p className="error" onClick={board.dismissError} role="alert">
          {board.error}
        </p>
      )}

      <BoardView board={board.notes} onRemove={board.removeNote} />
    </main>
  )
})

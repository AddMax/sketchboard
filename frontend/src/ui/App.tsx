import { useBoard } from '../application/board/useBoard'
import { ConnectionStatus } from './components/ConnectionStatus'
import { BoardPage } from './pages/BoardPage'
import { DrawingPage } from './pages/DrawingPage'
import { Link } from './routing/Link'
import { BOARD_PATH } from './routing/routes'
import { useRoute } from './routing/useRoute'

export function App({ author }: { author: string }) {
  const state = useBoard()
  const route = useRoute()

  return (
    <div className="app">
      <header className="topbar">
        <h1 className="topbar__title">
          <Link className="topbar__home" to={BOARD_PATH}>
            Sketchboard
          </Link>
        </h1>
        <ConnectionStatus
          state={state.connection}
          clients={state.clients}
          author={author}
          onPing={() => state.ping(author)}
        />
      </header>

      {route.kind === 'draw' ? (
        <DrawingPage
          noteId={route.noteId}
          note={state.board.find((note) => note.id === route.noteId)}
          loaded={state.loaded}
        />
      ) : (
        <BoardPage state={state} author={author} />
      )}
    </div>
  )
}

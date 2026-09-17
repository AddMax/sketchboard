import { observer } from 'mobx-react-lite'
import { useEffect } from 'react'
import { useStores } from '../application/Stores'
import { ConnectionStatus } from './components/ConnectionStatus'
import { BoardPage } from './pages/BoardPage'
import { DrawingPage } from './pages/DrawingPage'
import { Link } from './routing/Link'
import { BOARD_PATH } from './routing/routes'
import { useRouter } from './routing/RouterStore'

export const App = observer(function App({ author }: { author: string }) {
  const { board } = useStores()
  const router = useRouter()

  // Доска и роутер живут, пока смонтировано приложение: подписки снимаются вместе с ним
  useEffect(() => board.start(), [board])
  useEffect(() => router.start(), [router])

  const { route } = router

  return (
    <div className="app">
      <header className="topbar">
        <h1 className="topbar__title">
          <Link className="topbar__home" to={BOARD_PATH}>
            Sketchboard
          </Link>
        </h1>
        <ConnectionStatus
          state={board.connection}
          clients={board.clients}
          author={author}
          onPing={() => board.ping(author)}
        />
      </header>

      {route.kind === 'draw' ? (
        <DrawingPage
          noteId={route.noteId}
          note={board.notes.find((note) => note.id === route.noteId)}
          loaded={board.loaded}
        />
      ) : (
        <BoardPage board={board} author={author} />
      )}
    </div>
  )
})

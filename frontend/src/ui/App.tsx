import { useBoard } from '../application/board/useBoard'
import { BoardView } from './components/BoardView'
import { Composer } from './components/Composer'
import { ConnectionStatus } from './components/ConnectionStatus'

const COLORS = ['#ffd166', '#06d6a0', '#118ab2', '#ef476f', '#c8b6ff']

export function App({ author }: { author: string }) {
  const { board, connection, clients, error, addNote, removeNote, ping, dismissError } = useBoard()

  return (
    <div className="app">
      <header className="topbar">
        <h1 className="topbar__title">Sketchboard</h1>
        <ConnectionStatus
          state={connection}
          clients={clients}
          author={author}
          onPing={() => ping(author)}
        />
      </header>

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
    </div>
  )
}

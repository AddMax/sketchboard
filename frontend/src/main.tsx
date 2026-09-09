import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { BoardDependenciesProvider } from './application/board/BoardDependencies'
import { createDependencies } from './infrastructure/container'
import { App } from './ui/App'
import { currentAuthor } from './ui/identity'
import './ui/styles/index.scss'

const container = document.getElementById('root')

if (container === null) {
  throw new Error('Не найден корневой элемент #root')
}

// Имя участника нужно уже при подключении к каналу, поэтому определяем
// его здесь и передаём и в адаптеры, и в интерфейс
const participant = currentAuthor()
const dependencies = createDependencies(participant)

createRoot(container).render(
  <StrictMode>
    <BoardDependenciesProvider dependencies={dependencies}>
      <App author={participant} />
    </BoardDependenciesProvider>
  </StrictMode>,
)

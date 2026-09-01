import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { BoardDependenciesProvider } from './application/board/BoardDependencies'
import { createDependencies } from './infrastructure/container'
import { App } from './ui/App'
import './ui/styles.css'

const container = document.getElementById('root')

if (container === null) {
  throw new Error('Не найден корневой элемент #root')
}

// Адаптеры создаются один раз и передаются вниз через контекст
const dependencies = createDependencies()

createRoot(container).render(
  <StrictMode>
    <BoardDependenciesProvider dependencies={dependencies}>
      <App />
    </BoardDependenciesProvider>
  </StrictMode>,
)

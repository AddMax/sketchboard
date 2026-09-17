import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { StoresProvider } from './application/Stores'
import { createAppStores } from './infrastructure/container'
import { App } from './ui/App'
import { currentAuthor } from './ui/identity'
import { RouterProvider, RouterStore } from './ui/routing/RouterStore'
import './ui/styles/index.scss'

const container = document.getElementById('root')

if (container === null) {
  throw new Error('Не найден корневой элемент #root')
}

// Имя участника нужно уже при подключении к каналу, поэтому определяем
// его здесь и передаём и в адаптеры, и в интерфейс
const participant = currentAuthor()
const stores = createAppStores(participant)
const router = new RouterStore()

createRoot(container).render(
  <StrictMode>
    <StoresProvider stores={stores}>
      <RouterProvider value={router}>
        <App author={participant} />
      </RouterProvider>
    </StoresProvider>
  </StrictMode>,
)

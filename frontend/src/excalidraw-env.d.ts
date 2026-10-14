// Excalidraw читает путь к шрифтам из window, но свой global.d.ts из пакета не экспортирует
interface Window {
  EXCALIDRAW_ASSET_PATH: string | string[] | undefined
}

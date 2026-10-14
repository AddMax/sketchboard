import { Excalidraw, getSceneVersion } from '@excalidraw/excalidraw'
import '@excalidraw/excalidraw/index.css'
import type { ExcalidrawElement, OrderedExcalidrawElement } from '@excalidraw/excalidraw/element/types'
import type { ExcalidrawInitialDataState, ExcalidrawProps } from '@excalidraw/excalidraw/types'
import { observer } from 'mobx-react-lite'
import { useMemo, useRef } from 'react'
import type { DrawingElement } from '../../../domain/drawing/Drawing'
import { liveElements } from '../../../domain/drawing/Drawing'
import { useColorScheme } from '../../colorScheme'
import { SaveStatus } from './SaveStatus'
import type { Savable } from './SaveStatus'

/** Что редактору нужно от документа — этому интерфейсу соответствует DrawingStore. */
export interface EditorDocument extends Savable {
  readonly elements: readonly DrawingElement[]
  replaceElements(next: DrawingElement[]): void
}

/**
 * Сцену не загружают из файла и не сохраняют в него — она живёт на сервере;
 * тема следует за системной; картинки выключены, потому что их файлы
 * хранятся отдельно от сцены, а сервер файлы не принимает.
 */
const UI_OPTIONS: ExcalidrawProps['UIOptions'] = {
  canvasActions: {
    loadScene: false,
    saveToActiveFile: false,
    toggleTheme: false,
    changeViewBackgroundColor: false,
  },
  tools: { image: false },
}

/**
 * Редактор рисунка на Excalidraw. Сценой владеет редактор: стор получает от
 * него снимки при каждом изменении и сам решает, когда отправить их на сервер.
 */
export const DrawingEditor = observer(function DrawingEditor({
  drawing,
  name,
}: {
  drawing: EditorDocument
  name: string
}) {
  const scheme = useColorScheme()

  // Начальная сцена читается один раз, при монтировании: дальше источник
  // правды — редактор, а стор хранит то, что он прислал
  const initialData = useMemo<ExcalidrawInitialDataState>(
    () => ({
      // Наш тип описывает лишь поля, общие с сервером; полную форму знает редактор
      elements: drawing.elements as unknown as ExcalidrawElement[],
      scrollToContent: true,
    }),
    [],
  )

  // onChange срабатывает на каждое движение указателя и смену состояния
  // интерфейса; сохранять есть смысл только когда изменилась сама сцена
  const sceneVersion = useRef(getSceneVersion(initialData.elements ?? []))

  const onChange = (elements: readonly OrderedExcalidrawElement[]) => {
    const version = getSceneVersion(elements)

    if (version === sceneVersion.current) return

    sceneVersion.current = version
    drawing.replaceElements(liveElements(elements))
  }

  return (
    <div className="editor">
      <Excalidraw
        initialData={initialData}
        onChange={onChange}
        theme={scheme.dark ? 'dark' : 'light'}
        langCode="ru-RU"
        name={name}
        UIOptions={UI_OPTIONS}
        renderTopRightUI={() => <SaveStatus document={drawing} />}
      />
    </div>
  )
})

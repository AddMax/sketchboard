# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

Sketchboard — realtime-доска заметок. Скелет проекта: Symfony 8.1 на PHP 8.5,
PostgreSQL 18, Redis 8, React 19 + MobX 7 + Excalidraw, Centrifugo 6, всё за
nginx в Docker.
Описание для людей — в `README.md`, исходная постановка задачи — в `promt.md`;
здесь то, что экономит время при работе с кодом.

## Команды

Всё выполняется в контейнерах, на хосте ничего не установлено.

```bash
make init                  # первый запуск: .env, секреты, сборка, старт, миграции
make up / down / destroy   # destroy сносит и данные
make logs                  # логи всех сервисов
make test                  # тесты: 41 на бэке, 31 на фронте (test-back / test-front — по отдельности)
make health                # /api/health: статусы PostgreSQL, Redis и Centrifugo
make schema-validate       # сверить XML-маппинг Doctrine со схемой БД
make migration / migrate   # создать миграцию из маппинга / применить
make cache-clear
make composer c="require ..."   # make npm c="install ..."
make xdebug-on / xdebug-off    # Xdebug выключен по умолчанию; переключение пересоздаёт php
make lint                  # бэкенд: cs-check + stan + rector-check, ничего не меняет
make rector && make cs     # применить рефакторинг и стиль — именно в этом порядке
```

Профайлер Symfony: <http://localhost:8080/_profiler/>, у каждого ответа API есть
`X-Debug-Token-Link`. Как настроить IDE под Xdebug — в README, раздел «Отладка».
Документация API: <http://localhost:8080/api/doc> (Swagger UI), `/api/doc.json` — OpenAPI.

Тесты лежат в `backend/tests/Unit/` (PHPUnit 12, атрибут `#[DataProvider]`)
и `frontend/tests/*.test.ts` (Vitest; сторы проверяются с адаптерами в памяти
и фальшивыми таймерами, без React и jsdom; Excalidraw в тестах не участвует,
домен и стор о нём не знают). Один тест:

```bash
docker compose exec -T php vendor/bin/phpunit --filter testMovingToSamePlaceRecordsNoEvent
docker compose exec -T frontend npx vitest run -t "не дублирует"
docker compose exec -T frontend npm run typecheck
```

Проверка живого стека: `make health` или `curl -s localhost:8080/api/health` —
отвечает статусами PostgreSQL, Redis и Centrifugo сразу; любой сбой — 503.

## Архитектура

Обе части разложены по слоям DDD, зависимости направлены строго внутрь:
UI → Application → Domain, а Infrastructure подключается к Domain и Application
только через порты.

Практическое следствие, которое стоит сохранять: **смена транспорта не должна
трогать домен**. Переход с самописного WebSocket-сервера на Centrifugo затронул
ровно два адаптера по обе стороны, а все 32 теста домена прошли без правок.

### Бэкенд (`backend/src/`)

| Слой | Правило |
| --- | --- |
| `Domain/` | чистый PHP. Ни `use Symfony\…`, ни `use Doctrine\…`, ни атрибутов ORM |
| `Application/` | сценарии «команда + хендлер», порты, read-модели |
| `Infrastructure/` | единственное место, где живут Doctrine, Centrifugo, HTTP-клиент |
| `UI/` | разобрать запрос → вызвать хендлер → отдать JSON. **Один маршрут — один класс** с `__invoke`, каталоги по контексту: `Controller/Note/`, `Controller/Drawing/`, `Controller/Realtime/`. Тело запроса читает `Http/JsonPayload` (`string`, `int`, `list`…) без проверок — что внутри, решает домен |

- Инварианты стерегут объекты-значения (`NoteText`, `Position`, `Color`, `Author`),
  а не валидатор: `symfony/validator` намеренно не установлен. VO бросают
  `Domain\Shared\InvalidArgument`, а `DomainExceptionListener` превращает его в 422,
  `NoteNotFound` — в 404. Контроллеры не ловят исключения сами.
- Агрегат копит события (`recordThat`) и отдаёт их через `releaseEvents()`.
  Публикует их **хендлер после успешной записи**, не репозиторий и не контроллер.
  В `DeleteNoteHandler` события снимаются до `remove()` — после удаления объект уже не наш.
- Агрегатов два: `Note` и `Drawing` (рисунок заметки, ключ общий с заметкой).
  Рисунок — сцена Excalidraw: `Elements` из `Element` в порядке наложения.
  `Element` хранит объект редактора **как есть** (`toArray()` возвращает то,
  что пришло) и проверяет только `id` (строка ≤ 64), `type` (строчные
  латинские, не `image` — файлы картинок сервер не принимает), `isDeleted`
  (true → 422: удалённые элементы клиент отбрасывает до отправки) и
  `points` (список ≤ 20 000). Лимит сцены — 5 000 элементов. Домен
  **не** разбирает геометрию и стиль: модель принадлежит Excalidraw, новый
  тип элемента в редакторе не требует правок на сервере. Всё лежит одним
  JSON-документом (`ElementsType`, колонка `elements`) и всегда заменяется
  целиком — клиент шлёт полный список, а не дельту. Событий рисунок не
  порождает и по realtime-каналу не летает: может весить сотни килобайт.
  Связь между агрегатами держит `DeleteNoteHandler`, а не внешний ключ.
  Ошибки всех элементов приходят с полем `elements`. Рисунки прежнего
  формата (штрихи `line` и фигуры `shape` своего полотна) переведены
  миграцией `Version20261014120000` в `freedraw`, `rectangle`, `ellipse` и
  замкнутую `line` (треугольник); обратного пути нет.
- Маппинг Doctrine — XML в `Infrastructure/Persistence/Doctrine/Mapping/`.
  Имя файла кодирует FQCN относительно префикса `App\Domain`:
  `App\Domain\Note\Note` → `Note.Note.orm.xml`, embeddable `Position` →
  `Note.ValueObject.Position.orm.xml`. Ошибиться легко, симптом —
  «Class 'App\Domain\Note' does not exist». Таблиц две: `notes` и `drawings`,
  без внешнего ключа между ними.
- Документация API — атрибуты `OpenApi\Attributes` (`OA\Get`, `OA\RequestBody`,
  `OA\Response`…) на `__invoke` контроллера, тег `OA\Tag` на классе. Путь и метод
  Nelmio берёт из `#[Route]`, дублировать не нужно. Переиспользуемые схемы
  (`Note`, `Drawing`, `DrawingElement` — объект с `id`, `type` и
  `additionalProperties`, `ValidationError`…) — в `config/packages/nelmio_api_doc.yaml`,
  ссылки через `ref: '#/components/schemas/…'`; на read-моделях атрибутов нет,
  чтобы Application не зависел от библиотеки документации. Меняя `NoteView` или
  `DrawingView`, правьте и схему. `OA\Schema` на методе контроллера нельзя —
  Nelmio падает с «root annotation … is not allowed».
- Форма realtime-сообщения (`{event, payload, at}`) описана в
  `Infrastructure/Realtime/DomainEventSerializer` — это контракт с фронтендом,
  а не часть домена. Меняя её, правьте `frontend/src/domain/realtime/RealtimeEvent.ts`.

### Фронтенд (`frontend/src/`)

Те же слои: `domain/` (правила заметки, чистая функция `applyRealtimeEvent`,
тип элемента сцены и `liveElements`), `application/` (порты
`NoteRepository`, `DrawingRepository`, `RealtimeChannel`, `RealtimeAccessProvider`
и MobX-сторы `BoardStore`, `DrawingStore`; `Stores.tsx` собирает их из адаптеров
и раздаёт через контекст), `infrastructure/` (адаптеры + `container.ts` —
единственное место, где выбираются реализации), `ui/` (компоненты, страницы,
маршрутизация).

**Состояние — только в MobX.** Прикладное состояние живёт в сторах
(`makeAutoObservable` с `autoBind`), компоненты обёрнуты в `observer` и
состояние не меняют — вызывают действия стора. Локальное состояние
формы или панели (черновик текста, системная цветовая схема) — `useLocalObservable`,
не `useState`; `useState` остаётся только для хранения экземпляра стора
(`DrawingBoard`). Текущий экран — `ui/routing/RouterStore` (`route`, `navigate`).
Исключение — сцена Excalidraw: ею владеет сам редактор, стор хранит снимки.

- Доска одна на приложение, стор рисунка — на страницу: `Stores.openDrawing(noteId)`
  создаёт новый, `DrawingBoard` держит его в `useState`, а `key={noteId}` выше
  гарантирует новый стор и новый редактор при смене заметки. При размонтировании `dispose()`
  отправляет отложенное сохранение сразу.
- Массивы заметок и элементов рисунка аннотированы `observableRef`: доска и рисунок
  заменяются целиком, а глубокое наблюдение за десятками тысяч точек
  оборачивало бы их в прокси. Меняя список, присваивайте новый массив.
- После `await` действие MobX уже закончилось — изменения оборачивать в
  `runInAction` (иначе предупреждение о strict-mode в консоли).
- **MobX 7 переименовал пространственные аннотации**: `observable.ref` →
  `observableRef`, `observable.shallow` → `observableShallow`, `action.bound` →
  `actionBound`. Старые формы не проходят typecheck и падают в рантайме
  с «Invalid annotation».

Интерфейс — свои компоненты; библиотеки готовых компонентов нет. Экранов два —
доска заметок (`/`) и доска для рисования по заметке (`/notes/{id}/draw`), —
и маршрутизатор свой, на History API: чистый разбор пути в `ui/routing/routes.ts`,
хук `useRoute` и `Link`, который перехватывает только обычный клик (новая
вкладка остаётся за браузером). Новый экран — вариант в `Route`, ветка в
`parseRoute` и компонент в `ui/pages/`. Vite dev-сервер сам отдаёт `index.html`
на неизвестные пути, отдельной настройки nginx не нужно.

Стили — **только SCSS** (`sass-embedded`), точка входа `ui/styles/index.scss`:

```
ui/styles/
  _tokens.scss     размеры и радиусы переменными Sass, цвета — переменными CSS
  _mixins.scss     surface, control, disabled
  _base.scss       сброс и типографика
  blocks/          по блоку интерфейса: layout, status, composer, board, note, drawing, editor
```

Разделение внутри `_tokens.scss` не случайно: размеры и радиусы нужны
препроцессору на этапе сборки (`minmax(t.$board-min-column, 1fr)`), а цвета
подменяет тёмная схема в рантайме — поэтому они переменными CSS, и правила
не дублируются под `prefers-color-scheme`.

Именование блочное: `.note`, `.note__text`, `.status__dot--online`. Новый блок —
новый partial в `blocks/` и строка `@use` в `index.scss`; общие величины идут
в `_tokens.scss`, повторяющиеся наборы свойств — в `_mixins.scss`.

- Команды не меняют состояние доски напрямую: изменение возвращается событием
  из канала, поэтому автор и остальные обновляются одним и тем же путём.
- Правила заметки продублированы с бэкендом намеренно (ответить до сетевого
  запроса), но последнее слово за сервером. Меняя инвариант, правьте оба места.
- Редактор `ui/components/drawing/DrawingEditor.tsx` — обёртка над
  `@excalidraw/excalidraw` (0.18, React 19). `initialData` читается один раз
  при монтировании (`useMemo` с пустыми зависимостями), дальше сценой владеет
  Excalidraw. `onChange` срабатывает на каждое движение указателя и смену
  `appState`, поэтому снимок уходит в стор только когда изменился
  `getSceneVersion(elements)` (последняя версия — в `useRef`); перед
  отправкой `liveElements` отбрасывает `isDeleted` — сервер такие отвергает.
  Редактору нужен объект с интерфейсом `EditorDocument` (`elements`,
  `saveStatus`, `replaceElements`, `retrySave`) — ему соответствует
  `DrawingStore`. Сохранение живёт в сторе: `application/shared/debounce.ts`
  (1,5 с после последнего изменения, flush в `dispose()`). Статус
  сохранения — `SaveStatus`, отдельный `observer`, который Excalidraw
  рисует через `renderTopRightUI` (функцию он вызывает сам, поэтому
  компонент наблюдает за стором самостоятельно). `UIOptions` выключают
  загрузку сцены из файла, смену фона, переключатель темы и картинки:
  ничего из этого сервер не хранит. Тема — проп `theme` из
  `ui/colorScheme.ts`, локаль — `langCode="ru-RU"`. Тип `DrawingElement`
  в домене описывает только общие с сервером поля, в `initialData`
  элементы приводятся через `as unknown as ExcalidrawElement[]` — это
  единственное место, где два типа встречаются. Excalidraw требует от
  родителя явной высоты: `.editor { flex: 1; min-height: 0 }`. При первом
  открытии сконвертированного рисунка редактор может нормализовать сцену
  (например, выбросить штрих нулевого размера) и сохранить её один раз.

### Realtime

php-fpm живёт один запрос и соединения держать не может — их держит Centrifugo.
Бэкенд публикует события его серверным HTTP API (`CentrifugoApi`), браузер
получает JWT на `GET /api/realtime/access` и подключается к `/ws`, который nginx
проксирует в `/connection/websocket`.

- Presence, история канала и подписки живут в Redis (engine Centrifugo) — том же,
  что и кэш Symfony.
- Эфемерные события (курсор, ping) клиент публикует в канал сам, минуя бэкенд.
  Своё же сообщение возвращается автору: адаптер отбрасывает его по `info.client`,
  который Centrifugo проставляет клиентским публикациям и **не** проставляет серверным.
- Недоступность Centrifugo не роняет запрос: данные уже в БД, ошибка только в лог.

## Ловушки окружения

Найдены опытным путём — на них легко потерять время заново.

- **MTU под VPN.** Внешний трафик машины идёт через WireGuard (MTU 1420). С
  дефолтным MTU 1500 загрузки внутри контейнеров (apk, composer, npm) **зависают
  молча**, при работающем DNS. Отсюда `driver_opts` у сети и `network: host` у
  сборок. `make init` подставляет MTU интерфейса по умолчанию.
- **nginx кэширует IP апстримов** при старте и отвечает 502, когда контейнер
  перезапустился. Поэтому `resolver 127.0.0.11` и `proxy_pass` через переменную.
  После правки конфига нужен `docker compose exec nginx nginx -s reload` —
  сам он изменения не подхватит.
- **PostgreSQL 18** хранит данные в `/var/lib/postgresql`, а не `.../data`.
  Со старым путём контейнер не стартует.
- **`docker-php-ext-install` с несколькими расширениями разом** падает на гонке
  за каталог `modules/`. В Dockerfile они ставятся по одному, в цикле.
- **Named volume для `node_modules`** наследует права точки монтирования из
  образа — каталог создан в Dockerfile с нужным UID, иначе npm падает с EACCES.
- **Шрифты Excalidraw грузятся с CDN** (esm.sh) по умолчанию — под VPN это
  зависает так же, как загрузки в контейнерах. `main.tsx` выставляет
  `window.EXCALIDRAW_ASSET_PATH` на `/node_modules/@excalidraw/excalidraw/dist/prod/`:
  Vite dev-сервер отдаёт файлы из `node_modules` по их пути. Для продакшен-
  сборки (которой пока нет) шрифты нужно будет скопировать в `public/`.
  Объявление типа для `window.EXCALIDRAW_ASSET_PATH` — в `src/excalidraw-env.d.ts`,
  пакет своего `global.d.ts` наружу не отдаёт.
- **Symfony 8 / DoctrineBundle 3**: часть опций конфига удалена
  (`use_savepoints`, `auto_generate_proxy_classes`). Симптом — «Unrecognized option».
- Зависимости и миграции накатывает entrypoint контейнера php при старте;
  отдельных шагов после `make up` не требуется.
- **Twig, WebProfilerBundle, NelmioApiDocBundle, symfony/asset — только dev/test.**
  Шаблонов у приложения нет, Twig стоит как зависимость профайлера и Swagger UI;
  без `symfony/asset` и `framework.assets` Nelmio молча удаляет контроллер UI,
  а Twig падает на `assets.packages`. Ассеты Swagger UI грузятся с CDN
  (`html_config.assets_mode: cdn`): nginx отдаёт из `public/` только `/api`.
  nginx направляет в PHP лишь `/api`,
  `/_profiler` и `/_wdt`; новый не-API путь бэкенда нужно добавить в
  `docker/nginx/conf.d/default.conf`, иначе он уйдёт во Vite.
- **Xdebug** берёт режим из переменной `XDEBUG_MODE` (приоритетнее ini), поэтому
  смена режима — это пересоздание контейнера, а не правка `xdebug.ini`.
  `host.docker.internal` в Linux появляется только благодаря `extra_hosts`.
  `fastcgi_read_timeout` в nginx поднят до 600 с ради остановок на брейкпоинте.
- Правки PHP подхватываются сразу (bind-mount + `opcache.validate_timestamps`),
  но после `composer require` или правки `config/services.yaml` бывает нужен
  `make cache-clear`.

## Качество кода бэкенда

`make lint` должен быть зелёным перед коммитом. Три инструмента, конфиги в `backend/`:

- **php-cs-fixer** (`.php-cs-fixer.dist.php`): `@Symfony` + `@Symfony:risky`,
  `declare(strict_types=1)`. Встроенные функции и константы **без** ведущего `\`
  (`native_*_invocation` с пустым `include` и `strict`, у констант ещё
  `fix_built_in: false`, иначе слэш вернётся). Отключено `single_line_throw`;
  `no_homoglyph_names` включено — поэтому кириллицы в идентификаторах быть
  не должно: правило молча подменяло бы буквы латинскими двойниками.
- **PHPStan** (`phpstan.dist.neon`): уровень `max`, `src` и `tests`. Расширение
  Symfony читает `var/cache/dev/App_KernelDevDebugContainer.xml` — `make stan`
  прогреет кэш сам, если его нет. Doctrine-расширение без objectManagerLoader:
  анализ не должен требовать базы.
- **Rector** (`rector.php`): наборы PHP 8.5, codeQuality, typeDeclarations,
  deadCode, privatization, earlyReturn и атрибуты Symfony/Doctrine/PHPUnit.
  Отключены правила, сортирующие именованные аргументы (схлопывают атрибуты
  OpenAPI в одну строку) и `PreferPHPUnitThisCallRector` (в тестах `self::assert…`).
  Rector переписывает изменённые узлы целиком и теряет переносы строк — после
  него всегда `make cs`.
- Что уже нашли: в DBAL 4 нет `ConversionException::conversionFailed()`, поэтому
  типы Doctrine бросают `ValueNotConvertible::new()` и `InvalidType::new()`.

## Конвенции

- Комментарии, сообщения об ошибках и подписи тестов на фронтенде (`it('…')`) —
  по-русски. Идентификаторы, включая имена тестовых методов на бэкенде, —
  по-английски в camelCase (`testMovingToSamePlaceRecordsNoEvent`, провайдеры
  данных — `…Provider`). Комментарий объясняет **почему**, а не пересказывает код.
- Стили — классы в SCSS, а не инлайн и не утилиты в разметке. Исключение уже
  в коде: цвет стикера приходит из данных, поэтому он идёт через `style`.
  Величины не хардкодить — в `_tokens.scss`.
- Новый сценарий: DTO команды + хендлер в `Application/<Контекст>/Command/<Имя>/`,
  вызов из контроллера напрямую (командной шины нет — хендлеры инжектятся как сервисы).
  Контроллер — отдельный файл на маршрут, с OpenAPI-атрибутами (см. «Бэкенд»).
  Авторегистрация в `services.yaml` исключает из контейнера `**/ReadModel/`,
  `*Command.php` и `*Query.php` по имени — DTO с другим суффиксом станет
  сервисом и упадёт на автовайринге скалярных аргументов.
- Новый порт: интерфейс в `Application/Shared/Port/`, реализация в `Infrastructure/`,
  связка — явной строкой в `backend/config/services.yaml`.
- `.env` не в репозитории; при добавлении переменной обновляйте `.env.example`.
- Секреты Centrifugo (`CENTRIFUGO_API_KEY`, `CENTRIFUGO_TOKEN_SECRET`) генерирует
  `make init`; из контейнера php они наружу не выходят.

## Чего в этом проекте нет

Не считать пробелом по невнимательности — решения осознанные, но временные:
авторизации нет (имя участника приходит от клиента и ничем не подтверждается,
место для проверки — `IssueRealtimeAccessHandler`); фронтенд отдаётся Vite
dev-сервером, продакшен-сборки в стеке нет; интеграционных тестов нет, только
юнит-тесты домена, сторов и разбора маршрутов, компоненты React и сам
Excalidraw тестами не покрыты; рисунок не синхронизируется между участниками
в realtime — каждый видит его при открытии страницы, а побеждает последнее
сохранение (элементы Excalidraw несут `version` и `versionNonce`, а пакет
экспортирует `reconcileElements` — задел на будущее); картинки в рисунке не
поддерживаются — их файлы живут вне сцены.

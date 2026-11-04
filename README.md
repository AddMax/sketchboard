# Sketchboard

Скелет full-stack приложения: общая доска стикеров, где изменения одного
пользователя мгновенно появляются у остальных, а к каждому стикеру
прикреплён рисунок — редактор Excalidraw, сцену которого хранит бэкенд.
Исходная постановка задачи —
в `promt.md`, контекст для работы с кодом — в `CLAUDE.md`.

## Стек

| Слой | Технология | Образ |
| --- | --- | --- |
| Прокси | nginx | `nginx:1-alpine` |
| API | PHP 8.5 + Symfony 8.1 (php-fpm) | `php:8.5-fpm-alpine` |
| Realtime | Centrifugo 6 — WebSocket-сервер | `centrifugo/centrifugo:v6.9` (alpine) |
| БД | PostgreSQL 18 | `postgres:18-alpine` |
| Кэш и шина событий | Redis 8 | `redis:8-alpine` |
| Файлы | Garage 2 — S3-совместимое хранилище | `dxflrs/garage:v2.2.0` (бинарник переносится в alpine) |
| Фронтенд | React 19 + MobX 7 + Excalidraw + Vite + SCSS | `node:24-alpine` |

## Как это связано

```
             ┌──────────────── браузер ────────────────┐
             │  React (Vite) + centrifuge-js           │
             └───┬──────────────────────────┬──────────┘
       HTTP /api │                          │ WS /ws
             ┌───▼──────────────────────────▼──────────┐
             │            nginx  :8080                 │  единственный открытый порт
             └───┬──────────────┬───────────┬──────────┘
        fastcgi  │              │ proxy     │ proxy
             ┌───▼────┐   ┌─────▼──────┐  ┌─▼───────────┐
             │php-fpm │   │ Centrifugo │  │ vite:5173   │
             │Symfony │──►│   :8000    │  │ (dev + HMR) │
             └─┬──────┘ HTTP API       │  └─────────────┘
     Doctrine  │           └─────┬─────┘
          ┌────▼─┐               │ presence, история, подписки
          │  PG  │         ┌─────▼──────┐
          └──────┘         │   Redis    │◄── кэш Symfony
                           └────────────┘
```

Ключевая деталь: php-fpm живёт ровно один запрос и не может держать открытые
WebSocket-соединения. Их держит Centrifugo, а бэкенд обращается к нему по
серверному HTTP API:

1. React отправляет `POST /api/notes` → nginx → php-fpm.
2. Symfony пишет заметку в PostgreSQL и публикует доменное событие в канал
   Centrifugo (`CentrifugoEventPublisher`).
3. Centrifugo рассылает событие всем браузерам, подписанным на канал.
4. React обновляет доску — у всех одновременно.

Подключение защищено JWT: браузер сначала просит `GET /api/realtime/access`,
бэкенд подписывает короткоживущий токен общим с Centrifugo секретом. Сам
секрет и ключ HTTP API из контейнера php не выходят.

Эфемерные события (курсор, `ping`) клиент публикует в канал напрямую, минуя
бэкенд и базу. Своя же публикация возвращается автору — адаптер отбрасывает
её по `info.client`, который Centrifugo проставляет клиентским сообщениям
и не проставляет серверным.

Что теперь делает Centrifugo вместо своего кода: держит соединения и
переподключения, считает присутствие (`presence`), хранит короткую историю
канала и догоняет пропущенное после обрыва, проверяет токены.

### Файлы

Файлы живут не в PostgreSQL, а в Garage — S3-совместимом хранилище в том же
compose. Бакетов два: `public` и `private`. Публичный раздаёт web-endpoint
Garage без подписи запросов, nginx проксирует его как `/files/…`;
анонимного чтения через S3 API у Garage нет, поэтому другого пути к
публичным файлам и не бывает. Приватный бакет доступен только через S3 API
с ключом, то есть через бэкенд.

Настройка узла — роль в layout, бакеты, ключ доступа — выполняется
entrypoint-скриптом контейнера при каждом старте и идемпотентна, как
миграции в php-контейнере. Пару ключей S3 задаёт `.env` (её генерирует
`make init`): Garage импортирует её, а бэкенд читает из тех же переменных.
`GET /api/health` опрашивает admin-порт Garage и показывает `garage: ok`.

На стороне бэкенда — агрегат `StoredFile` (имя, MIME из закрытого списка,
размер до 10 МБ, видимость, владелец), таблица `files`, порт `FileStorage`
и сценарии загрузки, удаления, чтения метаданных и скачивания: публичный
файл читает кто угодно, приватный — только владелец, удаляет — только
владелец. Порт реализован на Flysystem с адаптером AsyncAws S3: два
хранилища, по одному на бакет, path-style адресация. Маршруты `/api/files`
принимают multipart-загрузку, тип файла определяется по содержимому
(`symfony/mime`), а не по расширению. У публичного файла в ответе есть
прямая ссылка `url` вида `/files/гггг/мм/<id>.<ext>`; приватный отдаёт
только `GET /api/files/{id}/content` с заголовком
`Content-Security-Policy: sandbox`, чтобы SVG или PDF с пользовательским
содержимым не выполнялись в origin приложения. Кто спрашивает, бэкенд
узнаёт из query-параметра `requester` (при загрузке — из поля `owner`)
и, как и везде, ничем это не подтверждает. Когда Garage не отвечает,
маршруты файлов возвращают 503.

## Запуск

```bash
make init          # .env, сборка образов, старт, миграции
```

Или вручную:

```bash
cp .env.example .env
# UID/GID в .env должны совпадать с вашими: id -u && id -g
docker compose up -d --build
```

Первый старт занимает несколько минут: собираются PHP-расширения,
ставятся composer- и npm-зависимости (это делают entrypoint-скрипты
контейнеров, отдельных шагов не требуется).

Готовность видно по логам:

```bash
docker compose logs -f php frontend centrifugo
```

Приложение: <http://localhost:8080>
Проверка связности: <http://localhost:8080/api/health>

### Если сеть работает через VPN

`make init` подставляет в `.env` MTU того интерфейса, через который у машины
идёт внешний трафик. Это важно: у Docker по умолчанию MTU 1500, а у VPN-
интерфейса он меньше — с дефолтным значением любые загрузки из контейнеров
(apk, composer, npm) зависают без ошибки. При ручном запуске проверьте:

```bash
ip link show $(ip route get 1.1.1.1 | awk '{print $5; exit}')   # смотрите mtu
```

и поставьте это значение в `DOCKER_MTU`. Образы собираются с `network: host`
по той же причине.

## Маршруты

| Метод | Путь | Назначение |
| --- | --- | --- |
| GET | `/` | React-приложение (Vite dev-server): доска заметок |
| GET | `/notes/{id}/draw` | то же приложение, страница рисунка заметки (редактор Excalidraw) |
| GET | `/api/doc` | Swagger UI с описанием API (только dev); `/api/doc.json` — спецификация OpenAPI |
| GET | `/api/health` | статус PHP, PostgreSQL, Redis, Centrifugo, Garage |
| GET | `/api/notes` | список заметок |
| POST | `/api/notes` | создать заметку |
| PATCH | `/api/notes/{id}/position` | переместить заметку |
| DELETE | `/api/notes/{id}` | удалить заметку (вместе с рисунком) |
| GET | `/api/notes/{id}/drawing` | элементы сцены Excalidraw (пустой список, если не рисовали) |
| PUT | `/api/notes/{id}/drawing` | сохранить сцену целиком: `{"elements": [элементы Excalidraw как есть]}`; удалённые (`isDeleted: true`) и картинки → 422 |
| POST | `/api/files` | загрузить файл: multipart с полями `file`, `visibility` (`public` по умолчанию), `owner` |
| GET | `/api/files/{id}` | метаданные файла; приватный — только владельцу (`?requester=`) |
| GET | `/api/files/{id}/content` | содержимое файла с `Content-Type` и `Content-Disposition` |
| DELETE | `/api/files/{id}` | удалить файл вместе с объектом в хранилище; только владелец |
| GET | `/files/{путь}` | публичный файл напрямую из Garage (web-endpoint через nginx) |
| GET | `/api/realtime/access` | токен подключения и имя канала |
| GET | `/ws` | WebSocket Centrifugo (проксируется в `/connection/websocket`) |

## Частые команды

```bash
make help          # список команд
make logs          # логи всех сервисов
make sh            # shell в php-контейнере
make migration     # сгенерировать миграцию из изменений маппинга
make migrate       # применить миграции
make test          # юнит-тесты (бэк + фронт); make test-back / test-front — по отдельности
make health        # состояние стека через /api/health
make garage c="bucket list"   # CLI Garage; без c — статус узла
make lint          # бэкенд: стиль, статический анализ, рефакторинг — только проверка
make cs            # php-cs-fixer: исправить стиль
make stan          # phpstan, уровень max
make rector        # rector: применить автоматический рефакторинг
make schema-validate  # сверить XML-маппинг со схемой БД
make psql          # psql в контейнере postgres
make composer c="require symfony/mailer"
make npm c="install zustand"
make xdebug-on     # включить пошаговую отладку (пересоздаёт контейнер php)
make xdebug-off
make xdebug-status # текущий режим Xdebug
make npm c="run typecheck"   # проверка типов фронтенда
make destroy       # снести окружение вместе с данными
```

## Документация API

Swagger UI: <http://localhost:8080/api/doc>, спецификация OpenAPI 3 —
<http://localhost:8080/api/doc.json> (NelmioApiDocBundle, только `dev`).
Операции описаны атрибутами `OpenApi\Attributes` прямо на контроллерах, общие
схемы ответов и ошибок — в `backend/config/packages/nelmio_api_doc.yaml`.
Из UI можно выполнять запросы к живому стеку.

## Качество кода бэкенда

Три инструмента, все в `require-dev`, конфиги в корне `backend/`:

| Инструмент | Конфиг | Проверить | Исправить |
| --- | --- | --- | --- |
| php-cs-fixer (`@Symfony` + risky, strict_types) | `.php-cs-fixer.dist.php` | `make cs-check` | `make cs` |
| PHPStan, уровень `max`, расширения Symfony/Doctrine/PHPUnit | `phpstan.dist.neon` | `make stan` | — |
| Rector, наборы PHP 8.5, качество кода, типы, атрибуты | `rector.php` | `make rector-check` | `make rector` |

`make lint` запускает все три проверки подряд и ничего не меняет — это то,
что стоит прогнать перед коммитом. Порядок правок: `make rector`, затем
`make cs`, затем `make stan`. Кэши инструментов лежат в `backend/var/cache/`.

## Отладка бэкенда

### Web-профайлер Symfony

Работает в `dev` и открывается по адресу <http://localhost:8080/_profiler/>.
API отдаёт JSON, поэтому тулбар в ответы не встраивается: у каждого ответа
есть заголовок `X-Debug-Token-Link` с прямой ссылкой на профиль запроса,
а последние запросы видны в списке профайлера. Там же — SQL Doctrine с таймингами,
логи, события, маршрутизация и запросы к Centrifugo (панель HTTP Client).

### Xdebug

Расширение собрано в образ php, но по умолчанию выключено (`XDEBUG_MODE=off`
в `.env`). Включение:

```bash
make xdebug-on      # XDEBUG_MODE=debug, контейнер php пересоздаётся
make xdebug-status  # проверить режим
make xdebug-off
```

В режиме `debug` Xdebug на каждом запросе подключается к IDE на хосте
(`host.docker.internal:9003`); если IDE не слушает, через 200 мс сдаётся,
и запрос выполняется как обычно. Отладочный лог Xdebug идёт в `make logs`.

Настройка IDE (маппинг путей обязателен: в контейнере код лежит не там,
где на хосте):

| Что | Значение |
| --- | --- |
| Порт отладчика | `9003` |
| Путь на хосте → в контейнере | `./backend` → `/var/www/backend` |
| PhpStorm, имя сервера | `sketchboard` (host `localhost`, port `8080`) |
| IDE key | `PHPSTORM` |

В PhpStorm: *Settings → PHP → Servers* → добавить сервер `sketchboard`
с включённым *Use path mappings*, затем *Run → Start Listening for PHP Debug
Connections*. Имя сервера уже передано в контейнер через `PHP_IDE_CONFIG`,
поэтому отладка `bin/console` и `phpunit` из `make sh` работает без
дополнительных настроек.

Для VS Code (расширение PHP Debug) конфигурация `launch.json`:

```json
{
  "name": "Xdebug: sketchboard",
  "type": "php",
  "request": "launch",
  "port": 9003,
  "pathMappings": { "/var/www/backend": "${workspaceFolder}/backend" }
}
```

Пока запрос стоит на точке останова, nginx ждёт ответа php до 10 минут
(`fastcgi_read_timeout`), поэтому вместо 504 вы дошагаете до конца.

## Архитектура

Обе части разложены по слоям DDD. Зависимости направлены строго внутрь:
домен не знает ни о фреймворке, ни о транспорте, а внешние слои общаются
с ним через порты.

```
      UI / транспорт          →  контроллеры, консоль, React-компоненты
            ↓
      Приложение              →  сценарии (команды и запросы), порты
            ↓
      Домен                   →  агрегаты, объекты-значения, события
            ↑
      Инфраструктура          →  адаптеры портов: Doctrine, HTTP API Centrifugo, centrifuge-js, fetch
```

### Бэкенд

```
backend/src/
  Domain/                     чистый PHP: ни Symfony, ни Doctrine
    Note/
      Note.php                корень агрегата, копит доменные события
      NoteRepository.php      порт хранилища
      ValueObject/            NoteId, NoteText, Position, Color, Author
      Event/                  NoteWasCreated, NoteWasMoved, NoteWasDeleted
      Exception/NoteNotFound.php
    Drawing/
      Drawing.php             рисунок заметки: отдельный агрегат с тем же идентификатором
      DrawingRepository.php   порт хранилища
      ValueObject/            Elements (вся сцена целиком), Element (элемент Excalidraw как есть)
    File/
      StoredFile.php          метаданные загруженного файла; байты — в хранилище под вычисляемым ключом
      FileRepository.php      порт хранилища метаданных
      ValueObject/            FileId, OriginalName, MimeType, FileSize, Visibility, StorageKey
      Exception/              FileNotFound, FileAccessDenied
    Shared/                   DomainEvent, InvalidArgument
  Application/                сценарии, знают домен и порты — больше ничего
    Note/Command/…            CreateNote, MoveNote, DeleteNote (+ Handler)
    Note/Query/ListNotes/
    Note/ReadModel/NoteView.php
    Drawing/Command/SaveDrawing/
    Drawing/Query/GetDrawing/
    Drawing/ReadModel/DrawingView.php
    File/Command/…            UploadFile, DeleteFile (+ Handler)
    File/Query/…              GetFile (метаданные), DownloadFile (байты)
    File/ReadModel/           FileView, FileContent
    Realtime/Query/IssueRealtimeAccess/
    Shared/Port/              DomainEventPublisher, RealtimeAccess, RealtimeCredentials, FileStorage
  Infrastructure/             адаптеры портов
    Persistence/Doctrine/     репозитории, DBAL-типы (в том числе ElementsType — JSON), XML-маппинг
    Realtime/                 DomainEventSerializer: форма сообщения для клиентов
    Realtime/Centrifugo/      HTTP API, публикация событий, выдача JWT
    Storage/Garage/           admin API Garage: только проба для /api/health
    Storage/Flysystem/        FileStorage на Flysystem (AsyncAws S3), по хранилищу на бакет
  UI/
    Http/Controller/          по классу на маршрут: Note/, Drawing/, File/, Realtime/, HealthController
    Http/JsonPayload.php      разбор тела запроса
    Http/UploadedFilePayload.php  файл из multipart: имя, тип по содержимому, байты
    Http/EventListener/       доменные исключения → коды HTTP
```

Инварианты стерегут объекты-значения, а не аннотации валидатора: пустой
текст, цвет вне формата `#rrggbb` и координата за пределами полотна просто
не могут существовать. `symfony/validator` поэтому в зависимостях не нужен:
`DomainExceptionListener` в одном месте превращает `InvalidArgument` в 422,
`NoteNotFound` и `FileNotFound` — в 404, `FileAccessDenied` — в 403,
а `StorageUnavailable` — в 503; контроллеры исключений не ловят.

Рисунок — отдельный агрегат, а не поле заметки: заметка при каждом
изменении летает по realtime-каналу целиком, а рисунок может весить сотни
килобайт. Его содержимое — сцена Excalidraw: редактор живёт в браузере,
а сервер хранит её элементы одним JSON-документом в том виде, в каком их
прислал редактор, и проверяет только то, без чего элемент нельзя
сохранить, — идентификатор, тип, число точек и элементов, отсутствие
признака удаления. Картинки не принимаются: их файлы Excalidraw хранит
отдельно от сцены. Документ всегда заменяется целиком, событий не порождает
и по каналу не рассылается. Связь между агрегатами держит
`DeleteNoteHandler`, а не внешний ключ: удаляя заметку, он удаляет
и рисунок.

Маппинг Doctrine вынесен в XML (`Infrastructure/Persistence/Doctrine/Mapping`),
чтобы агрегаты остались свободны от атрибутов ORM. Таблиц три — `notes`,
`drawings` и `files`; `make schema-validate` подтверждает совпадение маппинга
со схемой.

### Фронтенд

```
frontend/src/
  domain/
    note/Note.ts              правила заметки (те же, что на сервере), errors.ts — DomainError
    board/Board.ts            applyRealtimeEvent — чистая функция состояния доски
    drawing/Drawing.ts        элемент сцены (id, type, isDeleted — остальное принадлежит редактору), liveElements
    file/StoredFile.ts        описание файла и правила загрузки (те же, что на сервере)
    realtime/RealtimeEvent.ts типы событий и разбор входящих сообщений
  application/
    ports/                    NoteRepository, DrawingRepository, FileRepository, RealtimeChannel, RealtimeAccessProvider
    board/BoardStore.ts       MobX-стор доски: загрузка, события канала, команды
    drawing/DrawingStore.ts   MobX-стор рисунка заметки: элементы, отложенное сохранение, статус
    file/FileStore.ts         MobX-стор файлов: загрузка, описание по id, удаление, адрес содержимого
    Stores.tsx                сборка сторов из адаптеров и контекст для компонентов
    Dependencies.ts           набор адаптеров, из которого собираются сторы
    shared/debounce.ts        дебаунс без React — им пользуется стор
    shared/describeError.ts   текст ошибки для человека
  infrastructure/
    config.ts                 переменные окружения сборки, адрес WebSocket
    http/jsonRequest.ts       общий вызов JSON API; 422 → DomainError
    http/HttpNoteRepository.ts, HttpDrawingRepository.ts   адаптеры REST
    http/HttpFileRepository.ts  адаптер файлов: multipart на загрузку, JSON на остальное
    http/HttpRealtimeAccessProvider.ts   получение токена подключения
    realtime/CentrifugoRealtimeChannel.ts адаптер поверх centrifuge-js
    container.ts              композиционный корень
  ui/
    pages/                    BoardPage, DrawingPage
    routing/                  routes.ts (разбор пути), RouterStore, Link — свой роутер на History API
    components/               доска, карточка заметки с кнопкой-карандашом, композер, статус
    components/drawing/       DrawingEditor — Excalidraw поверх DrawingStore; SaveStatus — статус в его панели
    colorScheme.ts            системная цветовая схема как наблюдаемое значение (тема редактора)
    identity.ts               имя участника в localStorage (авторизации нет)
    styles/                   SCSS: токены, миксины и стили по блокам
```

Состоянием управляет MobX: сторы в `application/` хранят наблюдаемое
состояние и все действия над ним, компоненты обёрнуты в `observer` и
только читают сторы и вызывают их методы. Сторы собираются в композиционном
корне из адаптеров портов и приходят в компоненты через контекст, поэтому
в тестах на место репозиториев и канала встают объекты в памяти, а сценарии
доски и рисунка проверяются без React и сети. Правила заметки и разбор
маршрутов — чистые функции.

Рисунок редактирует Excalidraw (`DrawingEditor`): фигуры, стрелки, текст,
кисть, выделение и перемещение, undo, экспорт в PNG/SVG — всё его. Сценой
владеет редактор, а `DrawingStore` получает от него снимок при каждом
изменении сцены (`onChange` стреляет и на движение указателя, поэтому
снимки сравниваются по `getSceneVersion`), отбрасывает удалённые элементы
и отправляет документ на сервер через полторы секунды после последнего
изменения; при уходе со страницы отложенный вызов выполняется сразу.
Статус («Сохранено», «Сохранение…», «Ошибка сохранения») стоит в верхней
панели редактора. Тема редактора следует за системной; загрузка сцены из
файла, смена фона и картинки выключены — они не сохраняются на сервере.
Шрифты Excalidraw по умолчанию грузятся с CDN, здесь же они берутся из
`node_modules` через Vite (`EXCALIDRAW_ASSET_PATH`), чтобы не зависеть
от внешней сети.

Переход с самописного WebSocket-сервера на Centrifugo это наглядно
подтвердил: поменялись только адаптеры по обе стороны — порты
`DomainEventPublisher` и `RealtimeChannel`, сценарии и оба домена остались
дословно теми же, и все 32 теста домена прошли без правок.

### Тесты

```bash
make test          # домен бэкенда и фронтенда
```

Обе группы работают без базы, Redis и контейнера — это и есть проверка того,
что домен ни от чего не зависит: 87 тестов PHP (`backend/tests/Unit/`: агрегаты
`Note`, `Drawing` и `StoredFile`, объекты-значения, разбор элементов сцены,
сценарии файла с репозиторием и хранилищем в памяти, адаптер Flysystem на
файловой системе в памяти, разбор multipart-загрузки) и 44 теста TS
(`frontend/tests/`: правила заметки и загрузки файла, состояние доски,
очистка сцены от удалённых элементов, сторы доски, рисунка и файлов с
адаптерами в памяти, разбор маршрутов) за миллисекунды. Интеграционных тестов нет.

## Проверено

Стек поднят с нуля и проверен сквозным сценарием:

- `GET /api/health` → `{"status":"ok","php":"8.5.10","symfony":"8.1.6",
  "postgres":"ok","redis":"ok","centrifugo":"ok","garage":"ok"}`;
- `POST /api/notes` пишет в PostgreSQL, и событие `note.created` приходит
  в браузер по WebSocket; то же для `note.moved` и `note.deleted`;
- эфемерное событие (`cursor`) от одного клиента доходит до другого, минуя
  бэкенд; `presence` Centrifugo показал 2 клиентов и 1 после отключения;
- собственная публикация приходит с `info.client`, серверная — без него,
  поэтому адаптер отбрасывает ровно своё эхо;
- доменные правила отвечают через все слои: пустой текст и цвет не в формате
  `#rrggbb` → 422 с указанием поля, координата вне доски → 422, отсутствующая
  заметка → 404; перемещение «на то же место» события не порождает;
- HMR Vite работает через тот же прокси: правка `App.tsx` доезжает
  как `js-update`;
- миграции применяются автоматически при старте php-контейнера;
- рисунок сохраняется через `PUT /api/notes/{id}/drawing` и возвращается при
  повторном открытии страницы; элемент сверх лимита, с `isDeleted: true`
  или типа `image` → 422;
- элемент Excalidraw с любыми полями редактора (`version`, `seed`,
  `customData`…) возвращается байт в байт, порядок элементов сохраняется;
- рисунки прежнего формата (штрихи и фигуры своего полотна) переведены
  миграцией в элементы Excalidraw и открываются в редакторе; шрифты и
  локаль редактора грузятся с того же origin, внешних запросов нет;
- Garage поднимается с нуля: entrypoint назначает роль узлу, создаёт бакеты
  `public` и `private` и импортирует ключ из `.env`; повторный старт
  ничего не пересоздаёт. Объект, загруженный в `public` по S3 API,
  читается как `GET /files/<ключ>` → 200; тот же путь к объекту из
  `private` → 404; `/api/health` без Garage отвечает 503.
- файлы ходят через API: `POST /api/files` с PNG → 201 и `url`, по которому
  nginx отдаёт те же байты из Garage; HTML под видом картинки → 422 по полю
  `mimeType`, без файла → 422 по полю `file`; приватный файл чужому и
  анонимному → 403, владельцу через `/content` → 200 с `Content-Disposition`
  (имя в UTF-8 и транслитерация для старых клиентов), а по `/files/…` → 404;
  удалить чужой файл → 403, свой → 204, после чего и запись, и объект
  исчезают (`bucket info` показывает 0 объектов); при остановленном Garage
  загрузка и скачивание → 503, записей-сирот не остаётся;
- `FileStore` с настоящим `HttpFileRepository`, запущенный из контейнера
  фронтенда против живого API: публичная и приватная загрузка через
  multipart, адрес приватного содержимого открывается владельцем, чужой
  запрос и чужое удаление оставляют текст ошибки в сторе и не трогают
  список, своё удаление очищает и список, и бакеты.

## Что стоит изменить перед продакшеном

- `APP_SECRET` и пароль PostgreSQL в `.env` — это значения для локальной разработки.
- Фронтенд отдаётся Vite dev-server'ом. Для боевого окружения нужен
  `vite build` и раздача статики nginx'ом напрямую.
- В `php.ini` включён `display_errors` и `opcache.validate_timestamps` —
  для продакшена оба надо выключить.
- Подключение к каналу защищено JWT, но самого пользователя ещё нет: имя
  участника приходит от клиента и ничем не подтверждается. Реальная
  авторизация появится в `IssueRealtimeAccessHandler`.
- `CENTRIFUGO_API_KEY` и `CENTRIFUGO_TOKEN_SECRET` в `.env` сгенерированы
  локально; в бою их выдаёт хранилище секретов. То же про `GARAGE_RPC_SECRET`,
  `GARAGE_ADMIN_TOKEN` и пару `S3_ACCESS_KEY`/`S3_SECRET_KEY`.
- Garage запущен одним узлом с `replication_factor = 1`: данные хранятся в
  единственном экземпляре. Для боя нужны три узла и `replication_factor = 3`
  либо внешнее S3-хранилище — бэкенд ходит по стандартному S3 API, и
  подмена сводится к `S3_ENDPOINT` и ключам.
- Публиковать в канал разрешено любому подписчику (`allow_publish_for_subscriber`) —
  это нужно для курсоров. Если клиентские публикации не потребуются,
  опцию стоит выключить.
- Рисунок не синхронизируется между участниками в realtime: каждый видит
  его при открытии страницы, а при одновременном редактировании побеждает
  последнее сохранение. Элементы Excalidraw несут `version` и
  `versionNonce`, а пакет экспортирует `reconcileElements`, так что
  слияние правок через канал — следующий шаг, а не переделка.
- Сцена не хранит картинок: Excalidraw держит их файлы отдельно от
  элементов, и сервер их не принимает (инструмент в редакторе выключен,
  но библиотека фигур может принести `image` — такое сохранение ответит 422).
- Профайлер, Swagger UI, Twig и `symfony/asset` стоят в `require-dev` и
  включаются только в `dev`; Xdebug собран в образ, но выключен переменной
  `XDEBUG_MODE`. В боевом образе их не должно быть вовсе.

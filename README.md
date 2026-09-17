# Sketchboard

Скелет full-stack приложения: общая доска стикеров, где изменения одного
пользователя мгновенно появляются у остальных, а к каждому стикеру
прикреплена бесконечная доска для рисования. Исходная постановка задачи —
в `promt.md`, контекст для работы с кодом — в `CLAUDE.md`.

## Стек

| Слой | Технология | Образ |
| --- | --- | --- |
| Прокси | nginx | `nginx:1-alpine` |
| API | PHP 8.5 + Symfony 8.1 (php-fpm) | `php:8.5-fpm-alpine` |
| Realtime | Centrifugo 6 — WebSocket-сервер | `centrifugo/centrifugo:v6.9` (alpine) |
| БД | PostgreSQL 18 | `postgres:18-alpine` |
| Кэш и шина событий | Redis 8 | `redis:8-alpine` |
| Фронтенд | React 19 + MobX 7 + Vite + SCSS | `node:24-alpine` |

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
| GET | `/notes/{id}/draw` | то же приложение, страница доски для рисования по заметке |
| GET | `/api/doc` | Swagger UI с описанием API (только dev); `/api/doc.json` — спецификация OpenAPI |
| GET | `/api/health` | статус PHP, PostgreSQL, Redis, Centrifugo |
| GET | `/api/notes` | список заметок |
| POST | `/api/notes` | создать заметку |
| PATCH | `/api/notes/{id}/position` | переместить заметку |
| DELETE | `/api/notes/{id}` | удалить заметку (вместе с рисунком) |
| GET | `/api/notes/{id}/drawing` | штрихи рисунка заметки (пустой список, если не рисовали) |
| PUT | `/api/notes/{id}/drawing` | сохранить рисунок целиком: `{"lines": [{id, points, color, width}]}` |
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
      ValueObject/            Strokes (все штрихи целиком), Line, Point
    Shared/                   DomainEvent, InvalidArgument
  Application/                сценарии, знают домен и порты — больше ничего
    Note/Command/…            CreateNote, MoveNote, DeleteNote (+ Handler)
    Note/Query/ListNotes/
    Note/ReadModel/NoteView.php
    Drawing/Command/SaveDrawing/
    Drawing/Query/GetDrawing/
    Drawing/ReadModel/DrawingView.php
    Realtime/Query/IssueRealtimeAccess/
    Shared/Port/              DomainEventPublisher, RealtimeAccess, RealtimeCredentials
  Infrastructure/             адаптеры портов
    Persistence/Doctrine/     репозитории, DBAL-типы (в том числе StrokesType — JSON), XML-маппинг
    Realtime/                 DomainEventSerializer: форма сообщения для клиентов
    Realtime/Centrifugo/      HTTP API, публикация событий, выдача JWT
  UI/
    Http/Controller/          по классу на маршрут: Note/, Drawing/, Realtime/, HealthController
    Http/JsonPayload.php      разбор тела запроса
    Http/EventListener/       доменные исключения → коды HTTP
```

Инварианты стерегут объекты-значения, а не аннотации валидатора: пустой
текст, цвет вне формата `#rrggbb` и координата за пределами полотна просто
не могут существовать. `symfony/validator` поэтому в зависимостях не нужен:
`DomainExceptionListener` в одном месте превращает `InvalidArgument` в 422,
а `NoteNotFound` — в 404, контроллеры исключений не ловят.

Рисунок — отдельный агрегат, а не поле заметки: заметка при каждом
изменении летает по realtime-каналу целиком, а рисунок может весить сотни
килобайт. Он хранится одним JSON-документом и всегда заменяется целиком,
событий не порождает и по каналу не рассылается. Связь между агрегатами
держит `DeleteNoteHandler`, а не внешний ключ: удаляя заметку, он удаляет
и рисунок.

Маппинг Doctrine вынесен в XML (`Infrastructure/Persistence/Doctrine/Mapping`),
чтобы агрегаты остались свободны от атрибутов ORM. Таблиц две — `notes` и
`drawings`; `make schema-validate` подтверждает совпадение маппинга со схемой.

### Фронтенд

```
frontend/src/
  domain/
    note/Note.ts              правила заметки (те же, что на сервере), errors.ts — DomainError
    board/Board.ts            applyRealtimeEvent — чистая функция состояния доски
    drawing/Drawing.ts        типы штриха и пределы кисти
    drawing/Viewport.ts       математика вьюпорта: pan, zoom к курсору, видимая область, шаг сетки
    realtime/RealtimeEvent.ts типы событий и разбор входящих сообщений
  application/
    ports/                    NoteRepository, DrawingRepository, RealtimeChannel, RealtimeAccessProvider
    board/BoardStore.ts       MobX-стор доски: загрузка, события канала, команды
    drawing/DrawingStore.ts   MobX-стор рисунка заметки: штрихи, отложенное сохранение, статус
    Stores.tsx                сборка сторов из адаптеров и контекст для компонентов
    Dependencies.ts           набор адаптеров, из которого собираются сторы
    shared/debounce.ts        дебаунс без React — им пользуется стор
    shared/describeError.ts   текст ошибки для человека
  infrastructure/
    config.ts                 переменные окружения сборки, адрес WebSocket
    http/jsonRequest.ts       общий вызов JSON API; 422 → DomainError
    http/HttpNoteRepository.ts, HttpDrawingRepository.ts   адаптеры REST
    http/HttpRealtimeAccessProvider.ts   получение токена подключения
    realtime/CentrifugoRealtimeChannel.ts адаптер поверх centrifuge-js
    container.ts              композиционный корень
  ui/
    pages/                    BoardPage, DrawingPage
    routing/                  routes.ts (разбор пути), RouterStore, Link — свой роутер на History API
    components/               доска, карточка заметки с кнопкой-карандашом, композер, статус
    components/canvas/        InfiniteCanvas — наблюдает за DrawingStore
    identity.ts               имя участника в localStorage (авторизации нет)
    styles/                   SCSS: токены, миксины и стили по блокам
```

Состоянием управляет MobX: сторы в `application/` хранят наблюдаемое
состояние и все действия над ним, компоненты обёрнуты в `observer` и
только читают сторы и вызывают их методы. Сторы собираются в композиционном
корне из адаптеров портов и приходят в компоненты через контекст, поэтому
в тестах на место репозиториев и канала встают объекты в памяти, а сценарии
доски и рисунка проверяются без React и сети. Правила заметки, разбор
маршрутов и математика вьюпорта — чистые функции.

Полотно для рисования (`InfiniteCanvas`) держит вьюпорт и текущий штрих
в ref: во время движения мыши сегменты рисуются прямо в контекст Canvas,
React перерисовывается только по завершении штриха. Готовый штрих полотно
отдаёт в `DrawingStore`, а тот отправляет рисунок на сервер через полторы
секунды после последнего штриха; при уходе со страницы отложенный вызов
выполняется сразу. Статус («Сохранено», «Сохранение…», «Ошибка сохранения»)
показан в углу полотна.

Переход с самописного WebSocket-сервера на Centrifugo это наглядно
подтвердил: поменялись только адаптеры по обе стороны — порты
`DomainEventPublisher` и `RealtimeChannel`, сценарии и оба домена остались
дословно теми же, и все 32 теста домена прошли без правок.

### Тесты

```bash
make test          # домен бэкенда и фронтенда
```

Обе группы работают без базы, Redis и контейнера — это и есть проверка того,
что домен ни от чего не зависит: 34 теста PHP (`backend/tests/Unit/`: агрегаты
`Note` и `Drawing`, объекты-значения) и 37 тестов TS (`frontend/tests/`:
правила заметки, состояние доски, сторы доски и рисунка с адаптерами в памяти,
разбор маршрутов, математика вьюпорта) за миллисекунды. Интеграционных
тестов нет.

## Проверено

Стек поднят с нуля и проверен сквозным сценарием:

- `GET /api/health` → `{"status":"ok","php":"8.5.10","symfony":"8.1.6",
  "postgres":"ok","redis":"ok","centrifugo":"ok"}`;
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
  повторном открытии страницы; лишняя линия сверх лимита или цвет вне
  формата → 422.

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
  локально; в бою их выдаёт хранилище секретов.
- Публиковать в канал разрешено любому подписчику (`allow_publish_for_subscriber`) —
  это нужно для курсоров. Если клиентские публикации не потребуются,
  опцию стоит выключить.
- Рисунок не синхронизируется между участниками в realtime: каждый видит
  его при открытии страницы, а при одновременном редактировании побеждает
  последнее сохранение.
- Профайлер, Swagger UI, Twig и `symfony/asset` стоят в `require-dev` и
  включаются только в `dev`; Xdebug собран в образ, но выключен переменной
  `XDEBUG_MODE`. В боевом образе их не должно быть вовсе.

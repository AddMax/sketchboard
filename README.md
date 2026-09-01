# Sketchboard

Скелет full-stack приложения: общая доска стикеров, где изменения одного
пользователя мгновенно появляются у остальных.

## Стек

| Слой | Технология | Образ |
| --- | --- | --- |
| Прокси | nginx | `nginx:1-alpine` |
| API | PHP 8.5 + Symfony 8.1 (php-fpm) | `php:8.5-fpm-alpine` |
| Realtime | WebSocket-сервер на amphp | тот же образ, другая точка входа |
| БД | PostgreSQL 18 | `postgres:18-alpine` |
| Кэш и шина событий | Redis 8 | `redis:8-alpine` |
| Фронтенд | React 19 + Vite (dev-server с HMR) | `node:24-alpine` |

## Как это связано

```
             ┌──────────────── браузер ────────────────┐
             │  React (Vite)                           │
             └───┬──────────────────────────┬──────────┘
       HTTP /api │                          │ WS /ws
             ┌───▼──────────────────────────▼──────────┐
             │            nginx  :8080                 │  единственный открытый порт
             └───┬──────────────┬───────────┬──────────┘
        fastcgi  │              │ proxy     │ proxy
             ┌───▼────┐   ┌─────▼──────┐  ┌─▼───────────┐
             │php-fpm │   │ websocket  │  │ vite:5173   │
             │Symfony │   │  amphp     │  │ (dev + HMR) │
             └─┬────┬─┘   └─────▲──────┘  └─────────────┘
     Doctrine  │    │ publish   │ subscribe
          ┌────▼─┐  │     ┌─────┴──────┐
          │  PG  │  └────►│   Redis    │
          └──────┘        └────────────┘
```

Ключевая деталь: php-fpm живёт ровно один запрос и не может держать открытые
WebSocket-соединения. Поэтому соединения держит отдельный контейнер
`websocket`, а обмен между ним и API идёт через Redis Pub/Sub:

1. React отправляет `POST /api/notes` → nginx → php-fpm.
2. Symfony пишет заметку в PostgreSQL и публикует событие в канал Redis
   (`App\Realtime\RealtimePublisher`).
3. WebSocket-сервер подписан на этот канал и рассылает событие всем
   подключённым браузерам.
4. React обновляет доску — у всех одновременно.

Эфемерные события (курсор, `ping`) ходят напрямую через WebSocket, минуя БД.

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
docker compose logs -f php frontend websocket
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
| GET | `/` | React-приложение (Vite dev-server) |
| GET | `/api/health` | статус PHP, PostgreSQL, Redis |
| GET | `/api/notes` | список заметок |
| POST | `/api/notes` | создать заметку |
| PATCH | `/api/notes/{id}/position` | переместить заметку |
| DELETE | `/api/notes/{id}` | удалить заметку |
| GET | `/ws` | WebSocket-канал realtime-событий |

## Частые команды

```bash
make help          # список команд
make logs          # логи всех сервисов
make sh            # shell в php-контейнере
make migration     # сгенерировать миграцию из изменений маппинга
make migrate       # применить миграции
make test          # юнит-тесты домена (бэк + фронт)
make schema-validate  # сверить XML-маппинг со схемой БД
make psql          # psql в контейнере postgres
make composer c="require symfony/mailer"
make npm c="install zustand"
make destroy       # снести окружение вместе с данными
```

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
      Инфраструктура          →  адаптеры портов: Doctrine, Redis, WebSocket, fetch
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
    Shared/                   DomainEvent, InvalidArgument
  Application/                сценарии, знают домен и порты — больше ничего
    Note/Command/…            CreateNote, MoveNote, DeleteNote (+ Handler)
    Note/Query/ListNotes/
    Note/ReadModel/NoteView.php
    Shared/Port/DomainEventPublisher.php
  Infrastructure/             адаптеры портов
    Persistence/Doctrine/     репозиторий, DBAL-типы, XML-маппинг
    Realtime/                 публикация событий в Redis + сериализация
    WebSocket/                обработчик WS-соединений
  UI/
    Http/Controller/          тонкие контроллеры
    Http/EventListener/       доменные исключения → коды HTTP
    Console/                  app:websocket:serve
```

Инварианты стерегут объекты-значения, а не аннотации валидатора: пустой
текст, цвет вне формата `#rrggbb` и координата за пределами полотна просто
не могут существовать. `symfony/validator` поэтому в зависимостях не нужен —
контроллеру достаточно поймать `InvalidArgument` и ответить 422.

Маппинг Doctrine вынесен в XML (`Infrastructure/Persistence/Doctrine/Mapping`),
чтобы агрегат остался свободен от атрибутов ORM. Схема таблицы `notes` от
этого не изменилась: `make schema-validate` подтверждает совпадение.

### Фронтенд

```
frontend/src/
  domain/
    note/Note.ts              правила заметки (те же, что на сервере)
    board/Board.ts            applyRealtimeEvent — чистая функция состояния доски
    realtime/RealtimeEvent.ts типы событий и разбор входящих сообщений
  application/
    ports/                    NoteRepository, RealtimeChannel
    board/useBoard.ts         сценарий доски
    board/BoardDependencies.tsx   проброс адаптеров через контекст
  infrastructure/
    http/HttpNoteRepository.ts        адаптер REST
    realtime/WebSocketRealtimeChannel.ts  адаптер WS с переподключением
    container.ts              композиционный корень
  ui/                         компоненты, не знающие о транспорте
```

Компоненты получают адаптеры через контекст, поэтому в тестах на место
`NoteRepository` встаёт объект в памяти, а логика доски проверяется без
React и сети.

### Тесты

```bash
make test          # домен бэкенда и фронтенда
```

Обе группы работают без базы, Redis и контейнера — это и есть проверка того,
что домен ни от чего не зависит: 19 тестов PHP за ~5 мс, 13 тестов TS за ~15 мс.

## Проверено

Стек поднят с нуля и проверен сквозным сценарием:

- `GET /api/health` → `{"status":"ok","php":"8.5.10","symfony":"8.1.6",
  "postgres":"ok","redis":"ok"}`;
- `POST /api/notes` пишет в PostgreSQL, и событие `note.created` приходит
  в браузер по WebSocket; то же для `note.moved` и `note.deleted`;
- эфемерное событие (`cursor`) от одного клиента доходит до другого,
  минуя БД; подключение и отключение меняют `presence`;
- доменные правила отвечают через все слои: пустой текст и цвет не в формате
  `#rrggbb` → 422 с указанием поля, координата вне доски → 422, отсутствующая
  заметка → 404; перемещение «на то же место» события не порождает;
- HMR Vite работает через тот же прокси: правка `App.tsx` доезжает
  как `js-update`;
- миграции применяются автоматически при старте php-контейнера.

## Что стоит изменить перед продакшеном

- `APP_SECRET` и пароль PostgreSQL в `.env` — это значения для локальной разработки.
- Фронтенд отдаётся Vite dev-server'ом. Для боевого окружения нужен
  `vite build` и раздача статики nginx'ом напрямую.
- В `php.ini` включён `display_errors` и `opcache.validate_timestamps` —
  для продакшена оба надо выключить.
- WebSocket-канал не аутентифицирован: подключиться может кто угодно.

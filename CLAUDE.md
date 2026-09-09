# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

Sketchboard — realtime-доска заметок. Скелет проекта: Symfony 8.1 на PHP 8.5,
PostgreSQL 18, Redis 8, React 19, Centrifugo 6, всё за nginx в Docker.
Описание для людей — в `README.md`; здесь то, что экономит время при работе с кодом.

## Команды

Всё выполняется в контейнерах, на хосте ничего не установлено.

```bash
make init                  # первый запуск: .env, секреты, сборка, старт, миграции
make up / down / destroy   # destroy сносит и данные
make logs                  # логи всех сервисов
make test                  # тесты домена: 19 на бэке, 13 на фронте
make schema-validate       # сверить XML-маппинг Doctrine со схемой БД
make migration / migrate   # создать миграцию из маппинга / применить
make cache-clear
make composer c="require ..."   # make npm c="install ..."
```

Один тест:

```bash
docker compose exec -T php vendor/bin/phpunit --filter testПеремещениеНаТоЖеМесто
docker compose exec -T frontend npx vitest run -t "не дублирует"
docker compose exec -T frontend npm run typecheck
```

Проверка живого стека: `curl -s localhost:8080/api/health` — отвечает статусами
PostgreSQL, Redis и Centrifugo сразу.

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
| `UI/` | разобрать запрос → вызвать хендлер → отдать JSON |

- Инварианты стерегут объекты-значения (`NoteText`, `Position`, `Color`, `Author`),
  а не валидатор: `symfony/validator` намеренно не установлен. VO бросают
  `Domain\Shared\InvalidArgument`, а `DomainExceptionListener` превращает его в 422,
  `NoteNotFound` — в 404. Контроллеры не ловят исключения сами.
- Агрегат копит события (`recordThat`) и отдаёт их через `releaseEvents()`.
  Публикует их **хендлер после успешной записи**, не репозиторий и не контроллер.
  В `DeleteNoteHandler` события снимаются до `remove()` — после удаления объект уже не наш.
- Маппинг Doctrine — XML в `Infrastructure/Persistence/Doctrine/Mapping/`.
  Имя файла кодирует FQCN относительно префикса `App\Domain`:
  `App\Domain\Note\Note` → `Note.Note.orm.xml`. Ошибиться легко, симптом —
  «Class 'App\Domain\Note' does not exist».
- Форма realtime-сообщения (`{event, payload, at}`) описана в
  `Infrastructure/Realtime/DomainEventSerializer` — это контракт с фронтендом,
  а не часть домена. Меняя её, правьте `frontend/src/domain/realtime/RealtimeEvent.ts`.

### Фронтенд (`frontend/src/`)

Те же слои: `domain/` (правила заметки и чистая функция `applyRealtimeEvent`),
`application/` (порты `NoteRepository`, `RealtimeChannel`, `RealtimeAccessProvider`
и сценарий `useBoard`), `infrastructure/` (адаптеры + `container.ts` —
единственное место, где выбираются реализации), `ui/` (компоненты).

Интерфейс — свои компоненты; библиотеки готовых компонентов нет, экран один,
маршрутизации нет. Стили — **только SCSS** (`sass-embedded`), точка входа
`ui/styles/index.scss`:

```
ui/styles/
  _tokens.scss     размеры и радиусы переменными Sass, цвета — переменными CSS
  _mixins.scss     surface, control, disabled
  _base.scss       сброс и типографика
  blocks/          по блоку интерфейса: layout, status, composer, board, note
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
- **Symfony 8 / DoctrineBundle 3**: часть опций конфига удалена
  (`use_savepoints`, `auto_generate_proxy_classes`). Симптом — «Unrecognized option».
- Зависимости и миграции накатывает entrypoint контейнера php при старте;
  отдельных шагов после `make up` не требуется.
- Правки PHP подхватываются сразу (bind-mount + `opcache.validate_timestamps`),
  но после `composer require` или правки `config/services.yaml` бывает нужен
  `make cache-clear`.

## Конвенции

- Комментарии и сообщения об ошибках — по-русски, включая имена тестовых методов.
  Комментарий объясняет **почему**, а не пересказывает код.
- Стили — классы в SCSS, а не инлайн и не утилиты в разметке. Исключение уже
  в коде: цвет стикера приходит из данных, поэтому он идёт через `style`.
  Величины не хардкодить — в `_tokens.scss`.
- Новый сценарий: DTO команды + хендлер в `Application/Note/Command/<Имя>/`,
  вызов из контроллера напрямую (командной шины нет — хендлеры инжектятся как сервисы).
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
юнит-тесты домена; интерфейс — один экран доски без маршрутизации.

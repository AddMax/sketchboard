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
make test                  # тесты: 34 на бэке, 25 на фронте
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

Один тест:

```bash
docker compose exec -T php vendor/bin/phpunit --filter testMovingToSamePlaceRecordsNoEvent
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
| `UI/` | разобрать запрос → вызвать хендлер → отдать JSON. **Один маршрут — один класс** с `__invoke`, каталоги по контексту: `Controller/Note/`, `Controller/Drawing/`, `Controller/Realtime/` |

- Инварианты стерегут объекты-значения (`NoteText`, `Position`, `Color`, `Author`),
  а не валидатор: `symfony/validator` намеренно не установлен. VO бросают
  `Domain\Shared\InvalidArgument`, а `DomainExceptionListener` превращает его в 422,
  `NoteNotFound` — в 404. Контроллеры не ловят исключения сами.
- Агрегат копит события (`recordThat`) и отдаёт их через `releaseEvents()`.
  Публикует их **хендлер после успешной записи**, не репозиторий и не контроллер.
  В `DeleteNoteHandler` события снимаются до `remove()` — после удаления объект уже не наш.
- Агрегатов два: `Note` и `Drawing` (рисунок заметки, ключ общий с заметкой).
  Рисунок хранится одним JSON-документом (`StrokesType`) и всегда заменяется
  целиком — клиент шлёт полный список штрихов, а не дельту. Событий он не
  порождает и по realtime-каналу не летает: может весить сотни килобайт.
  Связь между агрегатами держит `DeleteNoteHandler`, а не внешний ключ.
- Маппинг Doctrine — XML в `Infrastructure/Persistence/Doctrine/Mapping/`.
  Имя файла кодирует FQCN относительно префикса `App\Domain`:
  `App\Domain\Note\Note` → `Note.Note.orm.xml`. Ошибиться легко, симптом —
  «Class 'App\Domain\Note' does not exist».
- Документация API — атрибуты `OpenApi\Attributes` (`OA\Get`, `OA\RequestBody`,
  `OA\Response`…) на `__invoke` контроллера, тег `OA\Tag` на классе. Путь и метод
  Nelmio берёт из `#[Route]`, дублировать не нужно. Переиспользуемые схемы
  (`Note`, `Drawing`, `ValidationError`…) — в `config/packages/nelmio_api_doc.yaml`,
  ссылки через `ref: '#/components/schemas/…'`; на read-моделях атрибутов нет,
  чтобы Application не зависел от библиотеки документации. Меняя `NoteView` или
  `DrawingView`, правьте и схему. `OA\Schema` на методе контроллера нельзя —
  Nelmio падает с «root annotation … is not allowed».
- Форма realtime-сообщения (`{event, payload, at}`) описана в
  `Infrastructure/Realtime/DomainEventSerializer` — это контракт с фронтендом,
  а не часть домена. Меняя её, правьте `frontend/src/domain/realtime/RealtimeEvent.ts`.

### Фронтенд (`frontend/src/`)

Те же слои: `domain/` (правила заметки, чистая функция `applyRealtimeEvent`,
типы рисунка и математика вьюпорта `Viewport.ts`), `application/` (порты
`NoteRepository`, `DrawingRepository`, `RealtimeChannel`, `RealtimeAccessProvider`
и сценарии `useBoard`, `useDrawing`), `infrastructure/` (адаптеры + `container.ts` —
единственное место, где выбираются реализации), `ui/` (компоненты, страницы,
маршрутизация).

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
  blocks/          по блоку интерфейса: layout, status, composer, board, note, drawing, canvas
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
- Полотно `ui/components/canvas/InfiniteCanvas.tsx` держит вьюпорт, текущий
  штрих и размеры в ref, а не в стейте: во время движения мыши сегменты идут
  прямо в контекст, React рендерится только по завершении штриха. Сохранение —
  через `useDebouncedCallback` (1,5 с после последнего штриха, flush при
  размонтировании). Пределы кисти и числа линий совпадают с `Domain\Drawing`.

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
юнит-тесты домена, разбора маршрутов и математики вьюпорта; рисунок не
синхронизируется между участниками в realtime — каждый видит его при открытии
страницы, а побеждает последнее сохранение.

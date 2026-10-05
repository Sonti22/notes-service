# Заметки — мини-сервис

Полное решение тестового задания: RESTful API на Laravel, интерфейс на Vue, SQLite, PHPUnit, OpenAPI / Swagger UI и Docker Compose.

## Быстрый запуск

Установите Git и Docker Desktop (либо Docker Engine с Compose v2). Docker должен быть запущен. После клонирования репозитория перейдите в его корень и выполните:

```sh
docker compose up -d
```

При первом запуске Compose автоматически собирает образ, устанавливает зависимости, собирает Vue, создаёт ключ приложения и запускает миграции. Дополнительные команды или копирование `.env` не нужны. Первый запуск требует интернета и может занять несколько минут.

- Интерфейс: http://localhost:8080
- API: http://localhost:8080/api/notes
- Swagger UI: http://localhost:8080/docs/
- OpenAPI JSON: http://localhost:8080/docs/openapi.json
- Проверка здоровья: http://localhost:8080/up

Приложение привязано к localhost. Авторизация не входит в задание: все заметки общие. Для публичного развёртывания дополнительно нужны аутентификация, HTTPS и ограничение частоты запросов. Swagger UI и необязательный веб-шрифт загружаются с CDN; само приложение и API после сборки работают без доступа к интернету.

```sh
docker compose ps                   # состояние и healthcheck
docker compose logs -f app          # логи
docker compose up -d --build        # пересобрать после изменения кода
docker compose down                # остановить; заметки сохраняются
```

Если порт занят, создайте в корне `.env` с `APP_PORT=8081` и повторите запуск. Данные и ключ сохраняются в именованном volume `notes-data`. `docker compose down -v` удаляет заметки и ключ безвозвратно; используйте только для сознательного сброса тестового экземпляра. Для резервной копии остановите приложение и скопируйте `/data/database.sqlite` из контейнера.

## Возможности интерфейса

Создание, просмотр, редактирование и удаление с подтверждением. Поиск по заголовку и тексту, пагинация, счётчик заметок, пустое состояние, индикаторы загрузки, ошибки API и полей формы. Адаптивная сетка для телефона и компьютера. Текст выводится средствами Vue с экранированием HTML.

## API

Все ответы и ошибки — JSON, кроме успешного удаления (204 без тела). Для запросов с телом передавайте `Content-Type: application/json`. Ответ заметки обёрнут в `data`, список — в `data`, `links`, `meta`.

| Метод | Путь | Назначение | Успех |
|---|---|---|---|
| POST | `/api/notes` | Создание | 201 + Location |
| GET | `/api/notes` | Список, новые первыми | 200 |
| GET | `/api/notes/{id}` | Просмотр | 200 |
| PUT / PATCH | `/api/notes/{id}` | Полное / частичное изменение | 200 |
| DELETE | `/api/notes/{id}` | Удаление | 204 |

### Валидация каждого метода

- Создание: `title` — обязательная строка 1–200 символов, `content` — обязательная строка 1–10000 символов.
- PUT требует оба поля. PATCH требует хотя бы одно из них; переданное поле не может быть пустым. Пробелы по краям обрезаются. `null`, массивы и неверные типы запрещены. Неизвестные поля тела игнорируются и не записываются в модель.
- Список: `page` — целое 1–1000000 (по умолчанию 1), `per_page` — целое 1–100 (по умолчанию 12), `search` — строка до 200 символов. Пустой поиск не фильтрует. `%` и `_` рассматриваются буквально. SQLite LIKE не учитывает регистр ASCII, но учитывает регистр кириллицы.
- Просмотр, изменение и удаление: ID должен быть положительным целым до 18 цифр; некорректный ID или отсутствующая запись возвращает 404.
- Ошибка данных или параметров списка: 422 с `message` и `errors`. Несуществующий маршрут: 404; неверный HTTP-метод: 405.

### Примеры (bash)

```sh
curl -i -X POST http://localhost:8080/api/notes \
  -H 'Content-Type: application/json' \
  -d '{"title":"Первая идея","content":"Сделать полезный сервис"}'

curl 'http://localhost:8080/api/notes?per_page=10&page=1'
curl http://localhost:8080/api/notes/1
curl -X PATCH http://localhost:8080/api/notes/1 \
  -H 'Content-Type: application/json' -d '{"title":"Обновлённая идея"}'
curl -i -X DELETE http://localhost:8080/api/notes/1
```

В PowerShell можно использовать `Invoke-RestMethod`:

```powershell
$body = @{title='Первая идея'; content='Сделать полезный сервис'} | ConvertTo-Json
Invoke-RestMethod http://localhost:8080/api/notes -Method Post -ContentType 'application/json; charset=utf-8' -Body ([Text.Encoding]::UTF8.GetBytes($body))
```

## Тесты и качество

Запуск API-тестов без локального PHP, из корня (для PowerShell и bash):

```sh
docker run --rm -v "./backend:/app" composer:2 composer install --no-interaction
docker run --rm -v "./backend:/app" composer:2 php artisan test
docker run --rm -v "./backend:/app" composer:2 vendor/bin/pint --test
```

Тесты работают с SQLite `:memory:` и не изменяют рабочую базу. Проверяются полный CRUD, русскоязычные строки, границы длины, пустые/неверные поля, PATCH и PUT, защита от записи посторонних полей, пагинация, поиск, SQL-подобный ввод, неверные ID и JSON-ошибки без заголовка Accept. GitHub Actions запускает PHPUnit, Pint и сборку Vue.

Дополнительная проверка работающего контейнера в PowerShell: `./scripts/smoke-test.ps1` (либо `./scripts/smoke-test.ps1 -BaseUrl http://localhost:8081` для другого порта). Скрипт проверяет healthcheck, HTML-вход Vue, OpenAPI и CRUD по HTTP; созданную им тестовую заметку удаляет.

```sh
cd frontend
npm ci
npm run build
```

## Локальная разработка без Docker

Нужны PHP 8.4+, Composer 2, расширения PDO SQLite, mbstring, DOM/XML и Node.js 22.12+.

```sh
cd backend
composer install
cp .env.example .env
php artisan key:generate
php -r "touch('database/database.sqlite');"
php artisan migrate
php artisan serve
```

В отдельном терминале `cd frontend && npm ci && npm run dev`. Vite проксирует `/api` на `http://127.0.0.1:8000`. При работе через Docker Apache обслуживает UI и API на одном origin, CORS не нужен.

## Структура и решения

```text
backend/app/Http/Controllers/NoteController.php   CRUD и поиск
backend/app/Http/Requests/                      правила валидации
backend/app/Http/Resources/NoteResource.php       JSON-контракт
backend/app/Models/Note.php                      Eloquent-модель
backend/database/migrations/                    схема базы
backend/tests/Feature/NoteApiTest.php            интеграционные API-тесты
backend/public/docs/                            Swagger UI + OpenAPI
frontend/src/                                  Vue-интерфейс
docker/                                        Apache и старт контейнера
compose.yaml                                   запуск и постоянное хранилище
```

SQLite подходит для маленького сервиса и исключает отдельный сервер БД. Запросы используют параметризацию, поля записи ограничены `$fillable` и валидированными данными. Порядок списка стабилен по ID. Миграции выполняются идемпотентно при старте. Зависимости зафиксированы `composer.lock` и `package-lock.json`. Контракт OpenAPI можно пересоздать: `python scripts/generate-openapi.py`.

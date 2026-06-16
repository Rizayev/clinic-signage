# Clinic Digital Signage Platform

Централизованная система управления контентом для ТВ/мониторов клиники.
Web-панель (Vue 3 SPA) + REST API (Laravel) + браузерный плеер для экранов.

ТЗ: см. [`info.md`](info.md). Контракт реализации (схема БД, конвенции, API): [`docs/CONTRACT.md`](docs/CONTRACT.md).

---

## Стек

| Слой | Технология |
| --- | --- |
| Backend | Laravel 13 (PHP 8.3), Sanctum (token auth) |
| Admin panel | Vue 3 SPA (`<script setup>`), Vue Router, Pinia, Tailwind 4, Vite |
| Player | Vue 3 (отдельный bundle), отдаётся на `/player` |
| Realtime | Laravel Reverb (WebSocket) + Laravel Echo — мгновенный push изменений; синхронизация воспроизведения по общим часам |
| БД | SQLite (дев) / MySQL (прод) — переключается через `.env` |
| Медиа | Laravel Storage (`public` disk) + FFmpeg (длительность, превью, метаданные) |
| Auth устройств | кастомный `auth.device` middleware (per-device `api_token`) |

> Android Kotlin/ExoPlayer плеер — отдельный этап (см. `info.md` §7). В этой версии экраны работают через **браузерный плеер** (`/player`), который ходит в тот же Player API.

---

## Требования

PHP 8.3+, Composer, Node 18+, FFmpeg (опционально — без него медиа работает, но без авто-превью/длительности видео).

## Установка

```bash
composer install
npm install
cp .env.example .env          # если .env ещё нет
php artisan key:generate
php artisan storage:link
php artisan migrate:fresh --seed
npm run build
```

## Запуск

Нужно два процесса: HTTP-сервер и **Reverb** (WebSocket-сервер для realtime).

```bash
# терминал 1 — WebSocket-сервер (realtime push + синхронизация)
php artisan reverb:start --port=8080

# терминал 2 — приложение
npm run build
php artisan serve --port=8010      # порт 8000 может быть занят — см. ниже
# (для разработки вместо build: npm run dev в отдельном терминале)
```

Без запущенного Reverb всё работает, но мгновенный push отключается — плеер падает на опрос `/state` раз в 15с. Ключи Reverb (`REVERB_*`, `VITE_REVERB_*`) и `BROADCAST_CONNECTION=reverb` уже в `.env`.

- Панель администратора: **http://localhost:8010/**
- Плеер (экран): **http://localhost:8010/player**

> Порт 8000 на этой машине занят другим проектом — примеры используют **8010**. Любой свободный порт подойдёт; при смене порта обнови `APP_URL` в `.env`, чтобы ссылки на медиа (`Storage::url`) были корректными.

---

## Демо-доступы (после `migrate:fresh --seed`)

| Роль | Email | Пароль |
| --- | --- | --- |
| Super Admin | super@clinic.local | password |
| Admin филиала | admin@clinic.local | password |
| Контент-менеджер | content@clinic.local | password |
| Наблюдатель | viewer@clinic.local | password |

Сид создаёт: 1 филиал «Главная клиника», 3 зоны, 3 устройства (TV-01 с кодом привязки **`A7K9-22`**, TV-02 online, TV-03 offline), активную бегущую строку, заготовку срочного сообщения и пустой плейлист.

---

## Демо-сценарий (end-to-end)

1. Войти как `super@clinic.local`.
2. **Медиатека** → загрузить MP4/JPG/PNG (превью и длительность определяются через FFmpeg).
3. **Плейлисты** → открыть «Зал ожидания — утро» → добавить медиа, задать длительность/переход.
4. **Устройства** → у TV-01 «Назначить плейлист».
5. Открыть **http://localhost:8010/player** в новой вкладке → ввести код **`A7K9-22`** → экран привязывается и крутит плейлист + бегущую строку.
6. **Срочные** → «Показать срочно» → сообщение появляется поверх контента на экране (приоритет выше всего).

---

## Реальное время и синхронизация

**Мгновенные изменения (WebSocket, Laravel Reverb).** Любое изменение контента (правка бегущей строки, цвета, активация/отключение срочного, смена плейлиста) рассылается всем плеерам через WebSocket. Реализовано: модели `Ticker / EmergencyMessage / Playlist / PlaylistItem / PlaylistAssignment` при сохранении/удалении (трейт `BroadcastsContentChanges`) бросают событие `ContentChanged` в публичный канал `signage`; плеер слушает его через Echo и при сигнале перечитывает `/state` → `/config`. Замер: срочное появляется/снимается на экране за **~0.1 секунды**. Если WebSocket недоступен — плеер автоматически опрашивает `/state` раз в 15 секунд (fallback), плюс полный `/config` раз в 60с.

**Синхронное воспроизведение (общие часы).** Все экраны с одним плейлистом играют **в ногу** (~0.1–0.3с). Плеер синхронизирует часы с сервером через `GET /api/player/time` (NTP-стиль, компенсация round-trip) и вычисляет текущий ролик и смещение внутри него детерминированно от общего времени: `позиция = (серверное_время) mod длительность_цикла`. Видео при необходимости перематывается к расчётной позиции (коррекция дрейфа > 0.4с). Никакого «ведущего» экрана — каждый сам считает от общих часов. Проверено: два разных устройства показывают один кадр одновременно.

> Это **софтовая** синхронизация (~десятки–сотни мс). Пиксель-в-пиксель видеостена (один кадр на плитке экранов) требует hardware genlock и в объём не входит.

При обрыве сети плеер продолжает играть из кэша и показывает индикатор «Нет связи»; в углу (по движению мыши) — имя устройства, статус `live`/`опрос`, кнопки «Полный экран» и «Отвязать».

## Роли и доступ

| Действие | super_admin | branch_admin | content_manager | viewer |
| --- | :--: | :--: | :--: | :--: |
| Просмотр всего | ✅ | ✅ | ✅ | ✅ |
| Медиа, плейлисты, бегущие строки, срочные | ✅ | ✅ | ✅ | — |
| Филиалы, зоны, устройства | ✅ | ✅ | — | — |
| Пользователи | ✅ | — | — | — |

Реализовано middleware `write:<roles>` (GET — всем авторизованным, запись — по ролям) и `role:super_admin` для пользователей.

---

## API

### Player API
| Метод | Endpoint | Auth | Назначение |
| --- | --- | --- | --- |
| POST | `/api/player/register` | — | привязка по коду → выдаёт device token |
| GET | `/api/player/time` | — | серверное время (синхронизация часов плеера) |
| POST | `/api/player/heartbeat` | device | статус, last_seen, текущий медиа |
| GET | `/api/player/config` | device | резолв плейлиста (приоритеты) + строка + срочное |
| GET | `/api/player/state` | device | лёгкая ревизия (для обнаружения изменений) |
| POST | `/api/player/log` | device | логи устройства |

### Admin API
Auth: `Authorization: Bearer <token>` (получается через `POST /api/login`).
`apiResource` для: `branches, zones, devices, media, playlists, tickers, emergency-messages, users`.
Плюс: `/dashboard`, `/devices/pair`, `/devices/{id}/assign-playlist`, `/devices/{id}/logs`, `/playlists/{id}/items|reorder|assign`, `/emergency-messages/{id}/activate|deactivate`, `/media/{id}/replace`.

Полная карта: `php artisan route:list --path=api`.

### Приоритет показа (резолв плейлиста на устройстве)
`срочное сообщение → плейлист устройства → зоны → филиала → all → дефолтный (current_playlist_id)`.
Внутри тира — наибольший `priority`. Реализовано в `app/Services/PlaylistResolver.php`.

---

## Структура

```
app/
  Http/Controllers/Api/Admin/   # CRUD-контроллеры панели
  Http/Controllers/Api/Player/  # PlayerController (register/heartbeat/config/log)
  Http/Middleware/              # auth.device, role, write, ForceJsonApi
  Http/Resources/               # API-ресурсы (JSON shape)
  Services/                     # MediaService (FFmpeg), PlaylistResolver (приоритеты)
  Models/                       # Branch, Zone, Device, Media, Playlist, PlaylistItem, ...
database/migrations/            # 12 таблиц сигнейджа
database/seeders/               # демо-данные
resources/js/
  pages/                        # 12 страниц панели (Vue)
  components/ui/                # Btn, Card, Modal, StatusDot, PageHeader
  components/player/PlayerApp.vue  # браузерный плеер
  router, stores, services      # инфраструктура SPA
routes/api.php  routes/web.php
docs/CONTRACT.md                # контракт реализации
```

## Что дальше (не входит в этот MVP)

Нативный Android-плеер (Kotlin + ExoPlayer/Media3) с оффлайн-кэшем и автозапуском, визуальная drag&drop схема клиники, конструктор баннеров, WebSocket-пуш конфигурации (сейчас polling каждые 30с), очереди FFmpeg-конвертации, 2FA. См. `info.md` §10, §19.

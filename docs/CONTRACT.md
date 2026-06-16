# BUILD CONTRACT — Clinic Digital Signage Platform

Single source of truth. Every agent MUST follow these exact names, types, and conventions.
Stack: Laravel 13 (PHP 8.3) API + Vue 3 SPA (Vite, Tailwind 4) + Sanctum (token auth) + SQLite dev / MySQL prod + FFmpeg.

Auth model: **Bearer token** (Sanctum personal access tokens), NOT cookie/SPA mode. Login returns a token; SPA stores it and sends `Authorization: Bearer <token>`. Devices authenticate with their own `api_token` via custom `auth.device` middleware.

---

## ROLES (User.role enum string)
- `super_admin`  — full access, all branches
- `branch_admin` — manage own branch only
- `content_manager` — media + playlists, no device/user delete
- `viewer` — read only

Gate convention: a simple middleware `role:super_admin,branch_admin` checks `in_array($user->role, $roles)`. Super admin passes everything.

---

## DATABASE SCHEMA (exact column names)

### users (add columns via migration `add_signage_fields_to_users_table`)
Existing: id, name, email, password, timestamps. ADD:
- `role` string default 'viewer'
- `branch_id` foreignId nullable (FK branches, nullOnDelete)
- `phone` string nullable
- `is_active` boolean default true

### branches
- id
- `name` string
- `address` string nullable
- `timezone` string default 'Asia/Baku'
- `status` string default 'active'   (active|disabled)
- timestamps

### zones
- id
- `branch_id` foreignId (FK branches, cascadeOnDelete)
- `name` string
- `description` string nullable
- `sort_order` integer default 0
- timestamps

### devices
- id
- `branch_id` foreignId (FK branches, cascadeOnDelete)
- `zone_id` foreignId nullable (FK zones, nullOnDelete)
- `name` string
- `device_code` string unique          (internal slug/code, e.g. TV-01)
- `pairing_code` string nullable unique (short code shown on screen, e.g. A7K9-22; cleared after pairing)
- `api_token` string nullable unique    (random 64-char token for device auth; store plain for MVP)
- `platform` string nullable            (android, browser, windows, ...)
- `device_type` string default 'android_tv' (android_tv|android_box|browser_player|windows_player|raspberry_player)
- `ip_address` string nullable
- `mac_address` string nullable
- `android_id` string nullable
- `screen_orientation` string default 'landscape' (landscape|portrait)
- `resolution` string nullable          (e.g. 1920x1080)
- `status` string default 'offline'     (online|offline|error|updating|disabled)
- `last_seen_at` timestamp nullable
- `app_version` string nullable
- `current_playlist_id` foreignId nullable (FK playlists, nullOnDelete)
- `free_storage` bigInteger nullable
- `settings` json nullable
- timestamps

### media
- id
- `title` string
- `type` string                          (video|image|audio|html|text)
- `file_path` string nullable            (relative path in storage/app/public)
- `thumbnail_path` string nullable
- `mime_type` string nullable
- `size` bigInteger nullable
- `duration` integer nullable            (seconds)
- `width` integer nullable
- `height` integer nullable
- `checksum` string nullable             (sha256)
- `category` string nullable
- `status` string default 'active'       (active|inactive|processing)
- `created_by` foreignId nullable (FK users, nullOnDelete)
- timestamps

### playlists
- id
- `branch_id` foreignId nullable (FK branches, cascadeOnDelete)
- `name` string
- `description` string nullable
- `status` string default 'active'       (active|inactive)
- `version` integer default 1            (bump on any change; players compare)
- `created_by` foreignId nullable (FK users, nullOnDelete)
- timestamps

### playlist_items
- id
- `playlist_id` foreignId (FK playlists, cascadeOnDelete)
- `media_id` foreignId (FK media, cascadeOnDelete)
- `sort_order` integer default 0
- `duration_seconds` integer nullable     (override; null = media.duration or default 10)
- `transition_effect` string default 'none' (none|fade|slide_left|slide_right|zoom|crossfade)
- `start_date` date nullable
- `end_date` date nullable
- `start_time` time nullable
- `end_time` time nullable
- `days_of_week` json nullable            (array of 1..7, 1=Mon)
- `is_active` boolean default true
- timestamps

### playlist_assignments
- id
- `playlist_id` foreignId (FK playlists, cascadeOnDelete)
- `target_type` string                    (device|zone|branch|all)
- `target_id` unsignedBigInteger nullable (null when target_type=all)
- `priority` integer default 0            (higher wins; see resolution order)
- `is_active` boolean default true
- timestamps

### tickers
- id
- `branch_id` foreignId nullable (FK branches, cascadeOnDelete)
- `title` string nullable
- `text` text
- `target_type` string default 'all'      (device|zone|branch|all)
- `target_id` unsignedBigInteger nullable
- `position` string default 'bottom'      (top|bottom)
- `speed` integer default 60              (px/sec)
- `font_size` integer default 28
- `text_color` string default '#ffffff'
- `background_color` string default '#000000'
- `opacity` decimal(3,2) default 0.80
- `start_date` date nullable
- `end_date` date nullable
- `start_time` time nullable
- `end_time` time nullable
- `is_active` boolean default true
- timestamps

### emergency_messages
- id
- `title` string nullable
- `text` text
- `target_type` string default 'all'      (device|zone|branch|all)
- `target_id` unsignedBigInteger nullable
- `duration_seconds` integer nullable     (auto-stop after; null = manual)
- `background_color` string default '#b00020'
- `text_color` string default '#ffffff'
- `started_at` timestamp nullable
- `ends_at` timestamp nullable
- `is_active` boolean default false
- `created_by` foreignId nullable (FK users, nullOnDelete)
- timestamps

### device_logs
- id
- `device_id` foreignId (FK devices, cascadeOnDelete)
- `level` string default 'info'           (info|warning|error)
- `event` string
- `message` text nullable
- `payload` json nullable
- `created_at` timestamp (use `$table->timestamp('created_at')->nullable()`; no updated_at)

### audit_logs
- id
- `user_id` foreignId nullable (FK users, nullOnDelete)
- `action` string
- `entity_type` string nullable
- `entity_id` unsignedBigInteger nullable
- `old_values` json nullable
- `new_values` json nullable
- `ip_address` string nullable
- `created_at` timestamp nullable (no updated_at)

---

## MODELS (app/Models, singular PascalCase)
Branch, Zone, Device, Media, Playlist, PlaylistItem, PlaylistAssignment, Ticker, EmergencyMessage, DeviceLog, AuditLog. User extended.

Relationships:
- Branch hasMany zones, devices, playlists
- Zone belongsTo branch; hasMany devices
- Device belongsTo branch, zone, currentPlaylist(Playlist, current_playlist_id); hasMany logs(DeviceLog)
- Media belongsTo creator(User, created_by); hasMany playlistItems
- Playlist belongsTo branch, creator; hasMany items(PlaylistItem ordered by sort_order), assignments(PlaylistAssignment)
- PlaylistItem belongsTo playlist, media
- PlaylistAssignment belongsTo playlist
- Ticker belongsTo branch
- EmergencyMessage belongsTo creator
- DeviceLog belongsTo device
- User belongsTo branch; HasApiTokens, hasMany createdMedia

Casts: json columns => 'array'; date/time/timestamp columns => proper casts; booleans => 'boolean'; opacity => 'decimal:2'.
All models: `protected $guarded = [];` (mass-assign open; MVP). Add `$casts` as needed.
DeviceLog & AuditLog: `public $timestamps = false;` and manage created_at manually (use `->latest('created_at')`).

---

## BACKEND CONTROLLERS

### Admin API — namespace App\Http\Controllers\Api\Admin
All under `auth:sanctum`. apiResource controllers (index/show/store/update/destroy) returning JSON via API Resources.
- AuthController: login(POST), logout(POST), me(GET)
- DashboardController: index(GET) — aggregate counts
- BranchController (apiResource)
- ZoneController (apiResource)
- DeviceController (apiResource) + pair(POST /devices/pair {pairing_code,name,zone_id,branch_id}) + assignPlaylist(POST /devices/{device}/assign-playlist {playlist_id}) + logs(GET /devices/{device}/logs)
- MediaController (apiResource; store handles multipart upload -> MediaService) + replace(POST /media/{media}/replace)
- PlaylistController (apiResource; show includes items+media) + items CRUD: storeItem(POST /playlists/{playlist}/items), updateItem(PUT /playlists/{playlist}/items/{item}), destroyItem(DELETE), reorder(POST /playlists/{playlist}/reorder {order:[ids]}) + assign(POST /playlists/{playlist}/assign {target_type,target_id,priority})
- TickerController (apiResource)
- EmergencyMessageController (apiResource) + activate(POST /emergency-messages/{em}/activate), deactivate(POST /emergency-messages/{em}/deactivate)
- UserController (apiResource)

### Player API — namespace App\Http\Controllers\Api\Player
- PlayerController:
  - register(POST /player/register)  — public. body {pairing_code, platform, app_version, android_id, screen_resolution}. Finds device by pairing_code, generates api_token, returns {success, device_id, token, name}.
  - heartbeat(POST /player/heartbeat) — auth.device. updates last_seen_at, status, free_storage, current_playlist_id, ip. returns {ok, config_version}.
  - config(GET /player/config) — auth.device. returns resolved playlist (priority order) + ticker + active emergency. Shape in API section below.
  - log(POST /player/log) — auth.device. inserts device_log.

### Services — app/Services
- MediaService: handleUpload(UploadedFile $file, array $meta): Media — stores file to public disk, runs FFmpeg (ffprobe) for video duration + ffmpeg thumbnail, computes sha256, fills width/height/mime/size. Degrade gracefully if ffmpeg/ffprobe missing (wrap in try/catch, null out).
- PlaylistResolver: resolveForDevice(Device $device): ?Playlist — priority: (1) direct device assignment, (2) zone assignment, (3) branch assignment, (4) target_type=all assignment, (5) device.current_playlist_id fallback. Highest `priority` within a tier wins. Only is_active assignments + active playlists.

### Form Requests — app/Http/Requests (one per store/update where validation matters). Keep simple.

### API Resources — app/Http/Resources: BranchResource, ZoneResource, DeviceResource, MediaResource, PlaylistResource (with items), PlaylistItemResource, TickerResource, EmergencyMessageResource, UserResource. camelCase or snake — use snake_case keys matching columns + add `thumbnail_url`, `file_url` (Storage::url) for media.

### auth.device middleware — app/Http/Middleware/AuthenticateDevice.php
Reads Bearer token, finds Device by api_token, 401 if none, binds `$request->setUserResolver`/attribute `device`. Register alias `auth.device` in bootstrap/app.php.

### role middleware — app/Http/Middleware/EnsureRole.php, alias `role`. Usage `->middleware('role:super_admin,branch_admin')`.

---

## API ROUTES (routes/api.php) — MAIN THREAD writes this. Controllers must match signatures above.

## PLAYER /config RESPONSE SHAPE (exact)
```json
{
  "device": {"id":15,"name":"TV-01","orientation":"landscape"},
  "playlist": {
    "id":7,"version":23,
    "items":[
      {"id":101,"type":"video","url":"http://.../v.mp4","duration":30,"transition":"fade","checksum":"sha256..."}
    ]
  },
  "ticker": {"enabled":true,"text":"...","position":"bottom","speed":60,"font_size":28,"text_color":"#fff","background_color":"#000","opacity":0.8},
  "emergency": {"active":false,"text":null,"background_color":"#b00020","text_color":"#fff"}
}
```
If no playlist resolved: `"playlist": null`. Player shows fallback.

---

## FRONTEND (Vue 3 SPA) — resources/js

MAIN THREAD writes infra: app.js, App.vue, router/index.js, stores/auth.js, services/api.js, components/AppLayout.vue, components/ui/* (Card, Btn, Modal, Table, StatusDot), css.

Pages live in `resources/js/pages/` (one .vue each). Agents write these. Each page uses the shared `api` service (default export axios instance with baseURL '/api' + Bearer interceptor) and Tailwind classes. Use `<script setup>`.

Pages (exact filenames):
- Login.vue            (/login)
- Dashboard.vue        (/)
- Branches.vue         (/branches)
- Zones.vue            (/zones)
- Devices.vue          (/devices)        — table + pair-device modal + assign playlist
- DeviceDetail.vue     (/devices/:id)    — card with status, logs
- Media.vue            (/media)          — upload + grid + preview
- Playlists.vue        (/playlists)      — list + create
- PlaylistEditor.vue   (/playlists/:id)  — items list, add media, reorder (up/down buttons ok), assign targets
- Tickers.vue          (/tickers)
- Emergency.vue        (/emergency)      — list + "show now"
- Users.vue            (/users)

API service contract (services/api.js): `import api from '@/services/api'` then `api.get('/devices')`, etc. It injects Bearer from auth store/localStorage and redirects to /login on 401.
Auth store (stores/auth.js, pinia): state {user, token}; actions login(email,password), logout(), fetchMe(); persists token in localStorage key `signage_token`.
Router: history mode, guard — redirect to /login if no token (except /login). Layout wraps authed pages with sidebar nav (links to all pages) + topbar (user name, logout).

Tailwind 4 is active. Keep UI clean, dark sidebar, light content. RU labels in UI.

PLAYER FRONTEND (separate entry): MAIN THREAD writes resources/js/player.js + components/player/PlayerApp.vue. Browser player: polls /api/player/config every 30s, plays items in loop (video/image), shows ticker overlay + emergency overlay, registration screen (enter pairing flow is reverse — player shows pairing_code; for browser player we let user paste a device token via ?token= or a register form). Keep functional.

---

## CONVENTIONS
- PHP: 4-space indent, strict where natural, return API Resources or JSON.
- All admin list endpoints: support `?branch_id=` and `?q=` filters where sensible, paginate with `->paginate(20)` BUT return `{data:[...], meta:{...}}` (Laravel resource collection default is fine).
- Timestamps in ISO. 
- DO NOT run composer/npm/artisan from agents. ONLY write/edit files. Main thread runs migrations, seeders, build, tests.
- DO NOT edit shared files owned by main thread: routes/*.php, bootstrap/app.php, vite.config.js, resources/js/app.js, resources/js/player.js, resources/js/router/index.js, resources/js/services/api.js, resources/js/stores/auth.js, resources/js/App.vue, resources/js/components/AppLayout.vue. Create only the files assigned to you.

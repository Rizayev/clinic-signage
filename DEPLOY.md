# Deploy to Coolify

Clinic Signage = Laravel 13 API + Vue SPA + Reverb WebSocket + FFmpeg.
Runs as **3 services from one image**: `web`, `reverb`, `queue`. DB = Coolify-managed MySQL.

## Files added for deploy
- `Dockerfile` — multi-stage: Vite asset build → PHP 8.3 (php-fpm + nginx + ffmpeg + supervisor).
- `docker-compose.yml` — 3 services (web / reverb / queue), shared `storage-app` volume.
- `docker/nginx.conf`, `docker/php.ini` (512M uploads), `docker/supervisord.conf`, `docker/entrypoint.sh`.
- `.env.coolify.example` — env template.
- `.dockerignore`.

## Steps

### 1. MySQL
Coolify → **+ New → Database → MySQL**. Note internal host, db name, user, password.

### 2. App (Docker Compose)
Coolify → **+ New → Application → Docker Compose**, point at this repo.
Coolify auto-detects `docker-compose.yml`.

### 3. Environment variables
Copy everything from `.env.coolify.example` into Coolify's env editor. Fill:
- `APP_URL=https://<main-domain>`
- `DB_HOST/DB_DATABASE/DB_USERNAME/DB_PASSWORD` from step 1
- `REVERB_APP_KEY`, `REVERB_APP_SECRET` — generate NEW random values (repo ones are leaked)
- `VITE_REVERB_HOST=reverb.<main-domain>` (and PORT=443, SCHEME=https)
- `APP_KEY` — keep the fresh one in the example, or run `php artisan key:generate --show`

> `VITE_REVERB_*` are compiled into the JS during build. Changing them later **requires a rebuild**, not just a restart.

### 4. Domains (per service in Coolify)
- `web` service → main domain → container port **80**
- `reverb` service → `reverb.<main-domain>` → container port **8080**
  (Coolify/Traefik upgrades to WSS automatically.)
- `queue` service → no domain.

### 5. Persistent storage
Volume `storage-app` (in compose) holds uploaded media. Already mounted on `web` + `queue`. Verify it's marked persistent in Coolify so redeploys don't wipe media.

### 6. Deploy
Click **Deploy**. On boot the `web` container:
1. waits for MySQL,
2. runs `php artisan migrate --force` (**never** `migrate:fresh`),
3. `storage:link`, then caches config/routes/views,
4. starts nginx + php-fpm.

### 7. First-run demo data (optional)
To load demo users/devices once (empty DB only), run in Coolify's `web` terminal:
```
php artisan db:seed --force
```
Demo logins: `super@clinic.local` / `password`. Skip if you want a clean prod DB.

## Verify
- `https://<main-domain>/up` → 200 (health).
- Login at `https://<main-domain>/`.
- Player at `https://<main-domain>/player`.
- Upload MP4 in Медиатека → thumbnail + duration appear ⇒ FFmpeg OK.
- Change a ticker → player updates in ~0.1s ⇒ Reverb/WSS OK.
  If realtime fails, the player falls back to 15s polling — check `VITE_REVERB_*` build args + the reverb subdomain.

## Scaling notes
- Current cache/session/queue/reverb all use the DB (no Redis), per chosen setup. Fine for one instance.
- To scale `reverb` to multiple replicas later: add Redis + set `REVERB_SCALING_ENABLED=true`.
- Heavy concurrent uploads: bump `web` resources; FFmpeg runs inline in the request.

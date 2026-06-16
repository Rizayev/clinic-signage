# Deploy to Coolify

Clinic Signage = Laravel 13 API + Vue SPA + Reverb WebSocket + FFmpeg.
Runs as **4 services from one image** in a single Docker Compose app:
`web` (nginx+php-fpm), `reverb` (WebSocket), `queue` (worker), `mysql`.

This is **deployed and live** (Coolify 4.1.2 @ 75.119.137.151, project GPS):
- Admin SPA: http://signage.75.119.137.151.sslip.io
- Player:    http://signage.75.119.137.151.sslip.io/player
- Reverb WS: http://signage-ws.75.119.137.151.sslip.io
- Repo:      https://github.com/Rizayev/clinic-signage (public, branch `main`)

## Files
- `Dockerfile` — multi-stage: Vite build → PHP 8.3 (php-fpm + nginx + ffmpeg + supervisor).
- `docker-compose.yml` — 4 services, one shared image, `storage-app` + `db-data` volumes.
- `docker/nginx.conf`, `docker/php.ini` (512M uploads), `docker/supervisord.conf`, `docker/entrypoint.sh`.
- `.env.coolify.example`, `.dockerignore`.

## How it works on Coolify (important specifics learned the hard way)

Coolify's auto-generated proxy config for **multi-service compose** is incomplete,
so the compose carries **explicit Traefik labels + the proxy network** itself:

1. **Container port in the domain.** A service is routed by container port via the
   `:port` in its domain. Web → port 80, Reverb → port 8080. Coolify needs this; the
   compose also sets it explicitly via labels.
2. **Manual Traefik labels** on `web` and `reverb` (`docker-compose.yml`):
   `traefik.enable`, router `rule=Host(...)`, `entryPoints=http`, `priority=1000`,
   and crucially `loadbalancer.server.port` (80 / 8080). Coolify's own labels omit the
   port for compose services → without these you get a Traefik **404**.
3. **`coolify` external network.** `web` + `reverb` join it (besides the app network)
   so the reverse proxy can actually reach them. Without it → 404 (proxy can't see them).
4. **No Docker `healthcheck` on `web`.** A healthcheck made Coolify withhold the
   container from the proxy → 404. Readiness is handled by the entrypoint's
   `wait_for_db` loop instead.
5. **Routes:** `route:cache` is NOT run — `routes/web.php` has a closure SPA catch-all
   that cannot be serialized. Only `config:cache` + `view:cache`.

## Recreating / redeploying from scratch (Coolify API)

1. Create a **Docker Compose** application from the public repo:
   `POST /api/v1/applications/public` with `build_pack=dockercompose`,
   `docker_compose_location=/docker-compose.yml`, and
   `docker_compose_domains=[{name:web,domain:"http://<host>:80"},{name:reverb,domain:"http://<ws-host>:8080"}]`
   (include the `:port`).
2. **Env vars** — set via `PATCH /applications/{uuid}/envs` (single key) or
   `POST /applications/{uuid}/envs` (note: create uses field `is_buildtime`, not
   `is_build_time`). The API can be flaky (hangs/locks) — set one key at a time and
   verify with a GET; for values that won't update, DELETE the entry then POST-create.
   Required (no compose default): `APP_KEY`, `APP_URL`, `DB_PASSWORD`, `DB_ROOT_PASSWORD`,
   `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`, `VITE_REVERB_HOST`.
   `VITE_REVERB_*` + `REVERB_APP_KEY` are **build-time** (baked into the player JS).
3. Deploy: `GET /api/v1/deploy?uuid={uuid}&force=true`.

## Env (current values are set in Coolify, not in the repo)
See `.env.coolify.example`. For this sslip.io deploy (HTTP, no TLS):
- `APP_URL=http://signage.75.119.137.151.sslip.io`
- `VITE_REVERB_HOST=signage-ws.75.119.137.151.sslip.io`, `VITE_REVERB_PORT=80`, `VITE_REVERB_SCHEME=http`
- Server-side publish to Reverb stays internal: `REVERB_HOST=reverb`, `REVERB_PORT=8080`, `REVERB_SCHEME=http`

## On boot
`web` waits for MySQL → `migrate --force` (**never** `migrate:fresh`) → `storage:link`
→ `config:cache` + `view:cache` → nginx+php-fpm. `queue` waits for migrations then
`queue:work`. `reverb` runs `reverb:start` on 0.0.0.0:8080.

## Verify
- `/up` → 200; SPA at `/`; player at `/player`.
- `POST /api/login {}` → 422 with Russian validation messages (DB + i18n OK).
- Upload MP4 in Медиатека → thumbnail + duration ⇒ FFmpeg OK.
- Change a ticker → player updates in ~0.1s ⇒ Reverb/WS OK (else falls back to 15s polling).

## Demo data (optional, empty DB only)
In Coolify's `web` container terminal: `php artisan db:seed --force`
(demo login `super@clinic.local` / `password`).

## Moving to a real domain + TLS later
Point DNS A-records at 75.119.137.151, then update the two domains (with `:80`/`:8080`),
the Traefik label hosts in `docker-compose.yml`, `APP_URL`, and the `VITE_REVERB_*`
(scheme `https`, port `443`) — and rebuild (VITE values are baked at build time).

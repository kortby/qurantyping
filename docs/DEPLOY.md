# Production deploy — qurantyping.com

Stack on the server: **Apache 2.4**, **MySQL** (`qurantyping`), **Redis** (`my-redis`
container, `127.0.0.1:6379`), **Soketi** (`quay.io/soketi/soketi:1.4-16-debian`
container, `127.0.0.1:6001`, shared with baytlink.fawatirs.com), **Supervisor**.
App root: `/var/www/html/qurantyping.com`, docroot `.../public`, PHP-FPM.

Broadcasting runs the **Pusher protocol against Soketi** (not Reverb, not pusher.com).

---

## 0. One-time server setup

### Soketi — client messages

Live race progress uses client whispers; without this flag they fall back to a
server relay (`/races/{key}/progress`), ~1 Hz instead of instant.

```bash
docker inspect soketi --format '{{json .Config.Env}}' | tr ',' '\n' | grep -i CLIENT_MESSAGES
```

If not `...ENABLE_CLIENT_MESSAGES=1`, recreate with the **same image** (baytlink shares
this container — it reconnects in ~1 s):

```bash
docker rm -f soketi
docker run -d --name soketi --restart unless-stopped \
  -p 127.0.0.1:6001:6001 -p 127.0.0.1:9601:9601 \
  -e SOKETI_DEFAULT_APP_ID=app-id \
  -e SOKETI_DEFAULT_APP_KEY=app-key \
  -e SOKETI_DEFAULT_APP_SECRET=app-secret \
  -e SOKETI_DEFAULT_APP_ENABLE_CLIENT_MESSAGES=1 \
  quay.io/soketi/soketi:1.4-16-debian
```

### Apache — proxy the Soketi WebSocket

Modules: `a2enmod proxy proxy_http proxy_wstunnel` (already enabled for baytlink).

Inside `<VirtualHost *:443>` of
`/etc/apache2/sites-available/qurantyping.com-le-ssl.conf`, after `</Directory>`
(identical to baytlink's working block):

```apache
        ProxyRequests Off
        ProxyPreserveHost On
        ProxyPass        /app/  http://127.0.0.1:6001/app/  upgrade=websocket
        ProxyPassReverse /app/  http://127.0.0.1:6001/app/
```

Do **not** proxy `/broadcasting/auth` — that's a Laravel route on PHP. Server-side
Pusher REST calls hit `127.0.0.1:6001` directly.

```bash
apachectl -t && systemctl reload apache2
```

**Verify the tunnel** (a plain `curl -I /app/...` returns 404 from Soketi — that is
normal; you must send an Upgrade handshake, and over HTTP/1.1 — Apache can't tunnel
WebSocket over HTTP/2, but browsers use `wss://` over 1.1 so that's fine):

```bash
KEY=$(head -c16 /dev/urandom | base64)
curl --http1.1 -s -i -N \
  -H "Connection: Upgrade" -H "Upgrade: websocket" \
  -H "Sec-WebSocket-Key: $KEY" -H "Sec-WebSocket-Version: 13" \
  "https://qurantyping.com/app/app-key?protocol=7&client=js&version=8.4.0" | head -1
# expect: HTTP/1.1 101 Switching Protocols
```

### Supervisor — queue worker

```bash
tee /etc/supervisor/conf.d/qurantyping-worker.conf >/dev/null <<'EOF'
[program:qurantyping-worker]
command=php /var/www/html/qurantyping.com/artisan queue:work redis --tries=3 --max-time=3600 --sleep=3
directory=/var/www/html/qurantyping.com
user=www-data
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=/var/www/html/qurantyping.com/storage/logs/worker.log
EOF
supervisorctl reread && supervisorctl update && supervisorctl restart qurantyping-worker
```

Worker runs `StartRaceJob` (flips a race countdown→racing + broadcasts `RaceStarted`).
Missing worker degrades rather than breaks races — clients start locally off `starts_at`.

### Cron — scheduler

```bash
( crontab -l 2>/dev/null; echo "* * * * * cd /var/www/html/qurantyping.com && php artisan schedule:run >> /dev/null 2>&1" ) | crontab -
```

Runs `races:reap` every minute (abandons stalled races).

---

## 1. `.env`

The pre-existing prod `.env` was on Laravel-10 keys (`BROADCAST_DRIVER`, `CACHE_DRIVER`,
`QUEUE_CONNECTION=sync`, `MIX_PUSHER_*`) and pointed at a paid **pusher.com** app.
Replace that section with:

```env
APP_VERSION='2.1.0'

BROADCAST_CONNECTION=pusher
QUEUE_CONNECTION=redis
CACHE_STORE=redis
CACHE_PREFIX=qurantyping_

PUSHER_APP_ID=app-id
PUSHER_APP_KEY=app-key
PUSHER_APP_SECRET=app-secret
PUSHER_HOST=127.0.0.1
PUSHER_PORT=6001
PUSHER_SCHEME=http
PUSHER_APP_CLUSTER=mt1

VITE_PUSHER_APP_KEY=app-key
VITE_PUSHER_HOST=qurantyping.com
VITE_PUSHER_PORT=443
VITE_PUSHER_SCHEME=https
```

Remove any leftover `BROADCAST_DRIVER` / `CACHE_DRIVER` / `MIX_PUSHER_*` lines.
`VITE_*` must be correct **before `npm run build`** — Vite inlines them at build time.

Redis notes: Laravel isolates cache (`REDIS_CACHE_DB=1`) from queue/default (`db 0`).
`CACHE_PREFIX` (or a distinct `APP_NAME`) keeps keys off baytlink's on the shared Redis.
PHP needs the `redis` (phpredis) extension — `php -m | grep redis`; otherwise
`REDIS_CLIENT=predis` + `composer require predis/predis`.

Optional knobs (all default sensibly): `ADMIN_EMAILS`, `HIFZ_CERTIFICATE_ACCURACY` (95),
`DRILLS_MIN_ATTEMPTS` / `DRILLS_WEAK_THRESHOLD` / `DRILLS_MAX_CHARS` /
`DRILLS_PASSAGE_AYAHS`, `TASHKIL_FEATURE` (false — the home-page diacritics toggle).

---

## 2. Release steps

```bash
cd /var/www/html/qurantyping.com

git pull
composer install --no-dev --optimize-autoloader     # pulls pusher/pusher-php-server ^7.2
npm ci && npm run build                              # bundles laravel-echo + pusher-js

php artisan config:clear
php artisan migrate --force
php artisan db:seed --class=QuranDivisionsSeeder     # one-time, idempotent (UPDATE only)
php artisan quran:import-punctuation                 # only if TASHKIL_FEATURE=true (~4 min)

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan cache:clear
php artisan queue:restart

chown -R www-data:www-data storage bootstrap/cache public/build
systemctl reload apache2
supervisorctl restart qurantyping-worker
```

### Migrations added since the last deploy (in order)

| Migration | Purpose |
|---|---|
| `add_last_login_at_to_users_table` | last-login column on /admin/users (backfills from `sessions`) |
| `add_handled_at_to_feedback_table` | admin feedback triage (/admin/feedback) |
| `create_user_letter_stats_table` | weak-letter drills (#6) — /drills |
| `add_reciter_to_users_table` | recitation reciter preference (#7) |
| `create_races_tables` + `add_race_id_to_tests_table` | live typing races (#8) |
| `add_config_columns_to_races_table` | private-room settings; makes race passage columns nullable |

(If the streaks/hifz/certificates migrations were never deployed they run here too.)

---

## 3. Smoke test

```bash
php artisan about | grep -Ei 'cache|queue|broadcast'   # redis / redis / pusher
supervisorctl status qurantyping-worker                 # RUNNING
```

Browser:

1. `/admin/users` — **Last login** column · `/admin/feedback` loads
2. `/` — audio bar plays · Profile → **Recitation** picker persists
3. `/drills` — loads (fills after some typing history)
4. `/races` in **two** logged-in sessions → Quick match → same room → 8-sec countdown →
   type → the other player's bar advances → standings. Private room: Create → copy link →
   other joins → host Start.

DevTools while on `/races`: `broadcasting/auth` = **200** with an `auth` string, no
`Pusher : Error` in the console.

---

## Rollback

- Every new migration has a working `down()`. `add_config_columns_to_races_table` down()
  only drops its 3 columns (leaves the passage columns nullable — harmless).
- `BROADCAST_CONNECTION=log` disables races cleanly — the pages still render, the
  presence channel just never connects.
- Revert `QUEUE_CONNECTION` / `CACHE_STORE` to `database` any time — no schema impact
  (`jobs`, `cache` tables still exist).

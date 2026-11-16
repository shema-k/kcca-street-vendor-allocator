# Running KCCA VRSP in Docker

The system ships as two containers: **app** (Apache + PHP 8.4, with `pdo_mysql`)
and **db** (MariaDB 11.4). Both are defined in `docker-compose.yml`.

The container replaces `run.sh` and `db.sh`: it waits for MariaDB, seeds the
database the first time, then serves on port 80, published as **8080** on the
host.

## Quick start

```bash
cd kcca-vendor-relocation
cp .env.example .env        # optional but recommended: set real passwords
docker compose up -d --build
```

Open <http://localhost:8080> and sign in as `admin` / `KCCA VRSP 2026`.
Every other seeded account uses `kcca@2026`.

Check it:

```bash
docker compose ps
docker compose logs -f app
```

First start takes a minute or two: the image builds, MariaDB initialises, and
the seeder loads the KCCA figures (about 20 seconds). The app container is
reported **healthy** once `pages/portal.php` answers with HTTP 200.

Stop it, keeping all data:

```bash
docker compose down
```

Stop it and delete the database, backups and sessions:

```bash
docker compose down -v
```

## What the container does

`docker/entrypoint.sh` runs before Apache:

1. Creates and hands over the session, log and backup directories.
2. Waits for MariaDB to accept connections on `KCCA_DB_HOST:KCCA_DB_PORT`.
3. Counts markets; if the table is empty (or missing) it runs
   `php database/seed.php`, which applies `database/schema.sql` and loads the
   data.
4. `exec apache2-foreground`.

Seeding is skipped on later starts. `KCCA_AUTO_SEED` controls it:

| Value | Behaviour |
|---|---|
| `1` (default) | seed only when the `markets` table is empty |
| `0` | never touch the database |
| `force` | drop and reseed on every start — destructive |

## Volumes

| Volume | Mounted at | Holds |
|---|---|---|
| `kcca-vrsp_db_data` | `/var/lib/mysql` | the entire database |
| `kcca-vrsp_backups` | `/tmp/kcca-vrsp-backups` | SQL backups written by **Settings → Backups** |
| `kcca-vrsp_sessions` | `/var/lib/php/sessions` | PHP sessions (kept over restarts) |

Without the `backups` volume the files would live inside the container and be
lost when it is recreated.

Download a backup produced in the UI:

```bash
docker compose cp app:/tmp/kcca-vrsp-backups/BKP-20261116-120000.sql ./backup.sql
```

Restore one (the app image has no `mysql` client, so use the database container;
the dump names its database itself):

```bash
docker compose exec -T db sh -c \
  'mariadb -u root -p"$MARIADB_ROOT_PASSWORD"' < backup.sql
```

Back up the whole database volume:

```bash
docker compose exec -T db sh -c \
  'mariadb-dump -u root -p"$MARIADB_ROOT_PASSWORD" --single-transaction "$MARIADB_DATABASE"' \
  > kcca-full.sql
```

## Development

Serve the working tree instead of the copy baked into the image, so PHP edits
apply on the next request:

```bash
docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d
```

The overlay bind-mounts `.` at `/var/www/html` and turns on `KCCA_DEBUG=1`
(exception detail on the database-error page). Rebuild only when files under
`docker/` or the `Dockerfile` change:

```bash
docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d --build
```

Run the shipped checks inside the container:

```bash
docker compose exec app php database/verify.php
docker compose exec app php database/lang_check.php
```

## Configuration

All settings are environment variables read by `config/config.php`; nothing in
the code needs editing.

| Variable | Default | Meaning |
|---|---|---|
| `APP_PORT` | `8080` | host port published to the app |
| `KCCA_DB_HOST` | `db` | MariaDB host |
| `KCCA_DB_PORT` | `3306` | MariaDB port |
| `KCCA_DB_SOCKET` | empty | must stay empty in Docker; forces TCP |
| `KCCA_DB_NAME` | `kcca_vendor_relocation` | database name |
| `KCCA_DB_USER` | `kcca` | application user |
| `KCCA_DB_PASS` | `kcca_dev_2026` | application password |
| `KCCA_DB_ROOT_PASS` | `kcca_root_2026` | MariaDB root password (compose only) |
| `KCCA_AUTO_SEED` | `1` | see the table above |
| `KCCA_DEBUG` | `0` | `1` shows exception detail on the DB-error page |

Put them in `.env` next to `docker-compose.yml`. `.env` is git-ignored and
`.dockerignore`d, so passwords never enter the image.

## Security notes

- `docker/apache-security.conf` denies HTTP access to `database/` — without it
  anyone could load `/database/seed.php` and have it drop every table — plus
  the `docker/` folder, SQL dumps, scripts, archives and dotfiles.
- The app container runs Apache as root (it must, to bind port 80) and serves
  as `www-data`. The application tree is owned by root and is not writable by
  the web user.
- `display_errors` is off and `expose_php` is off; faults are logged to
  `/var/log/php/error.log` inside the container and rendered as a reference
  number by `lib/ErrorHandler.php`.
- The database port is **not** published by default. Uncomment `ports:` in
  `docker-compose.yml` if you need DBeaver or the `mysql` client from the host.

### Before exposing it beyond localhost

1. Change `KCCA_DB_PASS` and `KCCA_DB_ROOT_PASS` in `.env`; `docker compose
   down -v` once if the database was already initialised with the old ones.
2. Put a TLS-terminating reverse proxy (Caddy, nginx, Traefik) in front and
   forward to `app:80`.
3. Set `opcache.validate_timestamps = 0` in `docker/php.ini` and rebuild, so
   PHP does not `stat()` files it will never change.
4. Remove the demonstration accounts, or change their passwords, from
   **Users** — they are seeded with `kcca@2026`.

## Continuous integration

`.github/workflows/docker.yml` (job **Build, seed and smoke-test the stack**)
runs on every push to `main`/`master`, every pull request and on demand. It:

1. builds the image and runs `docker compose up -d --build`;
2. waits for the container healthcheck, then asserts the first start seeded
   the database and that `php database/verify.php` reconciles with the
   published KCCA figures;
3. requests the HTTP surface — portal and sign-in `200`, `/` `302`,
   `database/`, `docker/`, scripts, the README and dotfiles `403`, compose
   files and the `Dockerfile` `404` (they are not in the image), images `200`;
4. checks the security headers and that `.env`, compose files and the 20 MB
   stock originals are absent from the image;
5. restarts the app and proves it reports **database already seeded** instead
   of seeding again;
6. always tears the stack down with `docker compose down -v`, and prints the
   container logs when a step fails.

The same checks locally:

```bash
docker compose up -d --build
curl -o /dev/null -w '%{http_code}\n' http://localhost:8080/pages/portal.php
docker compose exec app php database/verify.php
docker compose down -v
```

## Troubleshooting

**`permission denied ... docker.sock`** — your user is not in the `docker`
group. Run `sudo usermod -aG docker "$USER"`, sign out and back in (or prefix
commands with `sudo`).

**`docker compose` is not a recognised command** — the Compose v2 plugin is
missing; install `docker-compose-plugin`. The older `docker-compose` binary
works too if you prefer it.

**Port 8080 already taken** — set `APP_PORT=8090` in `.env`.

**App keeps restarting** — `docker compose logs app` shows the entrypoint
output. A seed failure prints the PHP error before the container exits.

**"Database connection failed" page** — the app cannot reach `db`. Check
`docker compose ps` for `db (healthy)` and that `KCCA_DB_*` in `.env` match the
`db` service environment.

**Reset everything** — `docker compose down -v && docker compose up -d
--build`. With `KCCA_AUTO_SEED=force` a single `docker compose up -d` reseeds
without deleting the volume.

**Timezone** — containers run with `TZ=Africa/Kampala`, and PHP is set to the
same zone, matching `config/config.php`.

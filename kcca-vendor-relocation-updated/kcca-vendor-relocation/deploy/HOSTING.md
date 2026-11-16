# Hosting KCCA VRSP on free shared hosting

This project is plain PHP 8.1+ and MySQL/MariaDB, so it runs on ordinary
shared hosting: upload the files once, import the database once, and the site
is live for anybody with the link. No terminal, no `php -S`, no Docker on the
host.

Two files are produced by the scripts in this folder:

| File | Size | What it is |
| --- | --- | --- |
| `deploy/dist/kcca-vrsp-shared-hosting.zip` | ~2.8 MB, 185 files | the application, with shared-hosting `.htaccess` rules |
| `deploy/dist/kcca-database.sql.gz` | ~0.6 MB | the seeded database: 30 tables, 86 markets, 8,386 vendors, 22,550 pitches |

The database dump is imported through phpMyAdmin. **Do not run
`database/seed.php` on the host** — free hosting has no PHP CLI, and the
`.htaccess` files block that script over HTTP on purpose.

---

## Which free host

| | **InfinityFree** (recommended for this demo) | **HelioHost — Johnny** |
| --- | --- | --- |
| Sign-up | instant | rationed; slots open a couple of times a day and fill in seconds |
| PHP | 8.2/8.3 (choose 8.3) | 8.3/8.4, selectable |
| Database | MySQL/MariaDB, phpMyAdmin | MariaDB, phpMyAdmin |
| Disk | 5 GB | 1 GB (files + database) |
| SSL | free | free |
| Ads | none | none |
| Cron / SSH | no | cron yes, SSH no on the free plan |
| Anti-bot gate | **yes** — a JavaScript/cookie check on every URL | no |
| Best for | a link you can share today | long-lived dashboards and clean AJAX |

**Recommendation:** start with **InfinityFree**. Sign-up is immediate, 5 GB is
far more than this project needs, and the whole package fits its limits
(2.8 MB ZIP against a 10 MB upload limit; the largest SQL statement is 0.5 MB
against a 3 MB `max_allowed_packet`).

The one caveat is InfinityFree's anti-bot gate. A visitor's first request gets
a short JavaScript challenge that sets a cookie; after that the site behaves
normally. The challenge parameters rotate periodically (roughly every half
hour), so a dashboard tab left open for a long time may start seeing failed
background polls (`api/pulse.php`, `api/alerts.php`, `api/live_counts.php`).
**Reloading the page fixes it.** Nothing else in the application depends on
long-lived background requests.

If you can catch a HelioHost sign-up slot, use it instead when you need the
live counters to run unattended for hours. The same ZIP and SQL work there.

---

## Build the two files

From the project root:

```bash
deploy/build-shared-hosting.sh    # → deploy/dist/kcca-vrsp-shared-hosting.zip
deploy/make-db-dump.sh            # → deploy/dist/kcca-database.sql.gz
```

`make-db-dump.sh` reads the running Docker stack when it is up, otherwise the
local MariaDB. It refuses to produce a dump that is missing the complaints and
support tables. Re-run both scripts after any code or data change.

The ZIP deliberately contains no `config.local.php`, `.env`, Docker files, CI
files, README, or the 20 MB of stock photographs in `images/source/`. **Local
credentials never leave the machine.** You create the config file on the host
in step 5.

---

## InfinityFree, step by step

### 1. Create the account

1. Sign up at <https://infinityfree.com>.
2. **Create Account** → pick a free subdomain, for example
   `kcca-vrsp.infinityfreeapp.com` (the panel offers several suffixes).
3. Verify the email address and wait for the account to become active.

### 2. Create the database

1. Open the account → **MySQL Databases** → **Create Database**.
2. Write down all four values the panel shows:

   | Panel label | Example | Notes |
   | --- | --- | --- |
   | Database host | `sql104.infinityfree.com` | **not** `localhost` |
   | Database name | `if0_38123456_kcca` | the prefix is added by the panel |
   | Database user | `if0_38123456` | also prefixed |
   | Password | (the one you chose) | case-sensitive |

   Use the values **exactly as shown**; the prefix is part of the name.

### 3. Import the database

1. In the panel, open **phpMyAdmin** for that database.
2. Select the database in the left sidebar (do not leave a table selected).
3. **Import** → **Choose File** → `deploy/dist/kcca-database.sql.gz`
   (if gzip is refused, use the uncompressed `kcca-database.sql`).
4. Leave the defaults (UTF-8, SQL) and press **Go**.
5. You should end with **30 tables**. Spot-check: `markets` has 86 rows,
   `vendors` 8,386, `trade_attire` 13.

The dump creates its own tables; it contains no `CREATE DATABASE` or `USE`, so
it imports into whichever database you selected.

### 4. Upload the application

1. Open **File Manager** and enter `htdocs`.
2. Delete the host's placeholder page if present (`index2.html`,
   `default.php`, or a demo `index.php`).
3. **Upload** `kcca-vrsp-shared-hosting.zip` (2.8 MB).
4. Select the uploaded ZIP → **Extract** into `htdocs`, so that `index.php`
   and `.htaccess` sit **directly** in `htdocs` — not inside an extra folder.
5. Delete the ZIP afterwards.

If the panel has no Extract action, upload the contents by FTP (FileZilla, using
the FTP details in the panel) or unzip locally and drag the folders in. Do not
rename `.htaccess`; Apache needs it.

### 5. Create the one config file

Shared hosting has no environment variables, so the application reads
`config/config.local.php`. The package ships an example.

1. **File Manager** → `htdocs/config`.
2. Select `config.local.php.example` → **Copy** → name the copy
   **`config.local.php`** (exactly that).
3. **Edit** it and replace the example values:

```php
define('DB_HOST', 'sql104.infinityfree.com'); // the panel's database host
define('DB_PORT', '3306');
define('DB_SOCKET', '');                      // shared hosting has no socket
define('DB_NAME', 'if0_38123456_kcca');       // exactly as shown in the panel
define('DB_USER', 'if0_38123456');
define('DB_PASS', 'the-database-password');
```

Save. `config.local.php` is blocked from browsers by `config/.htaccess`, and it
is git-ignored, so it will never be committed.

### 6. Open the site and change the passwords

1. Visit `https://your-subdomain.infinityfreeapp.com`.
2. Sign in as **`admin`** / **`KCCA VRSP 2026`**.
3. Open **Profile** and change the password immediately. The sign-in page
   stops offering an account in its demo list as soon as that account no longer
   uses the shared demo password.
4. If you will share the demo widely, change the other seeded accounts from
   **Users** too. Their shared password is `kcca@2026`. Until you do, anybody
   who finds the site can sign in as them — fine for a demo, not for real data.

### 7. Turn on SSL

In the account panel, open **SSL Certificates**, request the free certificate
for the subdomain, and wait for it to become active. The application uses
relative URLs only, so it works over HTTPS without any further change.
If the panel offers a "force HTTPS" toggle, enable it.

### 8. Prove the lockdown

Open these URLs; each must return **403 Forbidden**:

- `https://your-site/database/seed.php`
- `https://your-site/database/schema.sql`
- `https://your-site/config/config.local.php`
- `https://your-site/lib/Database.php`

If they return 200, `.htaccess` is not being honoured — stop and fix that
before sharing the link.

---

## HelioHost, step by step

1. Create an account when **Johnny** sign-ups are open (check the signup page
   and their Discord/forum for the twice-daily window), or send a one-off
   donation for a Tommy account.
2. In Plesk, add your subdomain/domain. The web root is **`httpdocs`**.
3. **Databases** → add a MariaDB database and user. Host is **`localhost`**.
4. Open phpMyAdmin and import `kcca-database.sql.gz` into that database.
5. **File Manager** → `httpdocs` → upload and extract
   `kcca-vrsp-shared-hosting.zip` so `index.php` and `.htaccess` are directly
   inside `httpdocs`.
6. Create `config/config.local.php` from the example, with
   `DB_HOST` = `localhost`, `DB_PORT` = `3306`, `DB_SOCKET` = `''`, and the
   database name/user/password you created.
7. In Plesk, set the PHP version to 8.3 or 8.4 and enable the free SSL
   certificate.
8. Sign in and change the demo passwords (as in step 6 above).
9. Check the four 403 URLs from step 8.

---

## Any other cPanel host

The same package works on any Apache/cPanel host with PHP 8.1+:

1. Create the database and user, then import the dump in phpMyAdmin.
2. Upload the ZIP to the domain's document root (`public_html`, `htdocs`, or
   `httpdocs`) and extract it there.
3. Create `config/config.local.php` (host is usually `localhost`).
4. Set the PHP version to 8.1+ and enable SSL.

Nothing needs Composer, npm, cron, Redis, or a build step.

---

## Troubleshooting

| Symptom | Cause and fix |
| --- | --- |
| “Database connection failed” | `config/config.local.php` values. On InfinityFree the host is **not** `localhost`, and the database name/user must include the `if0_…` prefix. Check the password and that `DB_SOCKET` is `''`. |
| Every page is 500 | The host is rejecting a `.htaccess` directive. Confirm the host allows `.htaccess` (both recommended hosts do). Temporarily rename `.htaccess` to isolate it, then ask support which directive failed. |
| 403 on the home page | Files were extracted one level too deep. Move the contents of `kcca-vrsp/` into `htdocs` so `index.php` is directly there. |
| Blank white page | Check the host's PHP error log. Confirm PHP is 8.1 or newer and that `pdo_mysql` is enabled (both hosts provide it). |
| Import fails in phpMyAdmin | Import the `.sql` (uncompressed) instead of `.sql.gz`. The largest statement is 0.5 MB, well under InfinityFree's 3 MB packet limit. Do not import into a database that already has these tables unless you intend to replace them — the dump drops and recreates them. |
| “Access denied; you need SUPER privilege” | You are importing a dump from a different tool that includes `DEFINER` clauses. This project's dump does not; re-export with `deploy/make-db-dump.sh`. |
| Live counters stop updating | InfinityFree's anti-bot cookie rotated. Reload the page. |
| Backup download fails | `lib/Backup.php` writes to the system temp directory. If the host blocks it, use phpMyAdmin's **Export** instead; the data is unchanged. |
| Images are missing from `images/source/` | Intentional: those are 20 MB of stock originals. The application only uses the smaller files in `images/`. |
| Site suspended | Free hosts suspend inactive or high-traffic accounts. Keep `kcca-database.sql.gz` and the ZIP; you can redeploy in minutes. |

---

## What has been tested

The exact two artifacts in `deploy/dist/` were verified here, not just written:

- imported into a fresh MariaDB database owned by a non-root user: **30 tables**,
  86 markets, 8,386 vendors, 0 audit rows;
- `php database/verify.php`: **34 passed, 0 failed**;
- served by Apache 2.4 with `AllowOverride All` from an extracted ZIP, with a
  `config.local.php` pointing at a remote-style database on another port;
- portal 200, sign-in 200, dashboard 200, `api/live_counts.php` 200 with JSON,
  support/notices/complaints/reports/users screens 200;
- `/database/seed.php`, `/database/schema.sql`, `/config/config.local.php`,
  `/lib/Database.php` and `/partials/.htaccess` → **403**;
- security headers present; PHP error log empty.

The same database was also rebuilt from scratch with the fixed seeder in the
Docker stack, confirming `docker compose up` and the hosted dump now carry the
same 30-table schema.

---

## Honest limits of free hosting

- **This is for a demonstration or coursework**, not for storing real vendor
  records. Free hosts can suspend accounts without notice, and a public demo
  login can be changed by anyone who finds it.
- InfinityFree: ~50,000 hits/day, no cron, no SSH, 10 MB per uploaded file,
  anti-bot gate, database fair-use limits. Backup files land in the account's
  temp directory, which counts against the 5 GB.
- HelioHost: 1 GB total disk, daily CPU/memory caps, and sign-up availability
  is unpredictable.
- If this ever becomes a real Authority system, move it to paid shared hosting
  (~$2–4/month on Hostinger/Namecheap) or a small VPS: the package, config
  file and SQL import are identical. The Docker setup in the project root is
  the other supported path.

---

## Updating a live site later

1. Run `deploy/build-shared-hosting.sh` again.
2. Upload the new ZIP and extract it over the existing files. The ZIP does not
   contain `config/config.local.php`, so your credentials are untouched.
3. If code changes required a schema change, also rebuild and re-import the SQL
   dump — **this replaces the data**. Export anything you need first.

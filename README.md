# KCCA Vendor Relocation & Space Allocation System

A working system for the **Kampala Capital City Authority**: survey the places where street vendors
trade unlawfully, rank them by how much harm they cause, find the gazetted market space that is
already sitting empty, and match each trader to a pitch — with the reasoning recorded and auditable.

It is built to sit alongside the Authority's existing platforms, not to replace them. Every screen
links onward to `kcca.go.ug`, and every figure is traced to the KCCA statement it came from.

```
kcca-vendor-relocation/
├── install.sh              check the environment, create and seed, verify
├── db.sh                   start / stop / status for the bundled database
├── run.sh                  the one command: start the database and serve
├── config/
│   ├── config.php          database, weights, paths
│   └── kcca_facts.php      every official KCCA fact, with its source
├── database/
│   ├── schema.sql          22 tables
│   ├── seed.php            loads the real figures, generates the survey layer
│   └── verify.php          proves the totals reconcile
├── lib/
│   ├── bootstrap.php       shared queries, division outlines, colours
│   ├── Database.php        PDO wrapper, bulk insert, transactions
│   ├── Auth.php            roles, sessions, CSRF, audit
│   ├── Engines.php         the two scoring engines
│   ├── ReachModel.php      footfall and trade viability model
│   ├── Recommendations.php recommendation service and approval workflow
│   ├── VendorService.php   vendor registration and management
│   ├── Validator.php       field-level validation
│   ├── Settings.php        policy values, read from the database
│   ├── Backup.php          self-contained SQL backups
│   ├── ErrorHandler.php    safe error pages and fault logging
│   └── Helpers.php         formatting, icons, chart formatters
├── layouts/                header, footer
├── partials/               dashboard sections, one per role remit
├── api/                    map data, stall plans, zone detail, vendor options,
│                           actions, modal forms, stall requests, CSV exports, backups
├── pages/                  the 17 screens, plus three redirects
└── assets/
    ├── brand/              KCCA logo, from kcca.go.ug
    ├── css/kcca.css        Authority colours and layout
    ├── css/activities.css  activity cards and slideshow
    ├── css/dark.css        dark-mode overrides
    └── js/                 kcca.js, charts.js, kampala-map.js,
                            stall-map.js, activities.js
```


## v2.0 additions

See `CHANGES_v2.md` for the full review. In short: an **AI Planner** that places a
whole batch of traders at once (exact joint optimum, four policies, four-eyes
approval), a **security pass** that makes every screen, export and action endpoint
agree with the role and division the rail promises, and a new **Civic** interface
layer (`assets/css/civic.css`). The planner appears as *AI Planner* under
Relocation for the Administrator, Executive Director, Mayor and Planner.

## Requirements

PHP 8.0+ with `pdo_mysql`, and MySQL 8 or MariaDB 10.4+ (`mariadbd` and
`mariadb-install-db` on PATH). No Composer, no npm, no build step.

## Run it

One command:

```bash
./run.sh
```

That starts the database if it is not running, seeds it the first time, and serves on
**http://localhost:8080**. Both `http://localhost:8080` and `http://127.0.0.1:8080` answer.
Press Ctrl+C to stop the web server.

```bash
./run.sh 9000        # serve on another port
./db.sh status       # is the database running?
./db.sh stop         # stop the database
php database/seed.php   # reset and reseed everything
php database/verify.php # prove it reconciles with the published figures
```

### Using your own database

The bundled server stores its data in `~/.kcca-vrsp/db`, outside the project, so it is not
committed and is not lost when `/tmp` is cleared. If you already run MySQL or MariaDB, point
the environment at it and ignore the bundled one:

```bash
KCCA_DB_HOST=localhost KCCA_DB_PORT=3306 KCCA_DB_SOCKET= \
KCCA_DB_USER=kcca KCCA_DB_PASS=secret ./run.sh
```

The database and user are created by `db.sh` on first run; `install.sh` does the same against a
server you name, as well as checking PHP and running the verification.

### Sign in

One form, and beneath it a card per role. Click a card and it signs you in —
the demonstration accounts are fixed, so asking an officer to type a username
adds nothing. Every account uses `kcca@2026` except `admin`, which uses
`KCCA VRSP 2026`.

| Role | Level | Sees |
|---|---|---|
| `admin` — Robert Ssempijja, Director ICT | 7 | Everything |
| `ed` — Hajjat Buzeki Sharifah, Executive Director | 6 | All progress, approvals, audit |
| `mayor.central` — Salim Uhuru, Mayor Kampala Central | 5 | Own division, its managers, audit |
| `mayor.nakawa` — Buken Ali, Mayor Nakawa | 5 | Own division, its managers, audit |
| `planner` — Godfrey Luyombya, Physical Planning | 4 | The forecast, congestion, matching |
| `enforcement` — Daniel Nuwabine, Legal Affairs | 3 | Notices, ultimatums, clear-ups |
| `revenue` — Ezra Ssebufu, Revenue Collection | 2 | Licences and the compliance gap |
| `vendor` — Nakato Rehema, street vendor | 1 | Markets, stalls, footfall, applications |

Sign in as `mayor.central` and then `mayor.nakawa` and the rail, the dashboard
and every figure change to that division — the quickest way to see that the
scoping is real rather than cosmetic.

Sign in as `vendor` for the trader's view: no registers, no programme figures,
no audit trail. Three sections — markets with space, footfall to choose well,
and their own applications.

Sign in as `revenue` for the licensing desk: the licence position across the
register and the compliance gap by division.

The trader portal at `/pages/portal.php` needs no sign-in.

**On the names.** Seven of the ten are officers KCCA names on kcca.go.ug — the
Executive Director, both Division Mayors, the Physical Planning secretary, the
Legal Affairs spokesperson, the Revenue deputy director and the Market
Development supervisor. The other three are illustrative: KCCA publishes an ICT
department, an Internal Audit unit and no read-only role, but names nobody in
them. Each account states which it is, in the account menu and on the accounts
page, because the audit trail attributes every action to a name and a reader is
entitled to know where that name came from.

## Roles and authority

Eight roles, in the order of authority. A role determines the screen an
officer lands on, the figures they are accountable for, what they may change,
and whose records they may edit.

| Level | Role | Remit | Links |
|---|---|---|---|
| 7 | System Administrator | Everything on the system, and every account that reaches it | 17 |
| 6 | Executive Director | All progress across the Authority, and the final decisions | 16 |
| 5 | Mayor | One division, and the managers working under them | 15 |
| 4 | Market Chairman | The traders allocated to your market, and what has been done for them | 8 |
| 4 | Planner | How the relocation is planned, and what the forecast says | 11 |
| 3 | Enforcement Officer | The Authority on the ground, and vendors following instructions | 9 |
| 2 | Revenue Officer | Licences and fees across the register | 8 |
| 1 | Vendor | Find space, apply for a stall, follow the footfall | 5 |

A **Market Chairman** sits at level 4, alongside the Planner. A chairman is
elected by the traders of a market rather than appointed by the Authority, so
they sit level with a directorate role rather than beneath or above it, and
neither outranks the other. See *Market chairmen* below.

**Internal Audit is not a sign-in role.** The instruction is that it is
*viewed by* the Executive Director, the Mayor and the System Administrator, so
the audit trail is a screen those three hold rather than a separate desk.

Three rules hang off the order of authority rather than a second permission list:

- **A Mayor sees only their own division.** Every figure is scoped before it
  is fetched, not filtered afterwards.
- **A chairman sees only their own market**, on the same principle. The scope
  is applied to the query rather than to a copy of the results, and it cannot
  be widened by editing the query string: `RoleDashboard::scopeFor()` names the
  table it is being applied to, because `vendors` carries a chairman while
  `markets` and `trade_zones` carry a division — so a chairman's scope reads
  differently on each.
- **An officer edits only accounts below their own level, and never their
  own.** That is why an account cannot be quietly widened by the person it
  belongs to.

The rail is drawn from the same ladder, and no two roles see the same links.
It is grouped into four — Work, Relocation, Operations, Records — and a role
that does not operate a screen does not have a link to it.

**On the names.** Five accounts are officers KCCA names on kcca.go.ug — the
Executive Director, both Mayors, the Physical Planning secretary and the Legal
Affairs spokesperson. The System Administrator and the vendor are illustrative:
KCCA publishes an ICT department and a street-vendor role but names nobody in
them. Each account states which it is, because the audit trail attributes
every action to a name and a reader is entitled to know where it came from.

## What each role sees

Every officer's rail is drawn from their own field, and so is what they can
open. The audience list in `navigation()` and the permission check on each
page are written to agree — a role is shown a link to a screen it could open,
and is refused a screen it could not.

| Role | Sees | Refused |
|---|---|---|
| System Administrator | every screen | — |
| Executive Director | every screen | — |
| Mayor | vendors, markets, stall map, matching, forecast, allocations, city map, congestion, enforcement, licensing, performance, reports, audit, chairmen | accounts, settings |
| Planner | vendors, markets, stall map, matching, forecast, allocations, city map, congestion, reports | enforcement, licensing, performance, audit, accounts, settings |
| Enforcement Officer | vendors, markets, stall map, city map, congestion, enforcement, reports | matching, forecast, allocations, licensing, performance, audit, accounts, settings |
| Revenue Officer | markets, stall map, licensing, reports | matching, forecast, allocations, enforcement, performance, audit, accounts, settings |
| Market Chairman | their market, their traders, their own record | everything else |
| Vendor | their market, their chairman, the pitch plan, the portal | the register and every staff screen |

**A link is only shown to a reader who could follow it.** Tightening a page's
permission is easy to do and easy to leave half-done: the page stops opening,
but a link to it sitting inside another page that the same role can still read
does not. The register alone produced forty dead "Match" links per page for the
chairman and the revenue desk, and the city map linked every zone into the
congestion profile, which neither may open. Each is now gated on the same
permission that guards its target, so the two cannot drift apart again.

## Market chairmen

Every market has a chairman, and every vendor in that market follows them.
`market_chairmen` holds one row per market — the name, telephone, email, when
the tenure began and whether it is active, interim or vacant.

The chairman is **a record against the market, not a login**. A market can have
a chairman on record before their account exists, and a report must be able to
name a chairman without implying that person can sign in. Granting a sign-in is
a separate step in Accounts, and the `chairman` role is what it produces.

The association is **not** a field somebody maintains. When a vendor is placed
into a market — an offer accepted, a pitch confirmed, a trader request approved
— `syncVendorChairman()` writes that market's chairman onto the vendor, and from
then on it travels with them through the register, the allocation record and
every report without anyone remembering to update it. A placement that is undone
(an offer withdrawn, a space declined) clears it again, because a vendor back in
the pool is nobody's responsibility until they are placed again.

`pages/chairmen.php` is where the assignment is made. Officers with `manage`
maintain it; a Mayor is narrowed to their own division; a chairman sees their own
market and may correct their own contact details and nothing else.

`pages/my_market.php` is the other end of the same chain, and the only screen
with a "my" in it. A chairman arrives at their market, its pitch counts and the
traders placed in it. A trader arrives at their market, their chairman's name
and telephone number, and every offer and request they have made. A trader who
has not been placed is told so and shown where to find a space, because "no
chairman yet" is a fact about their stage rather than an error to hide.

### Reaching a trader through their account

A trader account holds `vendor_id`, the vendor record it belongs to, which is
how it reaches its chairman and its market. `AccountService` refuses to store
one without the other: a `chairman` role with no `chairman_id`, or a `vendor`
role with no `vendor_id`, is an account that can be signed into and can see
nothing, so it is not a state worth saving. Both links are cleared on any
other role, where they would mean nothing.

**The 8,386 existing vendors carry no chairman.** They sit in trade zones and
have never been allocated to a market, so there is no market to take a chairman
from, and inventing one would put a name against a vendor on no authority. Their
chairman is written when they are placed. `verify.php` checks the invariants that
must not break meanwhile: one chairman per market, every chairman on a market that
exists, no vendor on a chairman who does not exist, and no account outside the
`chairman` role carrying a `chairman_id`.

## The dashboard, by role

One page, one composer. `lib/RoleDashboard.php` declares which sections each role
owns and scopes every figure before it is fetched, so a Mayor and the System
Administrator open the same URL and see a different screen. The relocation
programme funnel is the one section most roles share, because it is the point of
the system — it is declared once and never repeated per role. The vendor gets
none of it: a trader has no programme to track.

The **menu button** in the top bar opens that officer's dashboard as a drawer,
from any page; clicking it again closes it. It fetches the same sections through
one endpoint, so the drawer is never a second copy of the figures to drift.

The **name and role** in the top right open the account menu: the position,
directorate, division, contact details, last sign-in, what the account can do,
and a sign-out.

## The screens

Nineteen, grouped as the rail groups them. A role sees only the screens it
operates.

**Work** — *Dashboard* (built per role; see above), *Vendors* (register and
manage traders), *Markets* (every location, its individual pitches, counted
footfall, rent, capacity and land-use restrictions), *Stall Map* (every market
as a pitch plan, open to the public, with stall requests).

**Relocation** — *Matching* (vendor matched to space on six criteria, with the
reasoning shown), *AI Forecast* (the reach model, with approve and reject),
*Allocations* (every offer, its outcome, and the full relocation history).

**Operations** — *City Map* (Leaflet with an offline vector fallback),
*Congestion Analysis* (ranked sites, full scoring method), *Enforcement &
Notices* (the full chain from survey to escalation, plus the public
statements), *Trade Licensing*.

**Records** — *Performance & KPIs* (against the Strategic Plan), *Reports*
(search, filter and export), *Audit Trail*, *Accounts* (add staff, set what
they may do, set their password, and edit every location in the register),
*Settings* (engine weights, model calibration, programme policy, and
backups), *Chairmen* (who administers each market), *Trader Portal*.

**The Administrator's instrument over every account.** `pages/users.php` is
where the System Administrator adds staff and amends any account: username,
name, position, directorate, role, division, basis of the name, telephone,
email, whether sign-in is allowed, and a password that can be set directly as
well as issued as a temporary one. A chairman account is bound to the market it
chairs and a trader account to the vendor record it belongs to, from the same
form. Nobody may edit their own account here — that is the profile — and
nobody may appoint anybody to their own level of authority or above.

**Editing a location.** `pages/market_edit.php` is the System Administrator's
instrument over the location register — which is also what the map, the stall
map, the vacancy figures and every citywide total read from. Adding or
changing a location takes effect everywhere at once.

A change to a public market's capacity or vacancy is allowed, because the
administrator owns the system; but it is not silent. The page states the
consequence on the way out, and `database/verify.php` reports the resulting
difference against the published figures rather than absorbing it.

Three screens that duplicated others now redirect: the Market Register into
Markets, the Vacant Space Finder into Markets, and the KCCA Online Services
directory into the dashboard, because every page already carries those links
in its footer and the trader portal lists them in full.

## The two engines

Neither returns a bare number. Both return the per-criterion breakdown that produced the score, in
plain language, because a relocation decision affects somebody's livelihood and has to be justifiable
to the trader, to a Division Mayor, and to Internal Audit.

### Congestion — how badly does this place need clearing?

Seven criteria, scored 0–100, combined as a weighted average. Weights live in `config/config.php`.

| Criterion | Weight | What it measures |
|---|---|---|
| Physical obstruction | 26% | Footpath taken (65%) blended with carriageway taken (35%) |
| Vendors affected | 20% | Square-root compressed, so a large site leads without swamping the rest |
| Traffic and emergency access | 16% | Higher at junctions, roundabouts and stages; higher again on a strategic corridor |
| Duration | 12% | Square root of months occupied — a ten-year failure must close, not postpone |
| Strategic corridor | 12% | Designated priority corridors score full marks |
| Public facility obstructed | 8% | A hospital entrance outranks a general complaint |
| Complaints (30 days) | 6% | Volume of logged complaints |

Bands: Critical 75+, High 60–74, Moderate 45–59, Watch 30–44, Low under 30.

### Relocation — which gazetted space suits this trader?

Six criteria, scored 0–100. The top recommendation and every alternative show all six.

| Criterion | Weight | Why it is there |
|---|---|---|
| Proximity | 24% | Full marks at 400 m or less; collapses past 10 km, because past that it is a new business, not a relocation |
| Trade fit | 22% | Food cannot be sold from a hardware pitch; scored on the market's sections and facilities |
| Equity | 20% | Low capital, dependants, single parenthood, disability, years in trade. Decides who is offered space first when two traders fit equally well |
| Viability | 15% | A market that is 98% full moves congestion indoors and leaves no room for the next wave |
| Affordability | 11% | Rent against turnover. The Authority does not charge for the identified spaces, so this normally scores full marks — but the test stays for private markets that do charge |
| Facilities | 8% | A cook with no water is not allocated a pitch in a market with no water |

Both engines are exercised in the interface: open any site on *Congestion Analysis* and any vendor on
*Relocation Engine*.

### Reach — how many customers could this trader actually reach?

The third model answers the question the relocation engine deliberately does not: not "which market
fits this trader" but "how much trade is there to be had". Eight factors, scored 0–100:

| Factor | Weight | What it measures |
|---|---|---|
| Footfall | 30% | Movements in and out per day, from counted observations |
| Facility fit | 16% | Whether the location can properly support this trade |
| Category demand | 14% | How reliably this product draws buyers in Kampala |
| Headroom | 10% | Room left in the destination market |
| Competition | 8% | Vendors there already trading the same thing |
| Rent | 12% | Rent against the trader's turnover |
| Accessibility | 6% | Division density and transport links |
| Land use | 4% | Free of statutory restriction on that trade |

Two things make it honest rather than decorative. **Confidence is always reported**: footfall is
learned from counted observations, and where nothing has been counted the model falls back to a
catchment estimate anchored on published division populations and says so plainly. **Land use is a
gate, not a weight** — a location the Authority cannot lawfully use for a trade is excluded from the
ranking however good the numbers look.

The projection from footfall to customers is the most contestable number in the model, so it is
derived from stated factors rather than assumed, and the revenue figure is built from a per-customer
average spend that is set so the implied customer count is realistic for a Kampala stall.

This is a transparent analytical model, not a black box. Every score decomposes into the eight
factors, the reasoning is stored with the recommendation, and an officer can approve or reject it
with a recorded reason.

## The figures, and where they come from

Citywide totals, leadership, law and strategy are **official KCCA figures**, reproduced with their
source and reconciled exactly. Individual trade zones, vendor records, private market pitches and
enforcement outcomes are the **survey layer** — generated at realistic Kampala scale and labelled as
such wherever they appear.

`php database/verify.php` proves this. It checks the published figures, the internal consistency of
the tables, and the movement of the vacancy pool caused by the system's own actions.

| Figure | Source |
|---|---|
| 17 public markets, 2,520 vacant spaces | KCCA news 1078, 17 Feb 2026 |
| 69 private markets, at least 1,980 vacant | KCCA news 1078, 17 Feb 2026 |
| Usafi Market: capacity 3,000, 1,600 registered | Supervisor of Market Development, KCCA |
| Kamwokya Market: 250 identified spaces | KCCA news 1078 |
| "Over 550 free spaces in our markets" | Executive Director, 29 Sep 2026 |
| 194.3 km², 1,797,722 residents, 101 parishes, 1,285 LC1s | Strategic Plan FY2025/26–29/30, Table 1.1 (UBOS 2024) |
| UGX 11.9 trillion, five objectives, 2030 targets | Strategic Plan launch, 30 Sep 2025 |
| KCCA Act 2010 (Cap 195), Markets Act 2023, Trade Licensing Act Cap 101 | Primary legislation |

One discrepancy is recorded rather than hidden: KCCA's own table lists division areas summing to
194.4 km² against a stated city total of 194.3 km². That is rounding in the published source, so
`verify.php` reports it as information.

### Branding

Colours are taken from the Authority's published site: primary red `#DC351D` / `#F12D24`, amber
`#EAA515`, gold `#f7c401`, KCCA seal green `#0A6339`, body `#464646`. The logo in `assets/brand/` is
fetched from `kcca.go.ug`. The system uses the mark to identify an official KCCA platform, consistent
with the Authority's own use of it.

## The maps

Two maps, deliberately different in kind.

**City situation map** (`pages/city-map.php`) — `assets/js/kampala-map.js` renders the same data two
ways behind one API. Online it draws Leaflet over OpenStreetMap tiles. When tiles are unreachable — a
demo machine behind a firewall, a field laptop offline — it falls back to a vector map of Kampala
Capital City drawn from the division outlines, with every site and market plotted at its true
coordinates. The engine in use is shown in the toolbar. It never shows an empty frame.

**Location and stall map** (`pages/market_map.php`) — a different problem. A market interior is not
a street map: a trader needs to see the *plan* of the pitches and choose one. So each pitch carries
its own coordinates, laid out by the seeder in six section bands, and the page draws the whole market
as a plan — one square per pitch, coloured by status, with the sections labelled. At Usafi's 3,000
pitches the squares are the only legible way to show them all; clicking one opens its full record.

A trader can filter by status and section, click any pitch — on the plan or in the list — and
request it. A request is *not* an allocation: the pitch is reserved and the request waits, and only
an officer's approval turns it into an offer. That is the difference between a trader choosing and a
stall being taken.

## Choosing a stall

`pages/market_map.php` works with or without a sign-in. A trader opens a market, sees the plan, and
requests the pitch they want — the form asks only what the Authority genuinely needs to make the
decision. The API validates it anyway, because a browser's `required` attribute is a convenience, not
a control. A pitch already requested by somebody else is refused, with the request reference given so
the trader knows it is being considered, not ignored.

Officers see the same screen plus every open request for that market, and can approve one into a real
allocation — or reject it with a reason that is recorded and shown back to the trader.

## What the Authority does, on cards

`lib/Activities.php` holds six activities — trade order, gazetted space, trading
licences, market development, trader welfare and digital services — each as a
card saying what the Authority does and what the reader gets from it.

The illustrations are drawn plates by default and photographs where one is
supplied. A drawn plate in the Authority's own colours needs no rights, no
network and loads instantly; a licensed photograph says more about the work
than a drawing does, so a card prefers one when it has it. Each image is
`object-fit: cover`, so a portrait photograph fills the plate by cropping
rather than being squeezed out of shape.

To swap a photograph, edit the `image` key on the card in
`kccaActivities()` — that is the only place a file is named. A card whose
`image` is unset falls back to its drawn plate, so nothing breaks if a file
is missing. Files under `images/` are the web-sized copies served to the
browser; the originals are kept in `images/source/`.

The cards appear as an auto slideshow on the dashboard and the trader portal,
and as a static grid for readers who want them all at once. The slideshow
advances every two seconds and **stops the moment a reader shows any
interest** — hover, focus or touch. Left and right step through it and Home
and End jump to the ends, any of which sets it going again; there is no play
button, because a reader who has stopped it wanted it stopped. A reader who
has asked the operating system for less motion gets no auto-advance at all.

A vendor does not see the slideshow. A trader's three questions are where the
space is, how busy it is, and what happened to their application; what the
Authority does organisationally is not one of them.

## Dark mode

The stylesheet is built on custom properties, so dark mode is one override
block rather than a hunt through every rule. Light is the default; the toggle
writes the choice to both `localStorage` and a cookie — the cookie so the
sign-in page, which has no stored value yet, honours it too — and an unset
choice follows the operating system.

The brand colours are unchanged. KCCA red on white and KCCA red on near-black
are the same red; changing it would make this stop being the Authority's
system. What changes is the field it sits on.

The theme is applied before first paint, so a reader who chose dark never sees
a white flash while the stylesheets settle. Contrast was checked rather than
assumed: the semantic colours are lightened from their light values, because
#1B7F4D on near-black is 4.0:1 and #0A6339 would be 2.6:1.

`prefers-color-scheme` is honoured, `prefers-reduced-motion` stops the
slideshow, and the chart fills, form controls and map fallbacks are all
overridden for the dark field.

## Security

Eight roles with explicit permissions (`Auth::PERMISSIONS`). Passwords are bcrypt. Every
state-changing request needs a CSRF token. SQL is exclusively prepared statements. Output is escaped
by `e()` at every interpolation. The audit trail is written *before* the action completes, so a
download or an offer remains accountable even if the client disconnects.

### The gate has to gate something

`Auth::requirePermission('view')` on a staff screen is not a guard. **Every
role holds `view`**, including a trader, so a page gated on it is readable by
anyone who can sign in at all — and several were: a trader could open the
system settings, the account list, the enforcement register, the allocations
register, the analytics screen and the relocation engine, each of which names
traders and carries their national ID numbers and telephone numbers.

Each staff screen is now gated on the grant that actually separates its readers,
matching the audience its own navigation entry already declared:

| Screen | Was | Now | Held by |
|---|---|---|---|
| Analytics, Congestion, Licensing | `view` | `report` | administrator, ED, mayor, planner, enforcement, revenue |
| Allocations, Relocation | `view` | `allocate` | administrator, ED, mayor, planner |
| Enforcement | `view` | `enforce` | administrator, ED, mayor, enforcement |
| Settings, Accounts | `view` / login only | `manage` | administrator, ED |

The fix that is easy to repeat is the audit trail, and it is worth stating: a
page that pins a reader to their own rows has to apply the pin **after** reading
the query string. Applied before, the filter is silently overwritten by `$_GET`
and the screen looks scoped while the URL widens it. The audit trail, the vendor
register and the chairmen register all apply their scope after the request is
parsed, not before.

### Validation

Every user-supplied value goes through `lib/Validator.php`. The rule the Authority needs is specific:
**reject the bad field, keep everything else**. A trader mistyping a telephone number must not have to
complete the registration again. Validation returns a field-keyed map of messages, and the form
re-renders with each message beside the field that caused it, everything else preserved.

### Duplicate allocation prevention

Enforced at three levels, so a race between two officers cannot produce a double booking:

1. A unique index on `allocations(vendor_id, live_flag)` — a vendor may hold at most one live
   allocation.
2. A unique index on `allocations(market_space_id, live_flag)` — a pitch may be allocated to at most
   one vendor.
3. `RecommendationService::approve()` checks both again before writing, so the user gets a clear
   message rather than a database error.

`live_flag` is a generated column: 1 while the allocation is live, NULL once closed. MySQL treats NULL
as distinct in a unique index, so closed rows do not collide while live ones cannot.

### Backups

`lib/Backup.php` writes a self-contained SQL file — schema first, then every core table in restore
order — and records it in the `backups` table with a checksum, the row count and who took it. The
file restores on its own:

```bash
mysql < BKP-20261010-011151.sql
```

It contains personal data (vendor names, national ID numbers, telephone numbers) and must be handled
under the Authority's data protection policy.

### Error handling

`lib/ErrorHandler.php` registers a global exception handler. A trader never sees a stack trace, a SQL
fragment or a file path — they see what went wrong, what to do, and a reference number that matches
the log, so a fault report can be traced to one record. CLI scripts see the real fault instead.

## API

| Endpoint | Purpose |
|---|---|
| `GET api/map_data.php` | Divisions, zones with scores, markets, links, placements as JSON |
| `GET api/zone_detail.php?id=` | Full congestion profile for one site |
| `GET api/vendor_options.php?vendor_id=` | Ranked markets with the six-criterion breakdown |
| `POST api/allocation_action.php` | `offer`, `accept`, `decline`, `withdraw`, `occupy` |
| `POST api/enforcement_action.php` | `notify`, `issue-ultimatum`, `log`, `complete`, `escalate` |
| `POST api/recommendation_action.php` | `generate`, `approve`, `reject`, `explain`, `accept`, `decline`, `withdraw`, `occupy` |
| `POST api/vendor_save.php` | `create`, `update`, `delete` — field-level validation |
| `POST api/footfall_save.php` | `record`, `delete` — counted footfall |
| `POST api/location_save.php` | `save-restriction`, `delete-restriction`, `save-space` |
| `POST api/backup.php` | `create`, `delete` |
| `GET api/form.php?form=` | Modal forms: `footfall`, `space`, `restriction`, `vendor` |
| `GET api/market_stalls.php?market=` | Every pitch of one market, at its coordinates |
| `POST api/stall_request.php` | `request`, `review`, `approve`, `reject`, `cancel` |
| `POST api/chairman_save.php` | Record or amend the chairman of one market |
| `GET api/export.php?report=` | CSV export of any register |

## Resetting

```bash
php database/seed.php      # drop, reload, reseed, verify
php database/verify.php    # prove it reconciles
```

## Scope

This is an academic project. It is built to be a faithful, professional working system, but it is not
an official KCCA product and carries no endorsement from the Authority. The citywide figures are real
and sourced; the individual survey records are modelled and labelled as such throughout.

## Verification

```bash
php database/verify.php     # 27 checks: published figures, internal consistency, vacancy movement
```

Live checks run during development:

| Check | Result |
|---|---|
| All 19 pages return HTTP 200, zero PHP diagnostics | pass |
| No empty charts, no horizontal overflow on any page | pass |
| Field-level validation rejects 6 bad fields, keeps the rest | pass |
| Duplicate allocation refused at the database level (vendor and pitch) | pass |
| Closed allocations do not collide, so history is preserved | pass |
| Footfall count recorded, audited, and reflected in the market profile | pass |
| Settings change and normalise to 100% | pass |
| Backup writes 32,333 rows and restores into an isolated database | pass |
| Recommendation approved: pitch reserved, vacancy decremented, event logged | pass |
| Stall requested from the public portal with no account, then reserved | pass |
| Second request for the same pitch refused, with the reference quoted | pass |
| Officer approval creates the vendor record, the allocation and the event | pass |
| Pitch plan renders 3,000 stalls in six labelled section bands | pass |
| Nested transactions: an inner failure rolls back only its own work | pass |

### Button audit

Every control on every screen was clicked and its effect observed. Five were found broken and fixed:

| Control | What was wrong |
|---|---|
| Relocation Engine · offer | `array_slice()` returns a copy, so ranking wrote to a discarded temporary — every option came back with no rank, and the page crashed |
| Relocation Engine · proximity | Read `$vendor['lat']`; a vendor has no coordinates, they come from the trade zone as `current_lat`. The most important criterion was silently lost |
| Enforcement · close an operation | Undefined `$pct` in the transaction's `use` list, so the action always failed with a 500 |
| Locations · remove a restriction | The control existed with no handler at all — clickable, and did nothing |
| Stall map · "Show all" legend | `filter()` removed pitches from its own set, so after one filter the originals were gone and "show all" could never restore them |

Three buttons asked for input with `window.prompt()`, which a browser that suppresses dialogs turns into a dead control and which cannot be styled or validated. They now use `KCCA.ask()`, an inline dialog that validates and cannot be silently dismissed.
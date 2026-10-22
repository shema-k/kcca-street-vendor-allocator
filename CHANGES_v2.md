# KCCA VRSP v4.1 - review and changes

This records what was found when the whole project was read, what was changed,
and what has and has not been tested. **None of it has been run:** the review
environment had no PHP interpreter. Every PHP file touched passes a bracket
and string balance check, the optimiser's algorithm was verified separately
(see below), and every link and API target resolves. Run `php database/verify.php`
and click through each role before presenting it.

## 1. Findings

### Access control (the "nobody sees a dashboard that is not theirs" requirement)

| # | Finding | Fix |
|---|---|---|
| 1 | The rail hid Licensing, Performance and Congestion from roles that do not operate them, but each page checked only `report`, which six roles hold. A planner typing `/pages/licensing.php` was let in. | `Auth::requireScreen()` checks the same audience the rail uses. Applied to Licensing, Performance, Congestion. |
| 2 | `api/zone_detail.php` returned trader names, telephone numbers and disability status for any zone to **any signed-in account, including a trader**. | Needs `report` and the Congestion audience. |
| 3 | `api/vendor_options.php` returned any trader's equity profile (single parent, disability, dependants) to any signed-in account. | Needs `allocate` and the Matching audience. |
| 4 | `api/export.php` let any `report` holder download any register citywide, including National ID numbers. A revenue officer could take the vendor register; a Mayor could take every division's. | Each report names the desks that hold it; a Mayor's download is filtered to their division in the query. Refusals are audited. |
| 5 | Mayor scope was applied to pages but not to the endpoints that change data. A hand-written POST could approve, reject, offer or edit another division's trader. | `requireVendorInScope`, `requireAllocationInScope`, `requireRecommendationInScope` in `api/bootstrap.php`, used by the recommendation, allocation and vendor endpoints. |
| 6 | A Mayor's "N awaiting" dashboard counts were citywide while the list beneath was scoped. | Counts scoped by division. |
| 7 | The Executive Director could open Settings (it holds `manage`) but the rail did not offer it. | Settings added to the Executive Director's rail. |

### Other
* `Auth::rank()` and `Auth::remit()` used implicit nullable parameters, deprecated in PHP 8.4 and noisy on a current install. Fixed.
* `my_market.php` and `market_map.php` were never highlighted in the rail (file names use underscores, ids hyphens). Fixed.

### Not changed, but you should know
* The sign-in page lists one-click demo accounts for every account still using the shared demo password. It hides any account whose password has been changed, so change the passwords before any real use.
* `Auth::PERMISSIONS` gives the `mayor` role `allocate` but not `approve`; the notices screen is gated on `approve`, and mayors reach it through a division lock instead. It works, but the permission model and the screen rules are two systems that happen to agree.

## 2. New: AI Planner (`pages/ai_planner.php`, `lib/AllocationPlanner.php`)

**The gap it closes.** The Matching screen ranks markets for one trader at a time, so ten traders can each be told the same market is their best match while it has four pitches. Nothing considered the combined demand.

**How it decides**
1. *Who goes first:* traders are ranked by need = 55% congestion of their current site + 45% vulnerability (the existing equity criterion).
2. *Fit:* each trader is scored against each market on the existing six criteria, so there is one definition of a good fit.
3. *Ruled out:* land-use restrictions, and any fit below an officer-set floor. Optionally, markets outside the trader's division.
4. *Joint optimum:* the Hungarian method finds the assignment with the highest total fit under every market's capacity at once. Traders with no acceptable pitch are left for review, never forced.

**Four policies** (the same six criteria, different weights): Balanced (Settings), Equity first, Stay close, Trade first. "Compare the four policies" runs all four over the same batch and measures them against one yardstick.

**Honest "before and after".** Each plan reports its average fit against placing the same traders one by one, and how many traders' first choice was already full ("double bookings avoided").

**Controls built in**
* Read-only until submitted. Submitting turns each placement into an ordinary recommendation (re-scored by the reach model for that exact market) in the existing approval queue.
* A fingerprint of the plan is posted back; if the register changed in between, the save is refused.
* Four eyes: whoever submits an AI plan cannot approve it (Executive Director and Administrator exempt).
* Mayors are confined to their own division on the server. Export to CSV is audited.

**It is an optimiser, not a language model.** Say so to your lecturer; it is what makes every decision explainable.

**Testing done.** The solver was ported line for line to Python and checked against exhaustive brute force on 600 random cases (capacities, forbidden pairs, unplaceable traders): zero mismatches, constraints always respected.

## 3. New look (v2.1: glass)
`assets/css/civic.css`, loaded after `kcca.css` and before `dark.css`.
* **Palette:** the Authority's own colours as recorded from kcca.go.ug in `kcca.css`: red `#DC351D`/`#F12D24`, amber `#EAA515`, gold `#F7C401`, seal green `#0A6339`, body text `#464646`. They sit behind the interface as soft colour fields, with a fine dot-grid "body design" over them.
* **Glass:** every card, tile, bar and rail is frosted (`backdrop-filter`); menus, dialogs and the role drawer use a more opaque glass so text never depends on what is behind it. Browsers without backdrop-filter fall back to near-solid fills.
* **Shell architecture:** a floating top bar and a floating dock-style sidebar inset from the window edges, replacing the full-width bar and fixed rail. The logo swaps between the colour and white versions for light and dark.
* Dark mode keeps the same colour fields, dimmed. It is a token-and-rule override, so no page was rewritten. **Look at every screen in both themes**; it could not be rendered here. The PHP folder structure was deliberately left as it is, since reorganising code I cannot run risks breaking the system.

### Concise mode (default)
Explanatory sentences (page intros, card descriptions, field hints, footnotes) are hidden by default and return with the **?** button in the top bar; the choice is remembered. Nothing was deleted, so every figure, filter and button is still there. The AI Planner screen was also rewritten tersely: policy cards show a label and a three-word tag (full wording on hover), the four-step explainer became a one-line flow, and stat tiles and table headings were shortened. Uses `:has()`, supported by current Chrome, Edge, Safari and Firefox.


## 6. v3.0: a second kind of logic, and a living interface

### Cluster & Wave Planner (`pages/waves.php`, `lib/WavePlanner.php`) - "Waves" in the rail
The AI Planner decides *which pitch for which trader*. This asks a different question: *how do we move whole trading communities, and when?*
1. **Clusters:** traders waiting to be placed are grouped by the site they trade at and the kind of goods (food, produce, general goods, services). "Food sellers at Kisekka Road" is one cluster, because customers follow their sellers and a scattered community is the commonest reason a relocated trader returns to the street.
2. **Destination:** each cluster goes, as far as space allows, to one market. A market scores by fit (the same six criteria, sampled across the cluster) weighted against how much of the cluster it can hold, controlled by a **Keep together** slider. A cluster larger than any market's room is split, largest piece first.
3. **Waves:** clusters are queued by urgency (60% site congestion, 40% vulnerability) and packed into dated waves no larger than the **traders per wave** the Authority can absorb, a set number of days apart.
4. **Relief:** after each wave the plan shows how much congestion has been lifted and which sites are fully cleared, as an animated chart and timeline.
Settings on the screen (capacity, cadence, start date, togetherness, minimum fit, division) are its own, separate from the AI Planner's. Read-only; CSV export; "Plan this site" hands a deferred community to the AI Planner.
**Testing done:** the allocation and wave-packing logic was ported to Python and run over 2,000 random cases: no market over capacity, every trader either moved or deferred, every move to an acceptable market, no wave over capacity. The slider behaves as designed (at 0 a 50-trader cluster splits to chase the best fit; at 100 it stays whole).

### Alive interface (`assets/css/alive.css`, `assets/js/alive.js`)
* Living background (the colour fields drift), staggered entrances, figures that count up, bars that grow, table rows that slide in, cards that rise as you scroll, soft page transitions.
* **Controls redesigned:** primary buttons are glowing capsules with a sliding arrow and a light sweep; secondary buttons flood with colour from the left; every button ripples and gives under the press; action buttons sit in glass docks instead of loose.
* A **live chip** in the top bar (pulsing dot, Kampala clock) and a **Motion switch** (⚡) that turns every animation off; it is remembered, and the visitor's reduced-motion setting is respected automatically.
* Nothing delays a click, and every figure and link is complete without the script.

### Trader dashboard
A full-width photo slider (the six project photographs, cross-fading with a slow zoom, progress bar, dots, swipe and arrow keys, pause on hover), a live ticker of markets with free pitches, and three tiles that count up. It shows only public figures, nothing about any individual trader.

### A bug found and fixed in this pass
`lib/AllocationPlanner.php` was never added to `lib/bootstrap.php`, which has no autoloader, so the AI Planner and its API would have stopped with "class not found". Both planners are now required there. Every method the new code calls was then checked to exist, be public, and match the signature used.

**Still not run.** No PHP was available here. Run `php database/verify.php`, then click through Waves, AI Planner and the trader dashboard. Animations load on every page, so look for any screen where something moves that should not.


## 7. v3.1: same welcome for everyone, per-account appearance, yellow theme

* **Photo slider for every account.** The animated photo slider, market ticker and counting tiles now open the dashboard of every role, not only the trader's. Only the buttons differ, by what the role may open (planner: AI Planner; officer: Matching; trader: Find a stall).
* **Appearance settings for every user** (Profile, from the account menu, Appearance card; quick toggles for glass, animation and guidance in the top bar):
  * **Glass appearance** on or off. Off gives solid panels, no colour fields, no blur.
  * **Animations** on or off, **guidance text** shown or hidden, **light/dark**, and **restore the defaults**.
  * Saved **to the account on the server** (`user_preferences`, created automatically the first time it is needed, so no manual migration), so it follows the user to any device and does not leak between two people sharing a computer. If the table cannot be created, the defaults apply and the system carries on. `api/prefs.php` can only change the caller's own row, and saves only the keys sent, so simultaneous changes cannot overwrite each other.
* **Yellow theme, no orange.** The interface accent is now yellow `#F7C401` (with a pale `#FFE066` and a deeper `#E0B000`), carrying dark ink, never white; buttons, the active sidebar item, bars, charts, focus rings and the background colour fields are all yellow or green. The amber `#EAA515` and the red-to-amber blends that read as orange were removed. Red remains only for the logo and for warnings. Contrast checked: dark ink on every yellow is 7.8:1 or better.

## 8. v4.0: fault sweep, missing pieces, and account settings for everyone

### Faults found and fixed in this pass
| # | Fault | Fix |
|---|---|---|
| 1 | **A deactivated, deleted or demoted account kept working.** `Auth::check()` only looked for a session flag, so an officer switched off by the Administrator stayed signed in, with their old authority, on every device already signed in. | `Auth::check()` now confirms once per request that the account is still active and refreshes its role from the database. A change takes effect on the person's next click. |
| 2 | **Offers never expired.** Each offer had an expiry date and `expired` was a valid status, but nothing ever applied it, so an unanswered offer held a pitch for ever. | `lib/OfferSweeper.php`: at most every ten minutes, lapsed offers are expired, the pitch returns to the market, the trader returns to the waiting list, undecided proposals past their date are closed, and each is audited as the system's own act. No scheduler needed. |
| 3 | **No sign-in lockout.** A password could be guessed at the speed of the network. | Five failed sign-ins for a username in ten minutes locks that name out for the rest of the window. Counted from the audit trail (no new table); it expires by itself. |
| 4 | **The same trader could be registered twice** (national ID and phone were not unique), so one person could be offered two pitches and counted twice in every figure. | A second record with the same National ID or telephone is refused, naming the existing record. On edit, only a number actually being changed is checked, so a duplicate already in old data does not block unrelated corrections. |
| 5 | **A rail highlight, a PHP 8.4 deprecation and an export scope hole**, plus the missing class load of the AI Planner, were fixed earlier (sections 1 and 6). | |
Also checked and found sound: all 13 export queries accept the division filter (one `ORDER BY`, no existing `WHERE`/`GROUP BY`, the alias is defined in each); no named SQL placeholder is repeated (the PDO setting that makes that an error); every class is defined and loaded; the trade-category codes the Wave Planner groups by match the database enum exactly.

### Settings for every user (Profile, from the account menu)
Details and password (existing), **Appearance** (glass, animations, guidance text, **compact layout**, light/dark, restore defaults), **Alerts from colleagues** (officers), and **Your data and account**: **download my data** as a file, and **delete my account**.
* **Deleting an account** needs the password again and the word DELETE typed. It is a soft delete: sign-in is switched off permanently, the username is released, contact details are erased and the preferences row is removed, but the work the person did stays and the audit trail still names them (an audit trail that forgets who approved an allocation cannot be audited). The last active System Administrator cannot delete themselves. Wrong passwords count toward the lockout. The deletion is audited and appears in every officer's feed.
* Preferences are stored per account on the server (`user_preferences`, created or upgraded automatically).

### Every officer sees every change: the bell
All officers already read one shared database, so a saved change is in the register for everyone at once. What was missing was the **signal**. A bell in the top bar now lists what colleagues just changed (registered a trader, made or answered an offer, approved a plan, edited an account, deleted their own account...), shows an unread badge, and raises a small pop-up alert (which each officer can switch off in Profile). It polls every 20 seconds, only while the tab is visible, never reloads or moves the page, and each entry links to the relevant screen where the officer's role allows it.
* Staff roles only; traders and chairmen do not see it.
* Detail lines (which can name a trader) only for roles that already hold the Audit permission.
* Sign-ins, downloads, reading a notice and cosmetic preference changes are not shown to others (they are still in the audit trail).

### A systems analyst's gap list
**Now in the system:** sign-in lockout; duplicate-trader protection; offer expiry; immediate effect of deactivation and demotion; self-service account control and data export; a shared change feed; audited preference changes.
**Recommended next, not built** (each needs a decision or an outside service, so none was guessed at):
1. *Reaching traders:* SMS or WhatsApp notices, and USSD for feature phones. Many traders have no smartphone, and an offer that cannot be read lapses. Needs a gateway and consent records.
2. *Waiting-list automation:* when an offer lapses, offer the pitch to the next trader in the queue automatically.
3. *Fees:* mobile-money collection and receipting for pitch fees, with arrears.
4. *Evidence:* upload of ID, permit and photographs; GPS and photo evidence on enforcement actions.
5. *Appeals:* a trader's right to contest a placement, with statutory deadlines.
6. *Officer security:* two-factor sign-in and self-service password reset (today an officer issues a temporary password).
7. *Resilience:* scheduled backups with a tested restore, and a staging database separate from the demonstration data.
8. *Reporting:* a monthly Council report generated as a document, and a retention policy for personal data under the Data Protection and Privacy Act, 2019.
9. *Quality:* automated tests that run on every change (this review had to rely on static checks because no PHP was available).

**Still not run.** Run `php database/verify.php`, then: sign in as two different officers in two browsers, change something as one, and watch the bell on the other; delete a test account; let an offer's date pass and open any page.

## 9. v4.1: no more raw errors, and a smoother flow

### The screenshot: `{"ok":true,"status":200,"id":5973,...}` on a blank page
**The save had worked** (`"ok":true`, `"changed":3`, the database was updated). What failed was what happened next. The trader form, the footfall form, the pitch form and the land-use restriction form arrive as HTML and are put into the page with `innerHTML`; each carried an inline `<script>` to catch its own save. **Browsers never run a script that arrives that way**, so the form submitted the ordinary way and the browser navigated to `api/form.php` and showed the raw reply. This was in the original system, not something added in later versions.
* One handler in `assets/js/kcca.js` now catches every `api/form.php` form: it posts in the background, then closes the panel, confirms with a message and refreshes the page behind it; if the server refuses, it says why in a message; if the form has mistakes, it shows the form again with the messages beside the fields and keeps what was typed. A double click saves once. The four dead scripts were removed.

### The same problem everywhere else: one shared fix
`jsonOut()` now tells a script asking from the browser navigating. For a browser: a **success** returns the person to the page they came from with a confirmation; a **failure** shows an ordinary page in the system's own look with the reason and Go back / Dashboard buttons. Scripts still receive JSON. Messages are kept across the redirect (`flashSet` / `flashTake`) and shown by the footer. No endpoint or page now ends in raw JSON, `die()` text, or a status number.

### Refused screens no longer look like faults
A signed-in officer who follows an old link or types an address onto another desk's screen used to meet a red "Access not permitted" page. They are now returned to their own dashboard with a short note. The refusal is exactly as strict; only what is seen changed. Anything that is not a plain page view still receives a real 403.

### "A revenue officer tries to view their reports" (a regression I introduced, now fixed)
The Reports screen listed every register to every role holding `report`, while the export endpoint (tightened in section 1) refused the ones that role does not operate, so a revenue officer was offered "Vendor register" and met an error. There is now **one list of who may download what** (`exportRoles()` in `lib/bootstrap.php`), read by both the screen and the endpoint. A role is only offered what it can download. A Mayor's row counts show "—" because their download is limited to their division while the count was citywide.

### Buttons that led to screens a role cannot open (found by simulating every role against every link)
* Dashboard: **Allocation register** (shown to roles that cannot open it); **Retune the weights** (shown to planners, who cannot change settings); enforcement **Open** on the congestion screen (shown to planners). Each is now shown only to a role that can use it.
* The "Add" and "Edit" panels now say why, in the server's own words, if they cannot open, instead of "HTTP 403".

### Smoother saves
After a successful save on the **Accounts** and **Profile** pages the browser redirects back with a confirmation, so pressing Refresh no longer re-posts the form and shows a confusing error such as "username already exists" or "not your current password".

### Security gaps found while doing this
| Gap | Fix |
|---|---|
| **`api/backup.php` had no permission check.** Any signed-in account, a trader login included, could create a full database backup (every trader's personal data) or delete the backups. | Requires the Manage permission and the Settings audience. |
| **Footfall, pitch and land-use restriction forms accepted any signed-in account** (only "view" was checked), so a trader login could change land-use restrictions, which decide where traders may be placed. | Footfall needs Report, pitches and restrictions need Manage; a Mayor is limited to their own division's markets. |
| **Notification messages were inserted as HTML.** They often carry a trader's name, so a name containing markup would have been interpreted. | Messages are now plain text. |

### On "saved in the local database"
Nothing about where data is stored changed or needed to: every save in this system writes to the MySQL database, as the screenshot's `"changed":3` shows. The problem was only that the person was not told so, and was left on a blank page.

**Still not run.** Run `php database/verify.php`, then: edit a trader and save (stay on the page, see the confirmation); sign in as `revenue` and open Reports (only six registers offered); as `planner`, type `/pages/settings.php` (back on the dashboard with a note).

## 4. Files
New: `lib/AllocationPlanner.php`, `pages/ai_planner.php`, `api/ai_plan_action.php`, `partials/dash_aiplanner.php`, `assets/css/civic.css`, `CHANGES_v2.md`.
Changed: `lib/Auth.php`, `lib/bootstrap.php`, `lib/Engines.php`, `lib/Recommendations.php`, `lib/RoleDashboard.php`, `layouts/header.php`, `auth/login.php`, `config/config.php`, `api/bootstrap.php`, `api/export.php`, `api/vendor_save.php`, `api/allocation_action.php`, `api/recommendation_action.php`, `api/vendor_options.php`, `api/zone_detail.php`, `pages/licensing.php`, `pages/analytics.php`, `pages/congestion.php`.

## 5. Suggested checks before you present
1. `php database/verify.php`
2. Sign in as `planner`, open AI Planner, draw a plan, compare policies, submit it.
3. Sign in as `mayor.central` and approve one proposal (it works); sign in as `planner` and try to approve your own (refused).
4. As `planner`, open `/pages/licensing.php` (refused). As `revenue`, try exporting the vendor register (refused).
5. Switch to dark mode on the dashboard, the AI Planner and the vendor register.

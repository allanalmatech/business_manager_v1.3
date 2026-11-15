# Business Manager — Roadmap and Progress

Last updated: 2026-11-15 · App version: 1.4.0 · Mobile app: 1.4.0+1

Status key: ✅ done and tested · 🟡 in progress · ⬜ not started

Work is ordered by relevance: things that can lose money or data come first,
then things that are broken, then new features, then the mobile app.

## Progress at a glance

| Phase | Scope | Status |
|---|---|---|
| 1 | Security hardening | ✅ |
| 2 | Installer, uninstaller, migrations, one-click updates | ✅ |
| 3 | Broken features and POS correctness | ✅ |
| 4 | UI: responsive layout, sidebar, themes, settings | ✅ |
| 5 | Missing business features | ✅ |
| 6 | Sync API for mobile | ✅ |
| 7 | Mobile app | ✅ written, not yet built |

**Next:** build the Android APK (needs a machine with Flutter installed — it
cannot be downloaded in this environment), test it against a real server and a
real Bluetooth printer, then work through the "still to do" list at the end.

---

## Phase 1 — Security hardening ✅

- ✅ Removed the database dump, `system.zip`, debug scripts and logs from the web root
- ✅ `.htaccess` blocks dumps, archives, logs, `.git` and internal folders
- ✅ `uploads/` can no longer execute scripts
- ✅ Upload extension comes from the file's real content, not its name (5 upload paths)
- ✅ "Fetch image from URL" refuses private/internal addresses
- ✅ One CSRF token, enforced centrally for every signed-in write request (`includes/csrf.php`, `assets/js/core.js`)
- ✅ Sign-in throttling: 5 failures per account in 15 minutes (web and mobile login)
- ✅ Demo usernames and passwords removed from the login page
- ✅ `seed_database.php` is command-line only
- ✅ Receipt search requires sign-in and the `pos.view` permission
- ✅ Database credentials moved to `config/db.local.php` (not tracked by git)
- ✅ Stored payment secret keys are no longer printed back into the settings form
- ⬜ **Owner action:** purge the old dump from git history and change every password that was in it (see "Decisions needed")
- ⬜ Decide whether audit logs may be deleted at all (currently allowed with `audit.manage`)
- ⬜ Session idle timeout and "sign out everywhere"
- ⬜ Optional two-step sign-in for super admins

## Phase 2 — Install, uninstall, update ✅

- ✅ `setup.php` installer: server check, database, business details, your own admin account
- ✅ `setup.php` uninstaller: super admin password + typed confirmation, automatic backup to `storage/backups/`
- ✅ Migration runner (`includes/migrations.php`, `migrations/NNN_name.php`), tracked in `schema_migrations`
- ✅ Administration → Updates: check and pull updates from git, fast-forward only, then run migrations
- ✅ Update history recorded in `update_history` and `storage/logs/update.log`
- ✅ `docs/INSTALL.md`
- ⬜ Zip-download fallback for hosts where PHP cannot run git
- ⬜ Maintenance-mode page shown to staff while an update runs
- ⬜ Scheduled automatic database backups with download from the admin area

## Phase 3 — Broken features and POS correctness ✅

- ✅ Sales failed on strict-mode databases (stock movement rows lacked before/after quantities)
- ✅ Prices are taken from the database; cashiers without `pos.edit_price` always sell at list price
- ✅ Discounts cannot take a line below the wholesale price
- ✅ Stock rows are locked during a sale; stock can never go negative (tested with 4 tills racing for 2 units)
- ✅ Sequential document numbers per year from `doc_sequences` (e.g. `RC-2026-000001`)
- ✅ Fractional quantities (kg, litres) are stored exactly; piece items must be whole numbers
- ✅ Overpayment is returned as change instead of being booked as income
- ✅ Cost at time of sale is stored on each line; profit report totals no longer multiply revenue by line count
- ✅ Sales are now written to the audit trail (they were silently skipped)
- ✅ Base URL was wrong on web-root installs (every nested page built broken links)
- ✅ Forgot password: link works, email is actually sent, tokens are stored hashed, no account enumeration
- ✅ Roles: create, rename and delete endpoints added (the page called files that did not exist)
- ✅ Payment settings save endpoint added
- ✅ Theme upload crash fixed (`has_permission()` now lives in `includes/rbac.php`)
- ✅ Saving a store no longer errors after the update
- ✅ Top-bar search works (products, customers, sales documents)
- ✅ Accountant dashboard shows live figures
- ✅ Requires PHP 8.0+ (checked by the installer) instead of half-supporting PHP 7
- ⬜ POS screen should show the enforced price when the cashier cannot edit prices
- ✅ Returns: same stock locking and movement ledger as sales
- ⬜ Installments: CSRF-safe actions reviewed and tested end to end
- ✅ Notifications centre (low stock, expiring, unpaid, returns, installments)
- ✅ Removed 31 empty placeholder files
- ✅ Schema changes moved out of request handlers into migrations

## Phase 4 — UI ✅

- ✅ One set of design tokens bridged to Bootstrap, so every component follows the theme
- ✅ Responsive from 320 px phones to wide desktops; tables become labelled cards on small screens
- ✅ Sidebar grouped by job (Sell, Stock, Money, People, Admin), animated groups, icon rail with fly-outs, mobile drawer
- ✅ All 10 themes rebuilt by `tools/build_themes.py`, which refuses to write if any of 23 colour pairs falls under WCAG AA
- ✅ Toasts and a proper confirm dialog replace 84 `alert()`/`confirm()` calls
- ✅ Bootstrap, icons and Cropper served locally — the till keeps its styling with no internet
- ✅ Shared components: empty state, stat tile, toasts, confirm, table stacking
- ✅ POS: hold/resume sales, F2/F4/F8/F9 shortcuts, mobile cart bar, barcode scan-to-add, working category filters
- ✅ Settings rebuilt as grouped cards with switches and pickers, saving as you go
- ✅ Print stylesheet for receipts (58/80 mm) and A4
- ⬜ Dark mode that follows the phone's own setting (themes exist; no automatic switch)

## Phase 5 — Missing business features ✅

- ✅ Tax/VAT: rate, inclusive or exclusive, shown at the till, stored per sale, on the shift report
- ✅ Cashier shifts: opening float, cash in/out, count and close, shift (Z) report
- ✅ Barcodes: unique per product, scan-to-sell in the POS
- ✅ Email by SMTP or the server's mail, SMS via Africa's Talking, EgoSMS or a send link, each with a test button
- ✅ Scheduled reminders for unpaid documents and installments (`cron/run.php`), recorded so they never send twice
- ✅ Customer credit limits, enforced at the till
- ✅ Stock transfers between locations
- ✅ Automated tests (`tests/run.php`) — 24 checks over migrations, stock, tax, settings, numbering
- ⬜ PDF receipts and invoices
- ⬜ Mobile money collection (Flutterwave keys are stored but unused)
- ⬜ Purchase orders and goods received
- ⬜ Stock-take sessions
- ⬜ Customer statements

## Phase 6 — Sync API for mobile ✅

- ✅ Versioned JSON API (`/api/v1`) with a revocable token per device
- ✅ Change feed fed by database triggers, with a full-snapshot fallback for hosts that forbid them
- ✅ Pull: everything changed since version N, paged, safe to repeat
- ✅ Push: every action carries a uuid; the server applies it once and returns the same answer on a retry
- ✅ Conflict rules: sales append-only, stock by deltas, edits checked against what the device last saw
- ✅ Offline sales accepted and flagged for review rather than refused (Administration → Devices)
- ✅ Photo upload: re-encoded server-side, metadata stripped, long cache headers
- ✅ `docs/API.md`

## Phase 7 — Mobile app ✅ (written; not yet compiled)

Flutter, in `mobile/`. Written and checked structurally, but **not compiled**:
the Flutter toolchain cannot be downloaded in the environment this was built
in. Treat the first `flutter build` as the real test.

- ✅ Layered: `core` → `domain` → `data` (local, remote, repositories) → `features`. A screen never calls the network or writes SQL.
- ✅ Offline-first: the phone's SQLite database is what every screen reads; sync is the only thing that fills it
- ✅ Queue with a uuid per action, so retrying after a crash or flat battery cannot record a sale twice
- ✅ Concurrency: stock sent as changes not totals; edits carry the value last seen and come back as conflicts
- ✅ The phone subtracts its own unsent sales from the stock it shows
- ✅ Sign in, till (search, scan, basket, payment, change due), products, stock changes, sales history, settings
- ✅ Barcode scanning in the app (camera)
- ✅ Bluetooth receipt printing: raw serial connection in Kotlin, ESC/POS bytes built in Dart
- ✅ Product photos from the camera, shrunk to ≤1280 px and ~200 KB before upload, metadata stripped
- ✅ Image cache with a 60 MB budget; least recently looked at is deleted first
- ✅ Android manifest, Gradle files, ProGuard rules, README
- ✅ `tools/check_mobile.py` — structural checks in place of a compiler
- ⬜ **Build the APK and run it** against a real server and a real printer
- ⬜ iOS target (the code is portable; only the printer plugin is Android-only)
- ⬜ Widget tests, and an end-to-end test against a staging server
- ⬜ Play Store listing and signing key

---

## Decisions needed from the owner

1. **Git history.** The old database dump (password hashes, sessions, customer rows) is still in the repository's history. Removing it means rewriting history and force-pushing, and the repository is public. Until then, treat every password in that dump as exposed.
2. **Audit logs.** Should deleting audit logs be possible at all, or only archiving?
3. **Discount floor.** Discounts currently cannot take a line below the wholesale price. Confirm that is the rule you want.
4. **Uploaded images in git.** `uploads/` is tracked. On a live server this makes updates clash with new uploads; it should be untracked once you confirm the live images are backed up.
5. **Mobile app server address.** The APK is built with `--dart-define=BM_BASE_URL=...`. Confirm the address to build against.

## Still to do, in the order I would take it

1. Build the Android APK and test it against the live server and a real printer
2. PDF receipts and invoices
3. Mobile money collection
4. Purchase orders and goods received
5. Dark mode following the phone's setting
6. iOS build

## Change log

See `CHANGELOG.md`.

# Business Manager — Roadmap and Progress

Last updated: 2026-11-15 · App version: 1.4.0

Status key: ✅ done and tested · 🟡 in progress · ⬜ not started

Work is ordered by relevance: things that can lose money or data come first,
then things that are broken, then new features, then the mobile app.

## Progress at a glance

| Phase | Scope | Status |
|---|---|---|
| 1 | Security hardening | ✅ |
| 2 | Installer, uninstaller, migrations, one-click updates | ✅ |
| 3 | Broken features and POS correctness | 🟡 most done |
| 4 | UI: responsive layout, sidebar, themes, settings | ⬜ |
| 5 | Missing business features | ⬜ |
| 6 | Sync API for mobile | ⬜ |
| 7 | Mobile app | ⬜ |

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

## Phase 3 — Broken features and POS correctness 🟡

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
- ⬜ Returns: same stock locking and movement ledger as sales
- ⬜ Installments: CSRF-safe actions reviewed and tested end to end
- ⬜ Notifications centre (low stock, overdue installments, unpaid documents)
- ⬜ Remove the remaining 30 empty placeholder files or implement them
- ⬜ Remove schema changes that still run inside requests (`CREATE TABLE IF NOT EXISTS` in several modules)

## Phase 4 — UI ⬜

- ⬜ One design-token file (colour, spacing, type scale) used by every page; delete per-page `<style>` blocks and inline styles
- ⬜ Responsive layout from 320 px phones to wide desktops; tables collapse to cards on small screens
- ⬜ Sidebar: grouped by job (Sell, Stock, Money, People, Reports, Admin), remembers open group, smooth open/close, icon-only rail on tablets, drawer on phones
- ⬜ Themes: rebuild all 10 so text, borders and focus rings pass WCAG AA contrast; automated contrast check in the repo
- ⬜ Replace 84 browser `alert()`/`confirm()` calls with toasts and confirm dialogs
- ⬜ Bundle Bootstrap, icons and Cropper locally so the till works without internet
- ⬜ Shared components: table, filter bar, pagination, empty state, modal, flash message
- ⬜ POS: keyboard shortcuts, barcode field, hold/park sale, clear change-due display
- ⬜ Settings: grouped cards (Business, Sales & Tax, Receipts & Printing, Payments, Messaging, Appearance, System) with switches and pickers instead of raw key/value rows
- ⬜ Print stylesheet for receipts (58 mm / 80 mm) and A4 invoices

## Phase 5 — Missing business features ⬜

- ⬜ Tax/VAT: per-product rate, inclusive/exclusive pricing, tax on documents and reports
- ⬜ Cashier shifts: opening float, cash in/out, end-of-day count and Z-report
- ⬜ Barcodes: product barcode field, scan to sell, label printing
- ⬜ Email (SMTP) and SMS gateway delivery; message queue with retries
- ⬜ Scheduled reminders for unpaid documents and installments (cron scripts are empty today)
- ⬜ PDF receipts, invoices and delivery notes
- ⬜ Mobile money collection (Flutterwave keys are stored but unused)
- ⬜ Purchase orders and goods received, linked to suppliers and cost history
- ⬜ Stock transfers between locations and stock-take sessions
- ⬜ Customer credit limits and statements
- ⬜ Automated test suite for sales, returns, stock and permissions

## Phase 6 — Sync API for mobile ⬜

Design goals: the server stays the single source of truth; every write is safe to retry.

- ⬜ Versioned JSON API (`/api/v1`) with token sign-in per device (revocable)
- ⬜ Every syncable row gets `updated_at`, a soft-delete flag and a monotonically increasing `sync_version`
- ⬜ Pull: "give me everything changed since version N", paged
- ⬜ Push: each offline action carries a client-generated UUID; the server applies it once and returns the same result on retry
- ⬜ Conflict rules: sales are append-only; stock is changed by deltas, never overwritten; edits to shared records use version checks and return the newer copy on conflict
- ⬜ Oversell while offline: sale is accepted, flagged for review, and stock shown as negative-pending
- ⬜ Image endpoint with size limits, resizing and long-lived cache headers (ETag)

## Phase 7 — Mobile app ⬜

Proposed stack: Flutter (one codebase for Android and iOS), SQLite for local data.

- ⬜ App shell: sign-in, role-based home, bottom navigation (Sell, Products, Stock, Reports, More)
- ⬜ Layered structure: UI → use cases → repositories → local database + API client
- ⬜ Offline-first: every screen reads from the local database; a background queue pushes changes and pulls updates when the network returns
- ⬜ Sync status always visible (pending count, last sync, conflicts to review)
- ⬜ In-app barcode scanning with the camera
- ⬜ Bluetooth receipt printing (raw ESC/POS) behind a printer plugin interface
- ⬜ Product photos from the camera, resized and compressed on the phone before upload (target ≤ 200 KB)
- ⬜ Image cache with a size cap; least-recently-used images are evicted first
- ⬜ Cached reference data (products, prices, customers) refreshed by sync version so it never drifts from the server
- ⬜ Feature parity checklist against the web modules
- ⬜ Low-end device budget: cold start under 3 s, APK under 25 MB

---

## Decisions needed from the owner

1. **Git history.** The old database dump (password hashes, sessions, customer rows) is still in the repository's history. Removing it means rewriting history and force-pushing, and the repository is public. Until then, treat every password in that dump as exposed.
2. **Audit logs.** Should deleting audit logs be possible at all, or only archiving?
3. **Discount floor.** Discounts currently cannot take a line below wholesale price. Confirm that is the rule you want.
4. **Mobile stack.** Flutter is proposed; say so if you prefer React Native or native Android.
5. **Uploaded images in git.** `uploads/` is tracked. On a live server this makes updates clash with new uploads; it should be untracked once you confirm the live images are backed up.

## Change log

See `CHANGELOG.md`.

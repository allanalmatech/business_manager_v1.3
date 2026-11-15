# Changelog

## 1.4.0 — 2026-11-15

### Phone app (new, in `mobile/`)

Flutter till that works without a network. Written and structurally checked;
not yet compiled, since the Flutter toolchain was not available where it was
built.

- Offline-first: every screen reads the phone's own database; sync is the only writer
- Queued actions with a uuid each, so a retry after a crash cannot record a sale twice
- Stock sent as changes rather than totals, and edits carry the value last seen, so two phones cannot overwrite each other
- The phone subtracts its own unsent sales from the stock it shows
- Barcode scanning, Bluetooth (ESC/POS) receipt printing over a raw serial connection
- Camera photos shrunk to ~200 KB before upload, metadata stripped
- Product photo cache with a size budget, discarding the least used first

### Server

Security
- Removed the committed database dump, code archive, debug scripts and logs
- Central CSRF protection for all signed-in write requests
- Sign-in throttling on web and mobile login
- Uploads: extension from detected content type; `uploads/` cannot execute scripts
- Image-from-URL refuses internal addresses
- Demo credentials removed from the login page; seed script is CLI only
- Database credentials moved to an untracked local file

Install and update
- `setup.php` installs and uninstalls from the browser (uninstall takes a backup first)
- Migration runner with tracked, ordered migrations
- Administration → Updates pulls from git and applies migrations

Sales
- Server-side prices, wholesale floor on discounts, locked stock, no negative stock
- Sequential yearly document numbers
- Exact fractional quantities; change due on overpayment
- Cost captured per line at sale time; profit totals corrected
- Fixed sales failing on strict-mode databases
- Sales now appear in the audit trail
- Pending database updates apply themselves, so a pull cannot leave the till unable to save a sale
- POS search, product images and category filters
- Tax, barcodes, shifts, credit limits, stock transfers, email and SMS, reminders

Phone support
- Versioned token API at /api/v1 with per-device sign-in
- Change feed for sync, with a full-snapshot fallback
- Offline sales accepted and flagged for review under Administration > Devices
- Shared sale service used by both the web till and the API

Fixes
- Base URL on web-root installs
- Forgot password flow (sends email, hashed tokens)
- Roles create/update/delete, payment settings save, theme upload, store save
- Top-bar search; live accountant dashboard

## 1.0 — Enhanced UI & Themes

- Theme system with 10 predefined themes and theme upload
- Bootstrap Icons replace emoji icons
- CSS refactored to variables
- Improved super admin dashboard, sidebar and branding

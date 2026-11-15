# Changelog

## 1.4.0 — 2026-11-15

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

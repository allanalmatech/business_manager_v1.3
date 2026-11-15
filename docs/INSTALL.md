# Installing Business Manager

## Requirements

- PHP 8.0 or newer with `mysqli`, `mbstring`, `fileinfo`, `gd`
- MySQL 5.7+ or MariaDB 10.3+
- Apache with `mod_rewrite` (the `.htaccess` rules protect internal folders)
- Writable folders: `config/`, `uploads/`, `storage/`

## Install

1. Put the files on the server. To get one-click updates later, use git:
   `git clone https://github.com/allanalmatech/business_manager_v1.3.git`
2. Create an empty database and a database user in your hosting panel.
3. Open `https://your-site/setup.php` and fill in the form. You choose the admin username and password; no default accounts are created.
4. Sign in.

The installer writes `config/db.local.php` and `config/installed.lock`. Neither is tracked by git.

## Update

Administration → Updates → **Check for updates** → **Update now**.
This pulls the latest code from git and applies any database changes. It only works when the site was installed with `git clone` and the host lets PHP run `git`. Otherwise upload the new files and press **Update database** on the same page.

## Uninstall

Open `setup.php`, enter a super admin's username and password and type `UNINSTALL`.
A full database backup is saved to `storage/backups/` first. Then the tables and saved database settings are removed. Delete the folder to remove the program files.

## Moving an existing install to this version

1. Back up the database.
2. Replace the files (or `git pull`).
3. Create `config/db.local.php` returning your database settings (copy the array shape from `config/db.php`).
4. Sign in as super admin and open Administration → Updates → **Update database**.

## Command line

`php seed_database.php` loads demo data. It resets demo account passwords, so never run it on a live shop.

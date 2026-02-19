# Defense Deploy Guide (No Data Loss)

This guide is for non-technical use. Follow this before every big change or deployment.

## 1) Create a full backup first

Open in browser:

- http://localhost/CALLOWAYBACKUP/tools/defense_backup.php

Result:

- A full SQL backup is saved in the backups folder.
- File name format: full_backup_YYYY-MM-DD_HH-MM-SS.sql

## 2) Run deploy health check

Open in browser:

- http://localhost/CALLOWAYBACKUP/tools/defense_preflight.php

If status is FAIL, do not deploy yet.

## 3) If something breaks, restore quickly

Open in browser:

- http://localhost/CALLOWAYBACKUP/tools/defense_restore.php

Steps:

1. Select the backup file.
2. Type RESTORE.
3. Click Run Restore.

The tool makes another safety backup before restore.

## 4) What to copy for hosting

Always keep these together:

- Project files (htdocs/CALLOWAYBACKUP)
- Databases folder (SQL setup/recovery scripts)
- uploads/products folder (product images)
- latest full backup SQL file

## 5) Rules to prevent data loss

- Never run destructive seed/reset scripts on production.
- Never delete XAMPP/MySQL without exporting full backup first.
- Keep at least 3 recent full backups in a separate drive/cloud.
- Before defense day: run backup + preflight and store backup in 2 locations.
- Do not permanently delete old scripts; move them to _archive_dev first.
- Only archive scripts after checking they are not required by runtime includes/routes.

## 6) Emergency checklist

If login/users/employees disappear:

1. Stop changes immediately.
2. Restore latest full backup.
3. Re-check with preflight page.
4. Confirm users, employees, products, and images are present.

# Azure Deployment Notes

These are practical changes to make before hosting on Azure. Keep testing on XAMPP until cutover.

## Required before deploy

- Set PHP to production mode: disable display errors and enable logging.
- Move secrets out of repo: database credentials, API keys, and email SMTP creds should come from environment variables.
- Update database connection settings for Azure MySQL (host, port, user, password, database).
- Ensure file and folder permissions allow uploads and logs, but do not allow world-writable access.
- Confirm session storage and cookie settings work behind HTTPS.
- Turn on HTTPS only and set secure cookies.
- Verify scheduled tasks (backup/email crons) are moved to Azure WebJobs or Azure Functions.

## Recommended

- Add a production config switch (e.g., APP_ENV=production) to toggle debug output and verbose logging.
- Enable caching and compression in the web server settings.
- Add database indexes for large tables if reports feel slow.
- Add a health check endpoint for uptime monitoring.

## After deploy checks

- Login, POS sale, online order, and reports all load without warnings.
- Dark mode does not flash and UI is consistent.
- Email sending works (password reset, notifications).
- Backup and restore work with Azure storage paths.

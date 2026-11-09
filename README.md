# ELECTRINC

## Database configuration

Set these environment variables in the PHP web-server environment before deploying:

- `DB_HOST`, `DB_USER`, `DB_PASS`, and `DB_NAME` for the main, admin, member, franchise, API, and monthly cron applications.
- `JWT_SECRET` for signing API authentication tokens.

Use a unique, randomly generated `JWT_SECRET`; changing it invalidates existing API tokens. Production database credentials must be set explicitly. Local API development defaults to the `root` MySQL account with an empty password.
- `BULL_DB_HOST`, `BULL_DB_USER`, `BULL_DB_PASS`, and `BULL_DB_NAME` for the Bull application.

Local development defaults to MySQL on `localhost` with the `root` account and an empty password. The main applications try the local databases `electrinc` and `ind_new` unless `DB_NAME` is set. The Bull application requires its database name and password to be configured.

Do not commit credentials or personal documents. The `.gitignore` excludes environment files and two signed PDFs in `documents`.

# Upgrade Guide

## Before upgrading

1. Back up the database, `config.php`, and the configured local upload directory.
2. Confirm that the server runs PHP 7.4 through 8.3 with `pdo_mysql` and `curl` enabled.
3. Replace application files without replacing `config.php`, `file/`, or `install/install.lock`.

## Upgrade to database version 1002

Open `/install/update.php` once after deployment. This creates the login rate-limit table and initializes API-token settings for existing installations.

All administrators and end users must sign in again after the upgrade because authentication cookies now use a new signed-token format. Existing administrator passwords are migrated to `password_hash()` on the first successful login.

## API callers

Existing API callers continue to work after upgrade because token enforcement is initially disabled for upgraded sites. Configure an API token in the admin API settings, update callers to send `X-Api-Key` or `api_token`, then enable API-token enforcement.

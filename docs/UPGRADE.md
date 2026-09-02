# Upgrade Guide

## Before upgrading

1. Back up the database, `config.php`, and the configured local upload directory.
2. Confirm that the server runs PHP 7.4 through 8.4 with `pdo_mysql` and `curl` enabled.
3. Replace application files without replacing `config.php`, `file/`, or `install/install.lock`.

## Online updates

Maintained releases can be installed from **Admin → Online Update**. The updater downloads a tagged package from the maintenance repository, verifies its SHA-256 digest, creates a source backup, and preserves `config.php`, local uploads, and `install/install.lock`. If a release includes a database version change, the updater sends the administrator to the migration step after replacing the program files.

## Upgrade to database version 1002

Open `/install/update.php` once after deployment. This creates the login rate-limit table and initializes API-token settings for existing installations.

All administrators and end users must sign in again after the upgrade because authentication cookies now use a new signed-token format. Existing administrator passwords are migrated to `password_hash()` on the first successful login.

## Upgrade to database version 1003

Open `/install/update.php` once after deploying this release. The migration adds share expiration and maximum-access fields to the file table. Existing shares remain permanent and unlimited, so their current behavior does not change.

## Upgrade to database version 1004

This migration applies the LinkNest name only when the site still uses the untouched legacy default title or description. Administrator-customized site names and descriptions are preserved.

## Upgrade to database version 1005

This migration switches client IP detection to the direct connection address by default and adds an explicit trusted-proxy list. Configure the proxy IP or CIDR range only when the site is actually behind a trusted reverse proxy or CDN.

## Upgrade to database versions 1006 and 1007

Version 1006 creates the independent share table and migrates each existing file link into a default share. Old `file.php?hash=...` links remain compatible. Version 1007 adds privacy-preserving access logs and daily traffic summaries. Existing files and stored objects are not moved or duplicated.

## Upgrade to database version 1008

This migration adds per-share referer rules, user-agent blocking, request-rate limits, daily/monthly traffic caps, HTTPS webhook alerts, and the supporting rate/alert tables. All new controls default to disabled, so existing public links keep their previous behavior until an owner enables protection.

For true byte-per-second throttling on large local files, use the web server's native delivery controls such as Nginx `X-Accel-Redirect`; PHP request limits and traffic caps are intended for abuse prevention, not precise transfer shaping.

## Upgrade to database version 1009

This migration adds the recycle-bin fields, file-version snapshots, administrator audit log, storage-health records, and a retryable storage-cleanup queue. Existing files and share links are kept as-is. New protection defaults are conservative: recycle-bin retention is 30 days, version retention is 90 days with at most 10 snapshots per file, and password-protected shares allow 5 failed attempts per IP before a 15-minute temporary lock.

After upgrading, open **Admin → Operations Center** once. Set the actual retention policy, record the current backup and recovery-drill dates, run a storage health check, and configure a daily CLI job:

```bash
php /path/to/linknest/cron.php
```

The task clears only expired recycle-bin items and expired snapshots. Physical object deletion is retried through an internal cleanup queue so a temporary storage failure does not turn into silent data loss.

## API callers

Existing API callers continue to work after upgrade because token enforcement is initially disabled for upgraded sites. Configure an API token in the admin API settings, update callers to send `X-Api-Key` or `api_token`, then enable API-token enforcement.

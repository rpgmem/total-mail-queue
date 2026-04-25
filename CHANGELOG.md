# Changelog

All notable changes to **Total Mail Queue** are documented in this file.

The format is based on [Keep a Changelog 1.1.0](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

> Tracking work that will roll into the next minor release, **2.3.0**.

### Added

- **Automated test suite (PHPUnit 9).**
  - Unit tests (25) covering pure-PHP helpers without booting WordPress: AES-256-CBC password round-trip and tamper detection, JSON encode/decode with backwards-compatible unserialize fallback (object instantiation blocked via `allowed_classes => false`), SMTP account picker (`cycle_sent` vs `send_bulk`), in-memory SMTP counter increment, and base64 redaction of inline image data in HTML preview.
  - Integration tests (27) using Brain Monkey + Mockery for hook-and-database interactions, covering `wp_tmq_get_settings` (defaults, queue interval unit conversion, 10s minimum clamp), `wp_tmq_sanitize_settings` (whitelist of admin-settable keys, blocks `tableName`/`smtpTableName`/`triggercount` injection), `wp_tmq_prewpmail` (filter pass-through, `X-Mail-Queue-Prio: High`/`Instant`, block-mode behavior, `wp_mail_content_type` header, instant-email filter skip), `wp_tmq_capture_phpmailer_config` (no-op when nothing changed, capture path with encrypted password), and `wp_tmq_mail_failed` (auto-retry counter, error finalization, unknown-message fallback).
  - Functional tests (34) running against a real WordPress + MySQL via `wp-phpunit/wp-phpunit`, covering activation/`dbDelta` schema and indexes, retention cleanup (`clear_queue` age-based + `log_max_records` cap), REST endpoint `/tmq/v1/message/{id}` (capability + 404), bulk actions delete/resend/force_resend, full cron flow (`wp_tmq_search_mail_from_queue` with priority ordering, batch limit, disabled/block modes, smtp-only fallback, diagnostics), AJAX `wp_tmq_test_smtp` (permission, nonce, validation, connection failure), and XML export/import round-trip with whitelist enforcement and XXE protection (`LIBXML_NONET`).
  - In-memory `MockWpdb` test double that records every call and lets tests pre-load read responses.
- **Static analysis with PHPStan (level 5)** using `szepeviktor/phpstan-wordpress`. Existing issues captured in a baseline so CI fails only on new violations.
- **Coding standards with PHP_CodeSniffer + WordPress Coding Standards 3.x**, configured to enforce security and API correctness (escaping, prepared SQL, text-domain consistency, hook prefixing, deprecated functions) — style sniffs are intentionally not enforced because the existing codebase predates them.
- **PHP compatibility checks** via `phpcompatibility/phpcompatibility-wp` against the declared minimum PHP 7.4.
- **GitHub Actions workflow** running PHPUnit (PHP 8.1, 8.2, 8.3), functional tests against MySQL 8 (PHP 8.1 + 8.3), PHPStan and PHPCS on every push/pull request.
- **`bin/install-wp-tests.sh`** helper that bootstraps the WordPress test database for local development.
- **CHANGELOG.md** following the Keep a Changelog format.

### Changed

- **Full WordPress coding standard adopted.** `phpcs.xml.dist` now references `<rule ref="WordPress"/>` directly. A single `phpcbf` pass auto-corrected 3,395 mechanical violations across the three plugin files (spaces→tabs, parenthesis spacing, control-structure spacing, concatenation padding, array indentation, comma spacing). Authorial fixes (variable naming, docblocks, Yoda, etc.) are scheduled across follow-up phases F2–F4 and excluded with rationale in the ruleset until then. No behavior changes — the suite of 86 tests continues to pass.
- Added `.git-blame-ignore-revs` listing the F1 reformat commit so `git blame` and the GitHub blame UI continue to surface the real authorship of the surrounding lines.
- `wp_tmq_prewpmail` queue-alert payload now uses `wp_json_encode()` instead of `json_encode()`, matching WordPress conventions for handling encoding edge cases.
- `wp_tmq_handle_export()` was split into a pure `wp_tmq_build_export_xml()` helper plus a thin handler that emits headers and exits — the pure helper makes the export logic unit-testable without touching the HTTP layer.
- Several intentional uses of `base64_encode`/`base64_decode` (binary IV+ciphertext storage for SMTP passwords; transport-encoding of captured PHPMailer config in email headers) are now annotated with explicit `phpcs:ignore` justifications.
- Legacy fallback `unserialize()` call (used only when reading data written by older plugin versions) is annotated to make the `allowed_classes => false` safety guarantee visible at the call site.

### Deprecated

- _Nothing yet._

### Removed

- _Nothing yet._

### Fixed

- _Nothing yet._

### Security

- _Nothing yet._

---

## [2.2.1] - 2026-01-XX

### Added

- Configurable SMTP Timeout and Cron Lock Timeout settings in admin panel.
- Cross-process cron lock using MySQL `GET_LOCK` to prevent overlapping batch sends.

### Changed

- WordPress Plugin Check compliance: output escaping, `ABSPATH` guards, safe redirects.
- Memory optimization: lazy-load email bodies per iteration instead of loading all at once.
- Replaced `file_put_contents` with the WP_Filesystem API for attachment directory protection.

### Removed

- `error_log`/`print_r` development calls from production code.
- `%i` placeholder for compatibility with WordPress < 6.2.

### Fixed

- SMTP accounts no longer blocked mid-cycle by the `send_interval` check.

---

## [2.2.0] - 2025-XX-XX

### Added

- SMTP test connection button to verify credentials before sending.
- Export/import plugin settings and SMTP accounts via XML.
- Status filter on the log table (sent / error / alert).
- Auto-retry failed emails with configurable retry limit.
- Resend and force-resend actions that remove the original log entry.
- Cron diagnostics panel on the Retention tab.
- Detection of conflicting `pre_wp_mail` filters from other plugins.
- Info column in the log with retry count and error details.
- Bulk delete confirmation dialog.

### Changed

- JSON serialization replaces PHP `serialize()` (with backwards compatibility for legacy data).
- Strict comparisons throughout, `wp_kses_post` on notices, consistent `wpdb` formats.
- Inline CSS moved to `assets/css/admin.css`.
- Asset cache-busting via version parameter.
- SMTP counter reset runs once per cron instead of per email.
- SQL `LIMIT`/`OFFSET` pagination instead of loading all rows.

### Fixed

- Set envelope sender (`$phpmailer->Sender`) alongside From header.
- Instant email tracking now correctly updates status on success/failure.
- `NOW()` replaced with WordPress local time in SQL queries.
- Attachments stored in `wp-content/uploads/tmq-attachments/` with `.htaccess` protection.
- Use `wp_generate_password()` for attachment subfolder names.

### Security

- Mandatory nonce verification on all bulk actions.
- Nonce protection on test email insertion.
- XXE prevention on XML import (`LIBXML_NONET`).
- Settings whitelist on import and `register_setting` `sanitize_callback`.
- Encrypted captured SMTP passwords in queue headers.
- Browser autofill prevention on SMTP username field.
- Database indexes on status column (`idx_status_retry`, `idx_status_timestamp`).

---

## [2.1.0]

### Added

- SMTP accounts management with priority, daily and monthly sending limits.
- Multiple send methods: `auto`, SMTP only, PHP default.
- Capture and replay `phpmailer_init` configurations from other plugins.
- Block mode: retain all emails without sending.
- Log max records limit setting.
- Database table for SMTP accounts.
- Encrypted SMTP password storage (AES-256-CBC).

---

## [1.5.0]

### Added

- Fork of *Mail Queue* by WDM. Renamed all prefixes and identifiers; new branding: **Total Mail Queue**.

---

## [1.4.6]

### Added

- Support for the `pre_wp_mail` hook.

## [1.4.5]

### Added

- Check for incompatible plugins.

### Fixed

- Minor bug fixes.

## [1.4.4]

### Changed

- Performance improvements for large emails.

## [1.4.3]

### Changed

- Updated bulk actions for log and queue lists.

## [1.4.2]

### Changed

- Database improvements.

## [1.4.1]

### Fixed

- Refine detection for HTML when previewing emails.
- Catch HTML parse errors when previewing emails.

## [1.4.0]

### Added

- Support for previewing HTML emails as plain text.
- Improved preview for HTML emails.

### Fixed

- Minor bug fixes.

## [1.3.1]

### Added

- Support for the following `wp_mail` hooks: `wp_mail_content_type`, `wp_mail_charset`, `wp_mail_from`, `wp_mail_from_name`.

### Fixed

- Minor bug fixes.

## [1.3.0]

### Added

- Option to set the interval for sending emails in minutes or seconds.
- Send emails with high priority on top of the queue.
- Send emails instantly without delay bypassing the queue.

### Changed

- Refactor to use WordPress Core functionality.

## [1.2.0]

### Changed

- Performance and security improvements.

## [1.1.0]

### Added

- Resend emails.
- Notification if WordPress can't send emails.

## [1.0.0]

### Added

- Initial release.

[Unreleased]: https://github.com/rpgmem/total-mail-queue/compare/v2.2.1...HEAD
[2.2.1]: https://github.com/rpgmem/total-mail-queue/compare/v2.2.0...v2.2.1
[2.2.0]: https://github.com/rpgmem/total-mail-queue/compare/v2.1.0...v2.2.0
[2.1.0]: https://github.com/rpgmem/total-mail-queue/compare/v1.5.0...v2.1.0
[1.5.0]: https://github.com/rpgmem/total-mail-queue/releases/tag/v1.5.0

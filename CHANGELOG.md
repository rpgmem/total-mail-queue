# Changelog

All notable changes to **Total Mail Queue** are documented in this file.

The format is based on [Keep a Changelog 1.1.0](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.5.0] - 2026-04-27

> A new **HTML template engine** ports the visual styling layer of the GPL Email Templates plugin into Total Mail Queue's namespaced architecture. Every outgoing email is now wrapped in a configurable card-on-light-grey envelope before it leaves the server (or before it lands in the queue, in queue mode), with an admin tab to customize colors / fonts / logo / footer, a "Send test email" button, and a WooCommerce-aware token registry that pulls order context into customer emails.

### Added

- **Phase T1 — HTML wrapper engine.** New `Templates\Engine` class that hooks `wp_mail` at priority 100 (or is invoked directly by `Queue\MailInterceptor` in queue/block mode for "capture → process → queue" ordering). Wraps the body in a styled HTML envelope, forces `Content-Type: text/html`, and replaces `{token}` placeholders. Skips full-HTML messages (`<html>` / `<!DOCTYPE` near the top) so builders like Elementor / MemberPress pass through untouched, plus a `wp_tmq_template_skip` filter for explicit opt-out. The wrapper template (`src/templates/default-html.php`) is table-based with inline CSS for cross-client rendering, system font stack (no external Google Fonts requests), RTL-aware via `language_attributes()`, and mobile-responsive via a single `@media` query.
- **Phase T2 — `Templates\Options` storage layer.** Mirrors the shape of `Settings\Options` with `defaults()` / `get()` / `update()` / `reset()` / `sanitize()` / `isEnabled()`. The 23-key option map covers Header (bg / text / alignment / padding / logo URL / logo width), Body (bg / text / link color / font family / font size / padding / max width), Footer (bg / text color / free text / powered-by toggle), Wrapper (bg / border radius / padding), and Sender (from_email / from_name). Strict per-field sanitizer: hex colors via `sanitize_hex_color` (invalid → default), integers via `absint`, enums via whitelist, URL via `esc_url_raw`, email via `sanitize_email`, plain text via `sanitize_text_field`, footer text via `wp_kses_post`. Booleans round-trip correctly through `$_POST` strings and JSON.
- **Phase T3 — Templates admin tab.** New "Templates" submenu under "Total Mail Queue", routed via `Admin\PluginPage::TAB_TEMPLATES`. The renderer (`Admin\Pages\TemplatesPage`) ships a 5-section settings form with WP-native color pickers, a `wp.media()`-backed logo picker, font-family dropdown (5 system stacks), Save / Reset / Send-test-email actions, and mode-aware notices at the top (block / disabled / queue). The "Send test email" widget calls `wp_mail()` so the recipient sees the styled HTML through the live engine. Per-tab asset gating: `wp-color-picker` + `wp_enqueue_media()` + `tmq-templates.js` load only on this tab.
- **Phase T4 — Token registry + WooCommerce handler.** New `Templates\Tokens` module owns `{token}` substitution: nine globals (`{site_title}`, `{site_url}`, `{home_url}`, `{site_description}`, `{admin_email}`, `{recipient}`, `{date}`, `{time}`, `{year}`) plus a `wp_tmq_template_tokens` filter for extensions. Bundled `Templates\WooCommerceTokens` captures the live `WC_Order` from `WC_Email->object` via the `woocommerce_email_header` action and publishes six order tokens (`{order_number}`, `{customer_first_name}`, `{customer_last_name}`, `{customer_email}`, `{order_total}`, `{order_date}`). Duck-typed so the file loads without WooCommerce installed; cost on non-WC sites is two filter registrations.
- **Send-test-email AJAX endpoint.** `Templates\TestEmailSender` (`wp_ajax_wp_tmq_send_template_test`) — capability + nonce + sanitized recipient → `wp_mail()` so the engine pipeline is exercised end-to-end.

### Changed

- **`Engine::replacePlaceholders` is now a one-liner delegate to `Tokens::replace`** — engine no longer owns the token map directly.
- **`Plugin::VERSION`**, plugin header, `readme.txt` `Stable tag` bumped to **2.5.0**.

### Filters / Actions exposed

| Hook | Purpose |
|---|---|
| `wp_tmq_template_skip` (filter) | Short-circuit wrapping for a specific email |
| `wp_tmq_template_content` (filter) | Pre-process raw body before wrapping |
| `wp_tmq_template_html` (filter) | Final pass over the rendered HTML |
| `wp_tmq_template_tokens` (filter) | Add per-source / per-plugin tokens |
| `wp_tmq_template_default_options` (filter) | Override hardcoded defaults |
| `wp_tmq_template_options` (filter) | Override resolved (post-merge) options |
| `wp_tmq_template_before_apply` / `_after_apply` (action) | Around `Engine::apply()` |

### Tests

35 new functional tests across four files: 9 in `TemplateEngineTest` (wrapping, full-HTML pass-through, toggle / block-mode skips, placeholder substitution, content-type force, reset-link cleanup, skip filter), 4 in `TemplateInterceptionTest` (queue mode wraps, block mode doesn't, engine-disabled doesn't, blocked-by-source doesn't), 8 in `TemplateOptionsTest` (defaults, roundtrip, color / email / enum / integer sanitizers, reset, default-options filter), 9 in `TemplatesPageTest` (save POST, unchecked-toggle round-trip, invalid-nonce wp_die, reset, three mode notices, toggle-off inline warning, rendered structure), and 7 in `TemplateTokensTest` (globals, unknown-token passthrough, custom filter, WC happy path with stub order, no-order fallback, malformed-email-object rejection, substitution over realistic HTML).

### Out of scope (intentional cuts vs. the GPL plugin we ported from)

- WP Customizer-based UI (replaced by a native admin page).
- WC sub-templates per email state (use the per-Source override pattern from v2.4.1 instead).
- Google Fonts dynamic loader (system stack — most clients block external fonts anyway).
- Social-links repeater (use the free-text footer with `<a>` tags).
- EDD / Gravity Forms / MemberPress integrations (out of TMQ's domain).
- Postman / Post SMTP marketing (TMQ has SMTP natively).

---

## [2.4.1] - 2026-04-26

> UX patch on top of the 2.4.0 Sources tab: the labels are now translatable via Loco / `.po` files, the admin can override label and group per-row, the table has a "Filter by group" dropdown, and the page warns when the global Operation Mode (Block / Disabled) means per-source toggles can't take effect.

### Added

- **Translatable Source labels.** `Sources\KnownSources::translatedLabel()` / `translatedGroup()` / `translateRawGroup()` route every known `source_key` through a literal `__('Password reset', 'total-mail-queue')` (and equivalents) so Loco Translate, Poedit, `wp i18n make-pot`, etc. pick the strings up. Display order at render time: admin override → translated default → raw stored value.
- **Per-row Edit / Reset of label and group.** New "Edit" action per row in `Admin\Tables\SourcesTable` opens an inline form; saving updates the new `label_override` + `group_override` columns. A "Reset" action drops the overrides and falls back to the translated default. Each table row that carries an override gets a small "(custom)" badge so the admin can spot manual overrides at a glance.
- **Filter the catalog by group.** New `?group_filter=<canonical>` dropdown in the Sources table's `extra_tablenav` lists every distinct group (translated for display, canonical as the option value). Filter narrowing matches against either `group_label` or `group_override` so admin-set "VIP" rows still appear when the admin filters by "VIP".
- **Operation-Mode warning on the Sources tab.** When the global plugin mode is **Block** (`enabled = 2`) or **Disabled** (`enabled = 0`), a notice now appears at the top of the Sources tab explaining that per-source toggles are inert in that mode and linking to Settings to switch back to Queue mode.

### Changed

- **Schema** `{$prefix}total_mail_queue_sources` gains two columns: `label_override VARCHAR(255) NOT NULL DEFAULT ''` and `group_override VARCHAR(120) NOT NULL DEFAULT ''`. `Database\Migrator::install()` (triggered automatically by the version bump) calls `dbDelta()` to add them on existing installs without disturbing any other column.
- **`Sources\Repository`** gains `updateOverrides( id, label_override, group_override )` (silently drops the group override for system sources) and `distinctGroups()` (canonical, deduplicated, includes both `group_label` and any non-empty `group_override`).
- **`Plugin::VERSION`**, plugin header, `readme.txt` `Stable tag` bumped to **2.4.1**.

### Tests

11 new functional tests in `SourcesOverridesTest`: schema columns exist, override persistence, system-source group override is dropped, display priority (override > translated > raw), `distinctGroups` includes overrides, table filter matches against either column, edit POST persists, reset action clears overrides, mode warning notice on Block, on Disabled, and the absence of the notice on Queue mode.

---

## [2.4.0] - 2026-04-26

> A new **"individual control of emails by source"** feature plus a small admin/UI cleanup pass. Each enqueued message is tagged with a `source_key` (e.g. `wp_core:password_reset`, `woocommerce:new_order`) and the admin can toggle delivery per-source. The roll-out was split across phases S1 → S5; the cleanup shipped alongside.

### Added

- **Phase S5 — pre-populated catalog (full WP-core coverage).** The Sources tab now shows every default outgoing email a stock single-site WordPress install can produce — plus a small set of well-known integrations — on a fresh install (and on every version bump). Admins can toggle any of them off without having to wait for the first email to fire and auto-register the source. Detection still happens organically for everything else via the S2 backtrace fallback.
    - `Sources\KnownSources` (new `src/sources/known-sources.php`) holds the `(source_key, label, group)` tuples and exposes a `seed()` method.
    - Seed entries (21 total):
      - **Plugin internals:** `total_mail_queue:alert` (system, un-toggleable).
      - **WordPress Core (15):** `wp_core:password_reset`, `wp_core:new_user`, `wp_core:new_user_admin`, `wp_core:password_change`, `wp_core:password_change_admin_notify`, `wp_core:email_change`, `wp_core:admin_email_change_confirm`, `wp_core:auto_update`, `wp_core:auto_update_plugins_themes`, `wp_core:comment_notification`, `wp_core:comment_moderation`, `wp_core:user_action_confirm`, `wp_core:privacy_export_ready`, `wp_core:privacy_erasure_done`, `wp_core:recovery_mode`.
      - **Popular third-party plugins (4):** `plugin:woocommerce`, `plugin:contact-form-7`, `plugin:wpforms`, `plugin:wpforms-lite`.
    - **7 new `Sources\Detector` listeners** matching the new WP-core seeds: `wp_password_change_notification_email` (password change → admin), `new_admin_email_content` (admin-email change confirm), `auto_plugin_theme_update_email` (plugin/theme auto-update report), `user_confirmed_action_email_content` (privacy request confirm), `wp_privacy_personal_data_email_content` (privacy export ready), `user_erasure_complete_email_content` (privacy erasure done), `recovery_mode_email` (recovery-mode notification). Same passthrough pattern as the existing 8 listeners.
    - `Database\Migrator::install()` calls `KnownSources::seed()` after the schema install — so activation and version bumps both pre-populate. Idempotent: existing rows are left untouched (admin-toggled `enabled` values survive re-installs).
- **4 new functional tests** (`SourcesKnownCatalogTest`): every entry exists after `Migrator::install()`; repeated `seed()` calls don't duplicate rows; a re-install doesn't flip an admin-disabled row back to enabled; the alert system source is included in the seed.
- **Phase S4 — enforcement.** Disabled sources now actually block delivery (the previous phases only persisted the catalog and previewed the toggle).
    - `Queue\MailInterceptor::handle()` calls `Sources\Repository::isEnabled()` after detecting the source. When the source is disabled the message is stored with the new `blocked_by_source` status instead of being scheduled for sending. The wp_mail() return value is still `true` so callers can't tell their message was suppressed (same swallow as block-mode).
    - **`blocked_by_source` overrides `Instant` priority.** A third-party plugin cannot set `X-Mail-Queue-Prio: Instant` to bypass an admin-imposed source block.
    - **System sources are hardcoded as always-enabled.** Any `source_key` starting with `total_mail_queue:` (currently only `total_mail_queue:alert`) is recognised by `Repository::isSystem()`; `setEnabled()` and `setEnabledByGroup()` silently refuse to flip it, and `Admin\Tables\SourcesTable` renders a non-actionable "system (always on)" badge in place of the per-row toggle.
    - `Sources\Repository::SYSTEM_PREFIX` constant exposes the prefix for callers.
    - `Sources\Repository::setEnabledByGroup()` rewritten to use `$wpdb->query()` + a `NOT LIKE 'total_mail_queue:%'` WHERE clause so a "Disable group" call against the system group leaves the system rows untouched.
    - **Log table** picks up the new status: badge label "Blocked by source" (red CSS class `tmq-status-blocked-by-source`), and the status filter dropdown now offers `Blocked by source` alongside Sent / Error / Alert.
    - The Sources tab notice copy refreshed to reflect that toggles are live now, and to call out the system-source rule.
    - Skipped for blocked rows in `MailInterceptor`: `Content-Type` / `From` backfill, third-party PHPMailer config snapshot, and attachment staging — none of those are needed since the message will never be sent.
- **15 new tests for S4** — 4 integration tests on `Sources\Repository` (`isSystem`, `setEnabled` skipping a system row, `setEnabled` proceeding for a non-system row, `setEnabledByGroup` SQL excludes the system prefix), 6 functional tests on the end-to-end flow (`SourcesEnforcementTest`: disabled source → `blocked_by_source` row, enabled source still queues, Instant header doesn't bypass the block, repository refuses to disable system source, group disable skips system sources, admin UI per-row toggle cannot disable a system source). `MockWpdb` gained an `esc_like()` helper.
- **Phase S3 — admin UI.** The "Sources" tab is the place admins go to inspect what their site sends and toggle delivery per-source. Toggle UI is live; the actual blocking still lands in S4 (until then, disabling a source previews the change but the queue keeps delivering).
    - New tab **"Sources"** (slug `wp_tmq_mail_queue-tab-sources`), wired in `Admin\Menu` and `Admin\PluginPage`. Routes to the new `Admin\Pages\SourcesPage` renderer.
    - `Admin\Tables\SourcesTable` (new `WP_List_Table` under `src/admin/tables/`) — columns: status badge / label / source_key (as code chip) / group / total emails / last seen ("X ago" with full timestamp on hover) / per-row Actions (Enable-or-Disable + "Filter log" shortcut).
    - `Admin\Pages\SourcesPage`:
      - **Per-row Enable/Disable** (GET link, nonce-protected per row).
      - **Bulk Enable / Disable** (WP_List_Table's standard dropdown, applied to selected ids).
      - **Per-group Enable / Disable** toolbar above the table (select a group → "Enable group" / "Disable group" button).
      - Inline notices summarise how many rows changed.
    - `Admin\Tables\LogTable` gains a new **"Source"** column (just after "Status") that shows the `source_key` as a clickable code chip — clicking adds `?source_filter=<key>` to the URL and the LogTable narrows to that source.
      - The status filter form preserves the active `source_filter` so combining a status + a source narrowing works.
      - When a source filter is active, a small badge + "Clear source filter" link appears next to the status dropdown.
    - 6 functional tests (`SourcesAdminTest`) cover: per-row toggle (happy path + nonce rejection), bulk POST (only selected ids change), group POST (only members of the named group change), LogTable source-filter narrows the visible rows, SourcesTable lists every row ordered by group then key.
- **Phase S2 — detection + MailInterceptor wiring.** Sources are now resolved and persisted on every queued message; the catalog populates itself organically. No UI yet (S3); no enforcement yet (S4).
    - `Sources\Detector` (new `src/sources/detector.php`):
      - **Primary listeners** wired on the WP-core filters that fire just before `wp_mail()` is called: `retrieve_password_notification_email` → `wp_core:password_reset`, `wp_new_user_notification_email` / `wp_new_user_notification_email_admin` → `wp_core:new_user(_admin)`, `password_change_email` → `wp_core:password_change`, `email_change_email` → `wp_core:email_change`, `auto_core_update_email` → `wp_core:auto_update`, `comment_notification_text` / `comment_moderation_text` → `wp_core:comment_(notification|moderation)`. Each listener is a passthrough that records the source and returns the filter argument unchanged.
      - **Fallback** `inferFromBacktrace()` walks `debug_backtrace()` (skipping our own files + `wp-includes/`) and maps the first interesting frame to a `plugin:<slug>` / `theme:<slug>` / `mu_plugin:<slug>` / `wp_core:admin` / `wp_core:unknown` source. Cross-platform path normalisation handles Windows backslashes too.
      - `setCurrent()` / `consume()` (single-shot read + clear) keep the marker per-process and prevent stale leaks across requests.
    - `Queue\MailInterceptor::handle()` consumes the source (or falls back to backtrace) at the top of each invocation, persists it on the queue row in the new `source_key` column, and calls `Sources\Repository::register()` + `markSeen()` so the catalog stays in sync without an explicit "register" step on the admin's part. Skipped entirely when an earlier `pre_wp_mail` filter already short-circuited the send.
    - `Cron\AlertSender::maybeAlert()` now stamps the alert email with `total_mail_queue:alert` (new `AlertSender::SOURCE_KEY` constant) and registers that source up-front. S4 will hardcode this key as un-toggleable so the admin cannot silence their own monitoring.
    - `Plugin::boot()` wires `Sources\Detector::register()` before the mail interceptor.
- **15 new tests** for the S2 path: 8 unit tests covering every branch of `Detector::classify()` (plugin / theme / mu-plugin (dir + single-file) / wp-admin / wp-includes skip / our own files skip / Windows path normalisation / unknown fallback); 4 integration tests covering MailInterceptor's source-capture (listener marker, consume-and-clear, no catalog write on failed insert, short-circuit bail); 3 functional tests covering the end-to-end pipeline (real `wp_mail()` → queue row carries `source_key` → catalog auto-registers → repeat sends bump `total_count` without duplicating the catalog row).
- **Phase S1 — schema + repository foundation.** Wiring code only; no detection, no UI, no enforcement yet.
    - **New table** `{$prefix}total_mail_queue_sources` (`id`, `source_key` UNIQUE, `label`, `group_label`, `enabled`, `detected_at`, `last_seen_at`, `total_count`). Created idempotently via `dbDelta()` on activation / version-bump.
    - **New column** `source_key VARCHAR(120)` on `{$prefix}total_mail_queue` plus a matching `KEY idx_source_key` so the upcoming "filter log by source" query is indexed.
    - `Database\Schema::sourcesTable()` accessor + `Schema::SOURCES_TABLE` constant. `Schema::install()` and `Schema::drop()` updated to cover the new table.
    - `Sources\Repository` — read/write helpers that the upcoming detector + admin UI will consume:
      - `findByKey()` / `findById()` — single-row lookup.
      - `register()` — insert-or-return-existing for auto-detected keys (UNIQUE index handles dedup).
      - `markSeen()` — bumps `total_count` + `last_seen_at`.
      - `isEnabled()` — central decision point; **defaults to true** for unknown keys so the upgrade is non-breaking (opt-out, not opt-in).
      - `setEnabled()` / `setEnabledByGroup()` — toggle one row or every row in a group.
      - `all()` / `count()` — listing helpers for the admin tab.
- **15 integration tests** for `Sources\Repository` covering every method (default-true behaviour, dedup on `register`, no-op guards on non-positive ids, ORDER BY contract on `all()`, etc.).

### Changed

- **Plugin version bumped to 2.4.0** (`total-mail-queue.php` header, `readme.txt` `Stable tag`, `Plugin::VERSION`). The bump triggers `Database\Migrator::maybeMigrate()` on next load, which calls `Schema::install()` and dbDelta picks up the new table + column.
- **Compact admin header.** The plugin's logo block on every tab page now uses a 4rem icon and 1rem/1.25rem vertical padding (was 6rem icon and 3rem/3rem padding). The wordmark image renders at 14rem instead of 18rem. Net effect: ~60px less vertical space between the WordPress admin bar and the tab nav.
- **Block-mode banner contrast.** The "All outgoing emails are being blocked!" banner (Settings tab when the plugin is in block mode) was previously emitted with both `.tmq-warning` (red background + white text) and `.tmq-warning-block` (red text), which made it red-on-red and unreadable. Dropped the second class from the banner.
- **Renamed `.tmq-warning-block` → `.tmq-text-danger`** so its name no longer rhymes with `.tmq-warning`. The two classes solve different problems (a red banner with white text vs. a short paragraph of red caution copy); having near-identical names invited the cascade conflict above. `.tmq-text-danger` keeps the same `color: #d63638;` rule and is used by the cron-lock-TTL caution copy in `Admin\SettingsApi::renderCronLockTtl()`.
- **`.tmq-warning` background** darkened from CSS-named `red` (#ff0000, 4.0:1 contrast vs white text) to `#b32d2e` (5.94:1) so the banner clears WCAG AA for normal text.
- **Inline `style=""` on the SMTP form decoy fields** moved into `.tmq-autofill-decoy` in `assets/css/admin.css`. The behaviour (off-screen browser-autofill absorbers) is identical.

### Removed

- **Dead CSS rules** in `assets/css/admin.css`: `.tmq-status-sent-from-queue` (no such status was ever emitted), `.tmq-status-has-info` + `.tmq-status-has-info::after` + `.tmq-status-has-info:hover .tmq-info` + `.tmq-info` (an info-tooltip pattern with no callers), `.column-id` (the WP_List_Table doesn't expose an `id` column).
- **Dead Container entry** `plugin.dir`: registered in `Plugin::boot()` but never read by any consumer. `plugin.file` (the only entry that has callers) stays.

---

## [2.3.0] - 2026-04-26

> A full namespaced rebuild of the plugin. Procedural functions and the `$wp_tmq_*` globals are gone; every responsibility now lives in a dedicated class under the `TotalMailQueue\` namespace, loaded by a small inline autoloader registered directly in `total-mail-queue.php` (and mirrored in `uninstall.php`). Hook wiring is centralised in `Plugin::boot()`. Source files live under `src/` with lowercase directories + kebab-case filenames (e.g. `src/admin/plugin-page.php`) for case-coherent Linux deployments. The on-disk schema, option keys, cron events, page slugs, nonces and AJAX action names are unchanged — existing installs upgrade in place.

### Added

- **PSR-4 autoload root `TotalMailQueue\` mapping to `src/`** plus the orchestration scaffolding: `Plugin` (singleton orchestrator with `VERSION` / `REQUIRES_PHP` / `REQUIRES_WP` constants and a `boot()` / `container()` API) and `Container` (minimal lazy service locator with `set` / `get` / `has`).
- **Pure utility classes** — `Support\Encryption` (AES-256-CBC password helper), `Support\Serializer` (JSON encode/decode with backwards-compatible `unserialize` fallback, `allowed_classes => false`), `Support\Paths` (single source of truth for the attachments directory and the legacy attachments path), `Support\HtmlPreview` (admin log/queue HTML rendering).
- **Database + lifecycle** — `Database\Schema` (authoritative table definitions with idempotent `install()` via `dbDelta()`, `drop()`, plus `queueTable()` / `smtpTable()` accessors), `Database\Migrator` (version detection on `plugins_loaded`), `Lifecycle\Activator`, `Lifecycle\Deactivator`, and `Lifecycle\Uninstaller` driven by the new `uninstall.php` (WP convention; `register_uninstall_hook` cannot serialize closures).
- **Settings** — `Settings\Options` (single source of truth for the `wp_tmq_settings` option; `defaults()` + `get()` apply the queue interval / clear-queue derivations every consumer expects) and `Settings\Sanitizer` (`register_setting` callback that whitelists admin-settable keys so a crafted POST cannot inject `tableName`, `smtpTableName`, or `triggercount`).
- **SMTP** — `Smtp\Repository` (CRUD-adjacent access to the SMTP accounts table: `resetCounters()`, `available()`, `pickAvailable()`, `bumpMemoryCounter()`, `incrementCounter()`, `findPasswordById()`), `Smtp\Configurator` (`apply($phpmailer, $smtp_account)` — reads the SMTP timeout from `Options::get()`), `Smtp\PhpMailerCapturer` (returns the configuration third-party `phpmailer_init` listeners apply, with the password encrypted before storage; the `$wp_tmq_capturing_phpmailer` global moved to a typed static property `PhpMailerCapturer::$capturing`), `Smtp\ConnectionTester` (admin AJAX "Test Connection" handler).
- **Queue** — `Queue\QueueRepository` (every read/write against `{$prefix}total_mail_queue`), `Queue\AttachmentStore` (per-email staging area under `wp-content/uploads/tmq-attachments/`, released after the send), `Queue\Tracker` (replaces the legacy `$wp_tmq_mailid` global with `set()` / `get()` / `reset()`), `Queue\MailInterceptor` (`pre_wp_mail` filter — recognises `X-Mail-Queue-Prio: Instant|High`, backfills `Content-Type` / `From` from `wp_mail_*` filters, snapshots third-party `phpmailer_init` config), `Queue\MailFailedHandler` (`wp_mail_failed` with auto-retry up to `max_retries`), `Queue\MailSucceededHandler` (`wp_mail_succeeded` flips `instant` rows to `sent`).
- **Cron** — `Cron\Scheduler` (registers the `wp_tmq_interval` schedule + (un)schedules the queue event based on `enabled`), `Cron\BatchProcessor` (the cron worker; `run()` + `sendOne()` per row + `reset()` for tests), `Cron\CronLock` (MySQL `GET_LOCK` / `RELEASE_LOCK` with shutdown-time failsafe release), `Cron\Diagnostics` (accumulator for the `wp_tmq_last_cron` option), `Cron\AlertSender` (queue-overflow alert email with the 6-hour throttle).
- **Retention** — `Retention\LogPruner` with `pruneByAge()` (for `clear_queue`) + `pruneByCount()` (for `log_max_records`), run at the end of every batch.
- **Admin UI** — `Admin\TextDomain`, `Admin\PluginRowLinks`, `Admin\Menu`, `Admin\Assets` (admin CSS/JS + the inline `window.tmq` config blob with `restNonce` / `testSmtpNonce` / i18n strings), `Admin\SettingsApi` (`register_setting` + every field renderer: status, alert status, queue, log, send method, retry, smtp timeout, cron lock TTL, sensitivity), `Admin\Notices` (last-error notice + warning when known wp_mail-bypassing plugins like MailPoet are active), `Admin\ExportImport` (XML export/import with `LIBXML_NONET` for XXE protection and a settings-key whitelist on import), `Admin\PluginPage` (the single page callback dispatched by every submenu — routes by `page` slug to the per-tab renderers, runs the Settings API, surfaces the mode/cron/conflict notices, handles the `addtestmail` queue insert), `Admin\FaqRenderer` (long-form static FAQ split into focused private renderers), `Admin\Pages\SmtpPage` (full SMTP CRUD: list, add, edit with the connection-lock toggle that protects host + credential fields, delete, reset counters), `Admin\Tables\LogTable` (`WP_List_Table` subclass powering the Log + Retention tabs: status filter, bulk delete/resend/force-resend, lazy-loaded message preview, SMTP-account name caching).
- **REST** — `Rest\MessageController` registers `GET /tmq/v1/message/{id}` for the lazy-loaded message preview, gated on `manage_options`.
- **Automated test suite (PHPUnit 9.6).** 86 tests / 213 assertions across three suites — 25 unit tests (pure-PHP helpers without booting WP: Encryption AES-256-CBC round-trip + tamper detection, Serializer JSON+unserialize fallback, SMTP picker, in-memory counter increment, base64 redaction in HTML preview), 27 integration tests (Brain Monkey + Mockery + an in-memory `MockWpdb` test double covering Options defaults + interval conversion + 10s minimum clamp; Sanitizer whitelist; MailInterceptor priority handling, block mode, content-type backfill; PhpMailerCapturer encrypted-password capture; MailFailedHandler retry counter + error finalisation), 34 functional tests against a real WordPress + MySQL via `wp-phpunit/wp-phpunit` (activation + dbDelta schema/indexes; retention cleanup by age + count cap; REST endpoint capability + 404; bulk actions delete/resend/force_resend; full cron flow with priority ordering, batch limit, block/disabled modes, smtp-only fallback, diagnostics; AJAX `wp_tmq_test_smtp` permission/nonce/validation/connection failure paths; XML export/import round-trip with whitelist enforcement and XXE protection).
- **Static analysis** with PHPStan (level 5) using `szepeviktor/phpstan-wordpress`.
- **Coding standards** with PHP_CodeSniffer + WordPress Coding Standards 3.x (full ruleset, with PSR-4-compatible scoped exclusions for `src/*`).
- **PHP compatibility checks** via `phpcompatibility/phpcompatibility-wp` against the declared minimum PHP 7.4.
- **GitHub Actions workflow** — PHPUnit on PHP 8.1 / 8.2 / 8.3, functional tests against MySQL 8 on PHP 8.1 + 8.3, PHPStan, PHPCS — runs on every push and pull request.
- **`bin/install-wp-tests.sh`** helper that bootstraps the WordPress test database for local development.
- **`CHANGELOG.md`** following the Keep a Changelog format.

### Changed

- **Plugin version bumped to 2.3.0** (header in `total-mail-queue.php`, `Stable tag` in `readme.txt`, `Plugin::VERSION`).
- **Bootstrap** (`total-mail-queue.php`) shrunk to ~30 lines: `ABSPATH` guard → inline `spl_autoload_register` for the `TotalMailQueue\` namespace → `Plugin::boot(__FILE__)`. The Composer-generated `vendor/autoload.php` is no longer required at runtime — the plugin has no third-party runtime dependencies, so a small inline autoloader handles the namespace lookup. Dev tooling (PHPUnit, PHPCS, PHPStan, Brain Monkey) still uses `vendor/` from the test bootstraps.
- **Hook wiring** centralised in `Plugin::boot()`: lifecycle hooks, the `pre_wp_mail` / `wp_mail_failed` / `wp_mail_succeeded` chain, the cron schedule + worker, the AJAX endpoint, the admin UI scaffolding, and the REST controller.

### Removed

- **The two procedural admin files** (`total-mail-queue-options.php` and `total-mail-queue-smtp.php`, ~1900 lines combined) and the `is_admin()` includes that loaded them.
- **All 30+ procedural `wp_tmq_*` functions** that lived in those files plus the bootstrap (encrypt/decrypt password, encode/decode, attachments_dir, render_list_message / render_html_for_display; activate / deactivate / uninstall / updateDatabaseTables / check_update_db; get_settings / sanitize_settings; prewpmail / mail_failed / mail_succeeded / search_mail_from_queue / cron_interval; reset_smtp_counters / get_available_smtp / pick_available_smtp / update_memory_counter / increment_smtp_counter / configure_phpmailer / capture_phpmailer_config; the `wp_tmq_ajax_test_smtp_connection` AJAX handler; load_textdomain, actionlinks, settings_page_assets / _inline_script / settings_init / every render_option_*, checkLogForErrors, maybe_handle_export / handle_export / build_export_xml / handle_import, add_rest_endpoints / rest_get_message, settings_page / settings_page_navi / settings_page_menuitem, render_smtp_page) and their `add_action` / `add_filter` / `register_*_hook` registrations.
- **The legacy globals** — `$wp_tmq_options`, `$wp_tmq_version`, `$wp_tmq_mailid`, `$wp_tmq_pre_wp_mail_priority`, `$wp_tmq_next_cron_timestamp`, `$wp_tmq_capturing_phpmailer`.
- **The `wp_tmq_Log_Table` class** (replaced by `Admin\Tables\LogTable`).
- **The PHPStan baseline** — empty after the file deletions.

### Security

- **Settings import whitelist (`Admin\ExportImport`).** A crafted XML payload cannot rewrite `tableName` / `smtpTableName` to redirect SQL queries to other tables.
- **XXE protection on import.** `simplexml_load_string` is called with `LIBXML_NONET`, blocking external-entity resolution.
- **Settings sanitiser whitelist (`Settings\Sanitizer`).** Same whitelist enforced server-side on every `wp_tmq_settings` write, so a forged form POST cannot inject the protected option keys.
- **REST + AJAX gating.** `manage_options` is verified at the permission callback for `GET /tmq/v1/message/{id}` and at the AJAX entry point for `wp_tmq_test_smtp`.
- **SMTP password storage.** Passwords are encrypted with AES-256-CBC + a per-record IV before being written to `{$prefix}total_mail_queue_smtp.password` (legacy passwords are re-encrypted lazily on next save).

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

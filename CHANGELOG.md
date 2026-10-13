# Changelog

All notable changes to the **Total Mail Queue** plugin are documented in this file.
The format follows [Keep a Changelog] (https://keepachangelog.com/en/1.1.0/).

---

## [Unreleased]

### Added

- **The plugin has an icon on Dashboard → Updates and in the plugin list** (#142). Its own mark, the ring, the layered envelope and the green paper plane, now traced as a vector from the original artwork, replaces WordPress's grey placeholder, read from the installed copy. `assets/img/brand/` also ships black, white, dark-screen and no-background variants, each as SVG and as 128 px and 256 px PNG.
- **"View details" shows a banner and the readme's tabs** (#145). Description, Installation, FAQ, Screenshots and Other notes come from the offered release's `readme.txt`, and the changelog renders its Markdown instead of printing it raw. The Documentation tab shows the same banner.
- **"View details" speaks Portuguese and opens screenshots in place** (#147). The tabs and banner follow the viewer's language when the plugin ships a translation (`languages/readme-pt_BR.txt`), and a screenshot opens in an overlay with arrows, swipe and zoom instead of a new tab.


### Changed

- **Translations compile with one command, and CI checks the three files against each other** (#150). `php bin/compile-translations.php` writes the `.mo` and the `.l10n.php` from the `.po`, and a test fails when they disagree in either direction. A readme change now carries its pt_BR copy in the same PR (#149).
- **Two tests no longer fail when the clock crosses midnight during the run** (#151). The daily-cap tests read the next local midnight before and after the call, as the scheduler test already did; the audit of every clock-reading test is in the issue.
- **The translation reader is linted, and the functional suite runs locally** (#153). `bin/po-catalogue.php` now runs through PHPStan and PHPCS, and `CLAUDE.md` documents running the functional suite against the container's MariaDB.
- **CSS lives in files and ships minified** (#157). The icon and menu rules leave `wp_add_inline_style()` for `admin.css` and a new `admin-chrome.css`, generated from their PHP source and held to it by a test. `npm run build` writes `.min` CSS and JS, loaded unless `SCRIPT_DEBUG` is on, and CI fails when one is stale.

### Fixed

- **Dark mode reaches the Access role names, the colour picker and notice links** (#155). Each was painted by core on its own and measured under 2:1 on the dark card.


## [3.0.0] (2026-10-12) — `b5cec9c`

A major version for two changes to what the plugin asks of a site, not to what it does: it now requires WordPress 6.8 and PHP 8.3, and the licence moves to AGPLv3 or later. The release process also catches up with the other three plugins.

### Changed

- **⚠ Requires WordPress 6.8 and PHP 8.3** (#129). The floor moves from WordPress 5.9 and PHP 8.1, which CI never ran, to the one Free Form Certificate, Email Signature Generator and Institutional Email share; sites below it keep their installed version. CI runs the functional suite on the floor, and the coverage floors rise to 90% (PHP) and 94% (JS).
- ⚠ **The licence is now AGPLv3 or later** (#134). Reuse and forks stay free; a modified copy offered as a network service must publish its source too. The readme prose inherited from Mail Queue is rewritten, html2text stays MIT, and releases already published stay GPLv3.
- **Dependabot's patch and minor updates merge on their own** (#131). Required checks still gate the merge; majors stay manual.

### Security

- **CI jobs run with a read-only token** (#137). `lint.yml` and `tests.yml` declare `contents: read`.

## [2.12.0] (2026-10-12) — `89bef95`

A Cron tab that says whether the queue is being sent and gives the server cron line, Free Form Certificate's icons, colours and documentation navigation, empty number fields that keep their value, and authenticated SMTP passwords.

### Added

- **Coverage floor and three guards** (#113). CI now fails below a merged line-coverage floor, and the unit suite checks that the version and the PHP and WordPress floors agree across every file that declares them, that the Upgrade Notice describes the release on offer, and that every suppression names its rule and a reason.
- **JavaScript test suite** (#115). Vitest with jsdom now covers the four admin scripts (queue list, autosave, documentation search, templates preview and test email), and CI fails below a JS line-coverage floor.
- **Functional tests for the admin screens** (#115). The SMTP, Sources edit, Settings, notices, queue diagnostics, Access, assets and page-router screens, and the test-email handler, are now exercised with their nonce, capability and error paths; the coverage floor rises to 83%.
- **Tests for the worker, scheduler, boot and uninstall** (#115). The batch processor's lock, failure and transport branches, the schedule against the real cron table, SMTP recovery times, plugin boot and the uninstaller are now covered; the coverage floor rises to 88%.
- **Cron tab** (#121). Always shown, it says whether the queue worker is running, asleep, waiting for an SMTP quota, overdue or stalled, with its next and last run; whether WP-Cron's visit trigger is on and which events are overdue; and, for administrators, the crontab line to add (WP-CLI, wget or curl) with a copy button. It replaces the Cron Information tab that appeared only with `DISABLE_WP_CRON`.

### Changed

- **Documentation navigation beside the topics** (#120). On a wide screen the quick navigation is now a fixed column next to the text instead of a bar that followed the scroll over it; on a narrow screen it sits above the topics.
- **The documentation's navigation reads as in Free Form Certificate** (#122). Its title is now Quick Navigation (pt_BR "Navegação Rápida") and the search box says "Search documentation…" ("Pesquisar na documentação…"), the wording of Free Form Certificate and the other plugins.
- **Icons and colours follow Free Form Certificate** (#123). Each icon with a counterpart there now uses its drawing — Settings is the gear and Advanced the sliders, the reverse of before — and the colour tokens take its values, among them the darker field border (3:1 against white) and its status text colours.

### Fixed

- **An empty number field no longer saves as 0** (#112). Clearing the log retention deleted every log row on the next run, and clearing the batch size stopped sending. An empty field now keeps its value, each number has a minimum, and values already stored empty read as the default.
- **Import restores every setting the form saves** (#112). Six keys the export writes, among them the SMTP timeout, the default sender and dark mode, were dropped on import; the import now goes through the form's own sanitizer.

### Security

- **SMTP passwords are authenticated, not only encrypted** (#114). A stored password now carries an HMAC over its ciphertext, checked before decryption, with the cipher and MAC keys derived separately from the salt. Existing passwords are re-encrypted on update and after an import; the old format is still read.

## [2.11.0] (2026-10-10) — `88add1d`

### Added

- **Live preview on the Templates tab** (#104). A sample email with the settings on screen, header and footer included, updates beside the form as you edit, before saving. It is drawn by the same code that wraps real mail, in a sandboxed frame; nothing is stored or sent.
- **README with screenshots** (#107). The repository now presents the plugin on GitHub, one section per feature with screenshots taken from a throwaway site with invented data; `.github/scripts/screenshots/` holds the two scripts that rebuild them. Nothing in the plugin changes.

## [2.10.0] (2026-10-10) — `d3c5898`

### Added

- **Updates from GitHub Releases** (#66). WordPress offers new versions on its normal update screen, checks the package's SHA-256 before installing it, and reads the compatibility fields and upgrade notice from the release's own `readme.txt`. Ported from Free Form Certificate.
- **Choose which roles view and manage the plugin** (#91). Settings → Access, for administrators only, grants a role View (read-only screens) or Manage (settings, sources, templates, bulk actions, message bodies). SMTP accounts, Export / Import and the bodies of one-time account-link emails stay administrator-only.
- **Search and date range on the Log and Retention lists** (#83). Search matches recipient and subject, the dates select a range of days, and both combine with the status and source filters. The filters live in the page address, so pagination and bulk actions keep them.
- **Block on alert** (#84). An opt-in setting under Alerts switches the Operation Mode to Block when the queue alert fires, so a sudden flood stops leaving the site until someone has looked. The alert email says so, and an admin notice stays until the mode changes.
- **Password resets go out in Block mode** (#84). Block holds every other email, but a held password reset locks its user out, so it is sent at once unless the new setting is turned off. A disabled Password reset source still stops it.
- **On/off settings are toggle switches that save on flip** (#89). Alerts, Block on alert, Password resets, SMTP Debug Log and the Templates switches save the moment they change, with a Saved badge; the SMTP account and source forms use the same switches and keep their Save button.
- **Dark mode** (#68). Off, On or Follow the system, restyling only the plugin's screens. The submenus become a vertical tab column that keeps every URL, and the menu icon follows the admin colour scheme.
- **A Documentation tab** (#71, #77, #78). The plugin ships its own guide, grouped by tab in menu order under a searchable navigation tree, with Troubleshooting in place of the FAQ and a "Back to top" button on every screen.
- **Development workflow** (#64, #74, #75, #94). CI on PHP 8.1–8.4 with ESLint, Stylelint and Composer checks; `develop` deploys to the testes site behind a smoke check; a `v*` tag publishes the release zip, and older tags can be published by hand without becoming the latest.

### Changed

- **Minimum PHP is now 8.1** (#64), was 7.4. Composer already required it, and 7.4 and 8.0 are out of security support.
- **Admin screens laid out as cards with icon headings** (#68, #76). Settings is grouped into Sending, Log, Alerts, Advanced and Appearance, Log and Retention columns can be hidden from Screen Options, and every colour comes from a token checked against WCAG AA.
- **The changelog follows Free Form Certificate's format** (#80, #79, #93). Every version carries its date and the commit its tag sits on, lists what that commit shipped, and the pre-fork history credits the original plugin and its developer.

### Fixed

- **A retried email keeps its attachments** (#81). Files staged for a failed send were deleted before the retry; they now go only when the email is sent, fails for good or is deleted. Resend and Force Resend keep the source and its priority.
- **Attachment staging is all or nothing** (#81). A failed copy no longer queues a broken attachment, same-named files no longer overwrite each other, blocked emails stage nothing, and only folders inside the staging directory are ever deleted.
- **Emails to long recipient lists are stored whole** (#82). The recipient column held 255 characters, so a longer list was cut off and the email reached only part of it, or failed. The column is now `text`, widened in place on upgrade.
- **Deleting the plugin removes everything it wrote** (#71, #84, #91). The template settings, the WordPress-version marker, the auto-block marker and the role capabilities are removed too, and a test checks every option the plugin writes.

## [2.9.0] (2026-07-25) — `bee76b0`

### Added

- **Per-source preferred SMTP account** (#60). A source can be pinned to one SMTP account; a send uses it while the account is enabled and under quota, and otherwise falls back to round-robin, so a preference never blocks or delays a send.
- **Plugins can label each of their emails as a distinct source** (#62, #63). An `X-TMQ-Source-Key` header, with an optional `X-TMQ-Source-Label`, overrides detection for `plugin:`, `mu_plugin:` and `theme:` keys. Both headers are stripped before the message is stored or sent.

## [2.8.1] (2026-06-07) — `cb6c357`

### Fixed

- **The "queue may not be processing" notice no longer cries wolf** (#59). It now appears only when mail is waiting, nothing has been sent for over two hours and no worker run is due, instead of keying off the last worker run with a 15-minute threshold.

## [2.8.0] (2026-06-07) — `606fa84`

### Added

- **Per-email error history, with an optional SMTP debug log** (#58). Each failed attempt is kept in full on the queue row and shown as "Error details" in the Log, cleared when the email later sends. The SMTP Debug Log setting also records the whole SMTP conversation.
- **The waiting row says why the queue waits** (#58). When every SMTP account is capped, it names the limit holding each account (per-cycle, daily or monthly) and roughly when it recovers.
- **"Queue may not be processing" notice** (#57). Warns administrators when mail is waiting and the worker has not run for a while, the usual sign of WP-Cron not firing on a cached or quiet site.

### Changed

- **All database access goes through the repositories** (#56). The admin tabs, the REST controller and the notices no longer query the tables themselves; only the schema, the repositories, the retention sweep and the cron lock touch `$wpdb`. No behaviour change.

### Fixed

- **Per-cycle SMTP quotas no longer get stuck** (#58). The cycle counter rolled over from the last send, so an account sending steadily never reset; it is now anchored to the cycle's first send.
- **SMTP passwords no longer fail to decrypt intermittently** (#57). A random IV containing `::` broke about 0.4% of stored passwords; decoding now splits by the fixed IV length, and existing values decode unchanged.
- **A hung send no longer freezes the queue** (#57). The cron lock is now a timestamped option with a real TTL, kept alive while a batch progresses, instead of a MySQL `GET_LOCK` that a hung process held forever.

## [2.7.0] (2026-06-07) — `58bedbf`

### Added

- **Per-source send priority** (#55). Each source gets a numeric priority, `1` most urgent and `50` normal, shown in the Sources list and as a badge on pending Log rows.
- **Last-sent time and cycle usage per SMTP account** (#55), beside the daily and monthly counters.

### Changed

- **SMTP accounts are picked round-robin** (#55). Every enabled account shares the load instead of the batch draining through the top-priority one; daily and monthly caps are enforced within a batch.
- **The worker runs on demand** (#55). It sleeps on an empty queue, wakes when mail is queued, and defers to the next quota window when every account is capped.
- **One numeric priority scale** (#55). The `X-Mail-Queue-Prio: High` header and the per-source priority feed the same new `priority` column; legacy `high` rows are migrated on upgrade.
- **Saving an SMTP account or the settings takes effect on the next request** (#55), with no need to toggle the operation mode.

## [2.6.2] (2026-04-29) — `8c88eed`

### Changed

- **The postman illustration moved to a banner on the FAQ tab** (#37–#54), and the dark code blocks set the FAQ's PHP samples apart from the text.
- **The plugin's images are PNG and GIF instead of SVG** (#51).
- **Admin pages split into router, actions and renderers** (#37–#54). `SmtpPage`, `SourcesPage` and `PluginPage` shrank to routers; per-request static state resets through one `RuntimeState::reset()`.
- **PHPStan raised from level 5 to level 8, and from 1.12 to 2.1** (#37–#54), with the typing gaps it surfaced fixed.

### Fixed

- **Malformed email addresses are rejected in Settings** (#37–#54). The alert recipient and the default sender now go through `sanitize_email()`, so they can no longer ship as a broken `From:`.
- **The FAQ describes what the plugin actually changes** (#37–#54). "Does this plugin change how emails are sent?" lists the opt-in features that do, and the caching-plugin answer points to the plugin's own cron setting first.

## [2.6.1] (2026-04-28) — `d49a91a`

### Fixed

- **"Skip template wrapper" now skips it end to end** (#36). The engine re-wrapped queued rows when the queue drained, so a source with the box ticked still received the styled email.

## [2.6.0] (2026-04-28) — `243aaea`

### Added

- **Subject and body overrides for 11 WordPress core emails** (#35). From the Sources tab, with tokens, a preview send, "Reset to WP default" and a per-source "Skip template wrapper". Four core emails composed from many `sprintf` calls stay unmodified.
- **`{subject}` and `{message_original}` tokens** (#35), so an override can wrap or quote the original WordPress text.
- **A notice after a WordPress upgrade** (#35), pointing to the Sources tab to re-check the overridden emails against the new version.

### Changed

- **The default sender moved to Settings** (#35). From email and name left the Templates tab, migrated once on upgrade, with the precedence documented: SMTP account, then default sender, then WordPress.

## [2.5.2] (2026-04-27) — `18749dd`

### Fixed

- **Emails sent from other plugins' cron events are queued** (#34). They bypassed the queue, and in Block mode they went out; the interceptor now skips only while the plugin's own worker is draining.
- **The worker is exception-safe** (#34). The draining flag and the cron lock are released in a `finally`, so a fatal mid-batch no longer silences later intercepts.

## [2.5.1] (2026-04-27) — `b7c9fc6`

### Fixed

- **Fatal on load from an autoloader miss** (#33). `WooCommerceTokens` lived in a file the inline autoloader could not resolve; renamed, and a test now walks `src/` to check every class is reachable.
- **`_load_textdomain_just_in_time` notice on WordPress 6.7+** (#33). The text domain loads on `plugins_loaded` priority 0.

## [2.5.0] (2026-04-27) — `d20e1bd`

### Added

- **HTML template engine** (#28–#32). Every outgoing email can be wrapped in a configurable envelope; full-HTML messages pass through untouched. Ported from the GPL Email Templates plugin, without its Customizer UI, Google Fonts or third-party integrations.
- **Templates tab** (#28–#32). Header, body, footer and wrapper colours, fonts, logo and footer text, with Save, Reset and "Send test email".
- **Token registry with WooCommerce order tokens** (#28–#32). Site, recipient and date tokens plus order number, customer name, email, total and date, extensible through `wp_tmq_template_tokens`.
- **Template hooks** (#28–#32): `wp_tmq_template_skip`, `_content`, `_html`, `_tokens`, `_default_options`, `_options`, and the `wp_tmq_template_before_apply` / `_after_apply` actions.

## [2.4.1] (2026-04-26) — `30b87da`

### Added

- **Translatable source labels** (#27), picked up by Loco Translate and `.po` tools.
- **Per-source label and group overrides** (#27), edited inline and marked "(custom)", with a reset.
- **Filter the Sources list by group** (#27).
- **A warning on the Sources tab in Block or Disabled mode** (#27), where per-source toggles have no effect.

## [2.4.0] (2026-04-26) — `868509c`

### Added

- **Control of emails by source** (#19–#26). Every queued email is tagged with a source (`wp_core:password_reset`, `plugin:woocommerce`…), detected from WordPress filters or the call stack, and the new Sources tab enables or disables each source, a group or a selection.
- **Disabled sources are blocked** (#19–#26). Their emails are stored as "Blocked by source" and never sent, even with the Instant header; the plugin's own alert source cannot be disabled.
- **A pre-filled source catalogue** (#19–#26). Every email a stock WordPress sends, plus WooCommerce, Contact Form 7 and WPForms, appears on install, so a source can be switched off before it first fires.
- **Filter the Log by source** (#19–#26), from a Source column.

### Changed

- **A more compact admin header and a readable Block-mode banner** (#19–#26). The banner was red on red, and its red now meets WCAG AA.

### Removed

- **Dead CSS rules and an unused container entry** (#19–#26).

## [2.3.0] (2026-04-26) — `9766005`

### Added

- **Automated tests and static analysis** (#8–#18). PHPUnit unit, integration and functional suites against a real WordPress, PHPStan, PHPCS with WordPress Coding Standards, PHP compatibility checks and a GitHub Actions workflow.

### Changed

- **A full namespaced rebuild** (#8–#18). Every responsibility lives in a class under `TotalMailQueue\`, loaded by an inline autoloader and wired in `Plugin::boot()`. The schema, option keys, cron events, page slugs, nonces and AJAX actions are unchanged, so installs upgrade in place.

### Removed

- **The procedural admin files, the `wp_tmq_*` functions and the `$wp_tmq_*` globals** (#8–#18), replaced by the classes above.

### Fixed

- **No early text-domain notice on WordPress 6.7+** (#18). The cron schedule label is no longer translated, because WordPress asks for it before `init`.

### Security

- **Hardened settings, import and endpoints** (#8–#18). Settings saves and XML imports go through a key whitelist, imports block external entities, the REST and AJAX endpoints require `manage_options`, and SMTP passwords are stored with AES-256-CBC and a per-record IV.

## [2.2.1] (2026-02-01) — `1c1b4d2`

### Added

- **Configurable SMTP and cron-lock timeouts.**
- **A cross-process cron lock** (MySQL `GET_LOCK`), so batches cannot overlap.

### Changed

- **WordPress Plugin Check compliance**: output escaping, `ABSPATH` guards and safe redirects.
- **Lower memory use**: email bodies load one at a time instead of all at once.
- **The attachment directory is protected through the WP_Filesystem API.**

### Removed

- **Development `error_log` / `print_r` calls**, and the `%i` placeholder for WordPress before 6.2.

### Fixed

- **SMTP accounts are no longer blocked mid-cycle** by the `send_interval` check.

## [2.2.0] (2026-01-31) — `b182aae`

### Added

- **Test Connection for SMTP accounts**, before any email is sent.
- **Export and import** of settings and SMTP accounts as XML.
- **A Send Method setting**: Automatic, plugin SMTP only, or WordPress default.
- **Log improvements**: a status filter, an Info column with retries and errors, and a resend that replaces the original entry.
- **Cron diagnostics** on the Retention tab, and detection of conflicting `pre_wp_mail` filters.
- **Per-account send interval and per-cycle limit**, capped by the global queue limit, with a warning when an account's limit exceeds it.
- **An SMTP column in the Log** naming the account that sent each email, and account IDs in the SMTP list.
- **Connection fields locked when editing an SMTP account**, behind an unlock switch, so limits can change without touching credentials.

### Changed

- **JSON storage instead of `serialize()`**, still reading legacy data.
- **Paginated queries and one SMTP counter reset per run** instead of loading every row and resetting per email.
- **Stricter code**: strict comparisons, consistent `wpdb` formats, styles moved to `admin.css`, cache-busted assets.
- **Resend and Force Resend refuse emails already sent or still queued**, with a notice instead of skipping them silently.
- **SMTP accounts are picked from in-memory counters**, not one query per email.

### Fixed

- **Envelope sender set with the From header**, and instant emails record their result.
- **Local time in SQL** instead of `NOW()`.
- **Attachments stored under `uploads/tmq-attachments/`** behind `.htaccess`, in random subfolders.
- **SMTP authentication no longer fails from the second email of a batch**: the connection is closed before each send.
- **A per-account cycle counter survives cron runs shorter than its send interval**, so the per-cycle limit holds.
- **The worker no longer re-adds its `pre_wp_mail` filter mid-batch**, which could queue an email again instead of sending it.

### Security

- **Nonces on every bulk action and on the test email**, XXE prevention and a key whitelist on import, encrypted captured SMTP passwords, and no browser autofill on the SMTP username.

## [2.1.0] (2026-01-31) — `0238266`

### Added

- **A log size limit**: keep at most N entries, beside the age-based cleanup.
- **Automatic retry**: a failed email returns to the queue up to `max_retries` times (default 3).
- **Force Resend** for failed emails, ignoring the retry limit.

## [2.0.0] (2026-01-31) — `dd11612`

### Added

- **Operation modes**: Disabled, Queue, and Block, which keeps every email without sending it.
- **SMTP accounts** with priority, daily and monthly limits, and fallback to the next account when a limit is reached; passwords encrypted with AES-256-CBC.
- **The Retention tab**, renamed from Queue, listing only the emails still waiting.
- **Capture and replay of other plugins' `phpmailer_init` settings.**

## [1.5.0] (2026-01-30) — `0fd2805`

### Added

- **Fork of [Mail Queue](https://wordpress.org/plugins/mail-queue/) by [WDM](https://www.webdesign-muenchen.de)**, renamed **Total Mail Queue**, with every prefix and identifier changed.

### Security

- **Every database query goes through `$wpdb->prepare()`**, and every string is translatable.

**The versions below are releases of the original plugin, [Mail Queue](https://wordpress.org/plugins/mail-queue/), developed by [WDM](https://www.webdesign-muenchen.de).** Their entries are reproduced from its changelog, under its GPLv3-or-later licence. They predate the fork, are not part of this repository's history and have no tags here.

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

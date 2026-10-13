# CLAUDE.md

Project conventions for Claude (Anthropic CLI / agent sessions) working on this repository. Sessions inherit nothing across cold starts, so the durable rules live here. The workflow mirrors `rpgmem/ffcertificate`'s; where this file is silent, that repository's `CLAUDE.md` is the reference.

## Language

Every versioned artifact is in **English**: code, comments, docblocks, i18n source strings (translations live in `languages/`), `CHANGELOG.md`, commit messages, issue and PR titles and bodies. The conversation with the maintainer is in Brazilian Portuguese, and that does not decide the language of anything committed — a PR body is composed in the flow of the conversation, so the discipline belongs at the moment of writing it.

## Branches and pull requests

- **`main`** is production. It changes only through the release PR (`develop → main`) and through `hotfix/*` PRs.
- **`develop`** is the integration branch and the default base for every feature PR.
- Feature branches are `claude/<short-kebab-description>`, cut from `develop`. Always go through a PR, so the commit goes through CI.
- Open PRs as **draft**, flip to ready, then enable **auto-merge (SQUASH)**. When a PR completes a pre-agreed unit, flip it straight to ready. When its scope emerged mid-session, confirm with the maintainer first.
- When `develop` moves under a PR, the PR is `behind`. Update the branch; don't wait it out.
- `Closes #NNN` does not auto-close an issue when the PR merges into `develop`, because the default branch is `main`. Close the issue by hand when the work lands, and tick its checkboxes or map each one to the PR that shipped it.
- An agent's credential can create and update refs but **cannot delete a ref** (HTTP 403). Deleting a branch that survives the merge is the maintainer's job (see "Issues, pull requests and the merge queue").
- Dependabot targets `develop`. A security update opens against `main`: retarget it to `develop`.
- `dependabot-automerge.yml` arms squash auto-merge on Dependabot's patch and minor updates, the same as Free Form Certificate; the required checks still gate the merge. Majors stay manual, because a major can change behaviour the checks do not see. A merge made with the workflow's token does not trigger other workflows, so `deploy-develop.yml` skips it, which only matters for testes and only until the next merge.

## Issues, pull requests and the merge queue

The same standard as Free Form Certificate (its `CLAUDE.md` → "Issues, pull requests and the merge queue"). The layout lives in the templates — `.github/ISSUE_TEMPLATE/` (bug, feature, task) and `.github/PULL_REQUEST_TEMPLATE.md` (plus `PULL_REQUEST_TEMPLATE/release.md`, reached with `?template=release.md`); the rules around them are here.

- **An issue before the PR.** Every PR names the issue it closes. Exempt, and only these: a trivial docs or chore change, a Dependabot PR, the `release: X.Y.Z` bump and the CHANGELOG SHA backfill. An agent opens the issue itself, in English, from the task template.
- **Titles.** Issue: `Area: the problem in one sentence`. PR: `type(scope): summary`, `type` one of `feat`, `fix`, `docs`, `test`, `refactor`, `chore` — it becomes the squash subject on `develop`. Release titles keep their own shape.
- **Labels: one type, one area.** Type is `bug`, `enhancement`, `task`, `documentation` or `security`; area is an `area:` label — `area:queue`, `area:smtp`, `area:cron`, `area:admin` or `area:infra`. `duplicate`, `invalid` and `wontfix` say why something closed unmerged, and `dependencies` and the ecosystem labels belong to Dependabot, whose config applies them. An agent cannot create a label, only apply one that exists.
- **A PR carries the labels of the issue it closes.** The agent's tool opens a PR with no labels and cannot set them at creation, so the agent applies them as the next step, before marking it ready. Every issue and every PR carries its type and its area; a PR that only names its issue in the body is unlabelled until someone labels it, which is how the alignment PRs of 2026-10-12 went out bare in three repositories.
- **One merge at a time.** Only one PR is ready with auto-merge armed; the others wait as drafts. After a merge the next PR takes `develop` by a merge, never a rebase; `CHANGELOG.md` conflicts keep both bullets, translation catalogues are recompiled rather than hand-edited, the touched tests run again, and only then is it flipped to ready and armed.
- **After each merge** the agent closes the issue by hand, ticking or naming each acceptance criterion.
- **Branches.** Settings → General → "Automatically delete head branches" is on, so GitHub deletes a merged PR's branch. Whatever survives, the agent lists at the end of the batch for the maintainer to delete; local worktrees the agent removes itself.

## Versioning and CHANGELOG

- The version lives in three places, all updated in the same commit:
  - the `Version:` header in `total-mail-queue.php`;
  - `Plugin::VERSION` in `src/plugin.php`;
  - `Stable tag` in `readme.txt`.
- The PHP floor lives in four places, also kept in step:
  - the `Requires PHP:` header;
  - `readme.txt`;
  - `Plugin::REQUIRES_PHP`;
  - the PHPCompatibility `testVersion` in `phpcs.xml.dist`.

  `composer.json` declares the same floor.
- Feature PRs never bump the version. Their CHANGELOG entry goes under `## [Unreleased]`, grouped by `Added` / `Changed` / `Fixed` / `Removed` / `Security` / `Deprecated` and no other heading (tests, refactors, schema and hooks fold into these). Append the bullet at the end of its heading.
- **The CHANGELOG follows Free Form Certificate's format.** A bullet is a bold lead sentence, the PR or issue reference, then one or two short sentences — aim for 300 characters; the PR holds the detail. No version-bump bullets, no blockquote intros, no `---` between versions, no link-reference list. A released heading reads `` ## [X.Y.Z] (YYYY-MM-DD) — `<short-sha>` ``, the sha being the release commit on `main`.

## Releases

1. On `develop`, through a dedicated `release: X.Y.Z` PR:
   - bump the three version sites;
   - rename `[Unreleased]` to `## [X.Y.Z] (YYYY-MM-DD)` (the sha comes at step 5);
   - add a fresh `[Unreleased]` above it;
   - read `git diff origin/main...origin/develop -- readme.txt languages/readme-pt_BR.txt`: a change on one side only is a translation left behind (see "Translations").
2. Open the `develop → main` PR. Keep it draft until the maintainer confirms the production release.
3. After the squash lands on `main`, tag that commit (`vX.Y.Z`) and push the tag. `.github/workflows/release.yml` then:
   - checks the tag against the header version;
   - builds `total-mail-queue-X.Y.Z.zip`, staged through `.distignore`;
   - publishes a GitHub Release whose notes are that CHANGELOG section, marked as the latest.

   The build is `bin/build-release-zip.sh`, which the Lint workflow also runs against the newest tag on every PR, so a broken `.distignore` or CHANGELOG section fails there rather than at the release.

   **Pushing the tag publishes a production release, so an agent hands that command to the maintainer and does not run it.** Verify `HEAD` equals `origin/main` before tagging.
4. Reset `develop` to `main` (`git reset --hard origin/main`, then a force-with-lease push). Don't rebase: the squash already contains every develop commit.
5. Through a normal PR to `develop`, append `` — `<short-sha>` `` to the new heading, with the width `git rev-parse --short` gives for the squash commit. It must come after step 4, which would discard it, so the tagged tree never carries its own sha and the newest heading on `main` lacks one until the next release.

**Every released version has a `vX.Y.Z` tag** on the commit whose `Version:` header declares it, and the CHANGELOG heading names that commit. The history before 2.10.0 was tagged after the fact, each tag on the commit the CHANGELOG names for that version: the one that carries what the section lists and nothing from the next. Their releases are published by hand, from Actions → Release → Run workflow with the tag: the run packages the tag's files with the current `.distignore` and notes from the current CHANGELOG, and never marks the release as the latest, because the plugin's updater reads `releases/latest`. The same run rebuilds a release whose CHANGELOG section was corrected later; the published body otherwise keeps the text it was released with. The entries below 1.5.0 belong to the original plugin, [Mail Queue](https://wordpress.org/plugins/mail-queue/) by WDM; the CHANGELOG says so above them, and they have no tags here.

The zip ships no Composer or npm dependency. The autoloader is inline in `total-mail-queue.php` and `lib/` is vendored. Anything new at the repository root that should not ship goes into `.distignore`.

## Deploy to testes

`.github/workflows/deploy-develop.yml` runs on every push to `develop` (and by hand through `workflow_dispatch`). It rsyncs the tree to the testes site, the same host Free Form Certificate deploys to, and then runs a smoke check there.
- **What lands is what a release would ship.** The rsync excludes `.distignore`, the list `release.yml` builds the zip from, and `--delete` keeps the directory an exact copy.
- **Secrets**, set in this repository: `TESTES_SSH_HOST`, `TESTES_SSH_USER`, `TESTES_SSH_KEY` (no passphrase) and the optional `TESTES_SSH_PORT` carry the same values as in `rpgmem/ffcertificate`. `TESTES_REMOTE_PATH` is this plugin's own folder: `…/wp-content/plugins/total-mail-queue`, absolute, no trailing slash.
- **The path is checked before anything is sent.** `--delete` empties whatever directory it is pointed at, so the workflow refuses a `TESTES_REMOTE_PATH` that is not absolute or does not end in `/wp-content/plugins/total-mail-queue`. The first deploy ran with a placeholder value and created a stray copy in the home directory. The check cannot tell the testes site from another site on the same account (the host also serves production), so the secret must name the testes site.
- **The smoke** (`.github/scripts/testes-smoke.php`, copied to a scratch directory and removed afterwards) boots the real WordPress and checks that the plugin is active, that `Plugin::VERSION` on the host equals the deployed commit, and that the three tables exist. A boot that stops early, such as `wp_die()` on an unreachable database, fails rather than passing on exit code 0. A timeout only warns, because the rsync already succeeded.
- **Schema changes reach testes without an activation**: `Migrator::maybeMigrate()` runs on `plugins_loaded` whenever the stored version differs.
- **It is an alarm, not a gate**: it runs after the merge. Anything that must block a merge belongs in CI.

## Quality gates

These run in CI on every PR and on pushes to `main` and `develop`:
- PHPUnit `unit` and `integration` suites on PHP 8.3 and 8.4, the floor (8.3) and the newest. `composer.json` pins `config.platform.php` to the floor, so Composer resolves dev dependencies as if it ran there and none can quietly require a newer PHP.
- The functional suite against a real WordPress and MySQL (`phpunit.functional.xml.dist`), on the latest WordPress and on **the declared floor**: the `floor` leg reads `Requires at least` from the plugin header, resolves it to that branch's newest patch release and swaps the WordPress test library to the same branch, so moving the floor moves the test. A floor nothing runs is a claim, not a requirement.
- PHPStan, and PHPCS with WPCS and PHPCompatibilityWP.
- ESLint over `assets/js` and Stylelint over `assets/css` (`npm run lint`).
- **JS tests (Vitest)**: `tests/js/*.test.js` under jsdom, failing below the line floor in `vitest.config.mjs` (same ratchet rule as the PHP floor). Scripts load through `runScript()` in `tests/js/helpers.js`, which runs them under their real file name so V8 attributes coverage, and `installJquery()` binds real jQuery and resolves its ready event, which jsdom has already fired. jsdom has no layout: assert on `css('display')`, never `:visible`.
- `composer validate --strict` and `composer audit --locked`.
- **Coverage (PHP 8.3)**: the unit, integration and functional suites each write a `.cov` file, and `bin/coverage-merge.php` fails when the merged line coverage falls below `COVERAGE_FLOOR_LINES` in `tests.yml`. The rule is the same in the four plugins (Free Form Certificate, Total Mail Queue, Email Signature Generator, Institutional Email), for this floor and the JS one: **a floor is the highest of 90%, the measured figure minus 5 points, and its current value.** So it is never below 90%, never more than 5 points behind what is measured, and never lowered — the one exception is an honest re-measure after deleting covered product code, recorded beside the floor. Raise it in the PR that delivers the gain, and only once the measured figure is above it. The figure counts what each test's `@covers` names, so a test that runs code it does not declare adds nothing to it. So:
  - **declare the class, not the method** (`@covers \TotalMailQueue\Cron\BatchProcessor`, never `…::run`): a method-level annotation drops the private helpers that method calls, which hid a fifth of the batch processor;
  - **declare every class the test exercises on purpose**, such as the cron lock in a cron-flow test, but not the incidental ones every request touches (options, schema);
  - **a test whose subject is a partial goes without the annotation**, saying why in its docblock: the `src/admin/docs/` topics and `default-html.php` are included files, which no annotation can name.

Three guards in the unit suite hold what nothing else reads:
- `PluginIdentityTest`: the version (header, `Plugin::VERSION`, `Stable tag`, a dated CHANGELOG section) and the PHP and WordPress floors (header, `readme.txt`, `composer.json`, the PHPCS `testVersion` and `minimum_wp_version`) agree.
- `ReadmeUpgradeNoticeTest`: the Upgrade Notice holds one entry, for the `Stable tag` version, in at most 300 characters, pointing at `CHANGELOG.md`, and the updater's own parser reads the same text. Rewrite it in every release PR.
- `SuppressionTest`: every `phpcs:ignore` / `phpcs:disable` names its sniffs and gives a reason of at least 15 characters after `--`, and every `@phpstan-ignore` names an identifier and a reason; the `-line` forms are refused. A reason says why this line is safe ("the plugin's own table, name from Schema"), never "same".

**A test that reads the clock must not assume a fixed offset from code that snaps to a boundary.** The scheduler and `SmtpRepository` defer a capped account to the next local midnight, so "now + the cadence" fails in the last minutes of the day (#146), and a midnight read once before the call fails when the clock crosses it during the test. `FunctionalTestCase::nextLocalMidnight()` is read before and after the call, and either answer is accepted. The audit of every clock-reading test is in #151; re-run it when a new boundary appears (a month, a quota window).

Locally:

```bash
composer install && npm ci
vendor/bin/phpunit --testsuite unit
vendor/bin/phpunit --testsuite integration
vendor/bin/phpstan analyse --no-progress
vendor/bin/phpcs
npm run lint
npm run test:coverage
```

## Admin stylesheet

`assets/css/admin.css` loads on the plugin's own screens only.
- **Colours.** Every colour is a `--tmq-*` token from its `:root` block. The `:root.tmq-dark-mode` block redefines colour tokens only, because dark is light overridden, not a second set.
- **The dark class.** `Admin\Assets::darkModeScript()` puts the class on `<html>` from the Dark Mode setting. There is no `prefers-color-scheme` in the CSS: the operating system is consulted only when the setting is "auto".
- **`ColorTokensTest`** fails on a colour literal outside the token blocks, on a `var(--tmq-*)` nobody declares, and on a text/background pair below 4.5:1 in either theme. It checks pairs two ways: a curated `PAIRS` list, for pairs that inheritance creates, and a scan that measures every rule declaring `color` and a background together.
- **Icons.** They come from `Admin\Icons`: 24×24 stroke drawings painted as a `currentColor` mask. A new icon joins `Icons::PATHS`; no emoji, dashicon or one-off inline SVG stands in for one.
- **Headings and cards.** Every heading on the plugin's screens is printed by `Icons::heading()`, so it carries an icon, and a form's sections are `tmq-box tmq-settings-card` cards like the Settings tab's. A list screen (Log, Retention, SMTP accounts, Sources) has no card of its own, because the tab panel already is one, but its heading still carries the tab's icon. `AdminHeadingsTest` fails on a literal `<h2>`/`<h3>` under `src/admin/`, except the page title, the Export / Import group label and core admin notices.
- **No inline `style=""`** on the plugin's screens. The one exception is the WordPress-update notice, which shows on screens where the sheet is not loaded.
- **Tabs and submenus.** Each tab is its own submenu slug, listed once in `PluginPage::tabs()`. The submenus stay registered, so URLs and screen ids are stable, and `Menu::menuStylesheet()` hides them from the sidebar.

## Documentation

The Documentation tab is `Admin\Documentation`: one partial per topic under `src/admin/docs/`, listed in `Documentation::topics()`.
- **Documentation travels with the change, in the same PR.** A PR that changes what an administrator sees or configures (a tab, a setting, a default, a notice, a header, a filter) updates the topic that describes it, with its strings in the pt_BR catalogues.
- **Topics are grouped by tab, in the menu's order.** `Documentation::groups()` names the tabs each group documents and takes its title from the tab's own label, so the documentation reads the way the menu does, as Free Form Certificate's does. The quick navigation is a tree of those groups with a search box (`assets/js/tmq-docs.js`). On a wide screen it is a sticky column beside the topics, never a bar over them, so it cannot hide the text being read; on a narrow one it sits above the topics and scrolls away.
- **`DocumentationTest`** fails when a tab belongs to no group or to two, when the groups leave the menu's order, when a tab group's title is not the tab's label, when a listed topic file is missing, or when a file in `src/admin/docs/` is never shown. It proves a topic exists, never that it is right.
- **Name the labels the screen shows.** A topic quotes a button or field by the label the plugin prints, so the translated topic and the translated screen agree.
- **The old FAQ slug** (`PluginPage::TAB_FAQ`) stays registered and opens the documentation, so old links keep working. Notices link to the Troubleshooting topic.
- **Every option the plugin writes is deleted on uninstall.** `UninstallFootprintTest` reads each `update_option()` under `src/` and fails when the uninstaller does not delete it.

## Translations

`languages/` holds the `.pot`, and for pt_BR the `.po`, `.mo` and `.l10n.php`.
- **Edit the `.po` (by hand or in Loco), then run `php bin/compile-translations.php`** (needs GNU gettext). It writes the `.mo` with `msgfmt --check` and the `.l10n.php` in the shape Loco writes it, so a catalogue saved from Loco and one compiled here are the same file. Never edit a compiled catalogue by hand: a fix that reaches one and not the other depends on which file WordPress loaded.
- **`bin/po-catalogue.php` is the one `.po` reader.** The compiler and the tests both use it, so they cannot disagree about what an entry is. `PoCatalogueTest` exercises the shapes on a synthetic file.
- **`TranslationCatalogueAgreementTest`** fails when the three files disagree, in either direction, plurals form by form, or when the `.l10n.php` is not byte for byte what the compiler writes. Its self-check recounts the `.po`'s entries by a different route, so a reader that read nothing fails instead of passing.
- **`languages/readme-pt_BR.txt` is the readme "View details" shows a pt_BR administrator.** A PR that changes `readme.txt` updates it in the same PR. `PluginDetailsAssetsTest` holds the two to the same structure (tab sections, the count of other sections, the screenshot captions) and cannot see the content: a changed sentence in English passes CI with the translation still saying the old thing (#149).

## Code

- Namespace `TotalMailQueue`; no global functions.
- Escape on output; use `$wpdb->prepare()` for every query; check a nonce and a capability on every request entry point.

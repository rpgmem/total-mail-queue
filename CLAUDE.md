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
- An agent's credential can create and update refs but **cannot delete a ref** (HTTP 403). Deleting merged branches is the maintainer's job.
- Dependabot targets `develop`. A security update opens against `main`: retarget it to `develop`.

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
- Feature PRs never bump the version. Their CHANGELOG entry goes under `## [Unreleased]`, grouped by `Added` / `Changed` / `Fixed` / `Removed` / `Security`. Append the bullet at the end of its heading and cite the issue or PR.

## Releases

1. On `develop`, through a dedicated `release: X.Y.Z` PR:
   - bump the three version sites;
   - rename `[Unreleased]` to `## [X.Y.Z] - YYYY-MM-DD`;
   - add a fresh `[Unreleased]` above it.
2. Open the `develop → main` PR. Keep it draft until the maintainer confirms the production release.
3. After the squash lands on `main`, tag that commit (`vX.Y.Z`) and push the tag. `.github/workflows/release.yml` then:
   - checks the tag against the header version;
   - builds `total-mail-queue-X.Y.Z.zip`, staged through `.distignore`;
   - publishes a GitHub Release whose notes are that CHANGELOG section.

   **Pushing the tag publishes a production release, so an agent hands that command to the maintainer and does not run it.** Verify `HEAD` equals `origin/main` before tagging.
4. Reset `develop` to `main` (`git reset --hard origin/main`, then a force-with-lease push). Don't rebase: the squash already contains every develop commit.

The zip ships no Composer or npm dependency. The autoloader is inline in `total-mail-queue.php` and `lib/` is vendored. Anything new at the repository root that should not ship goes into `.distignore`.

## Quality gates

These run in CI on every PR and on pushes to `main` and `develop`:
- PHPUnit `unit` and `integration` suites on PHP 8.1–8.4.
- The functional suite against a real WordPress and MySQL (`phpunit.functional.xml.dist`).
- PHPStan, and PHPCS with WPCS and PHPCompatibilityWP.
- ESLint over `assets/js` and Stylelint over `assets/css` (`npm run lint`).
- `composer validate --strict` and `composer audit --locked`.

Locally:

```bash
composer install && npm ci
vendor/bin/phpunit --testsuite unit
vendor/bin/phpunit --testsuite integration
vendor/bin/phpstan analyse --no-progress
vendor/bin/phpcs
npm run lint
```

## Code

- Namespace `TotalMailQueue`; no global functions.
- Escape on output; use `$wpdb->prepare()` for every query; check a nonce and a capability on every request entry point.

# Statamic SOP

> A global SOP (Standard Operating Procedure) consent gate for the Statamic control panel: users must read and confirm every active SOP before they can work in the CP — with immutable versioning, forced re-consent on changes, and a revision-safe audit trail.

[![Latest Version](https://img.shields.io/packagist/v/takepart-media/statamic-sop.svg?style=flat-square)](https://packagist.org/packages/takepart-media/statamic-sop)
[![Total Downloads](https://img.shields.io/packagist/dt/takepart-media/statamic-sop.svg?style=flat-square)](https://packagist.org/packages/takepart-media/statamic-sop)
[![License](https://img.shields.io/packagist/l/takepart-media/statamic-sop.svg?style=flat-square)](LICENSE)

## Features

- **Sequential consent gate** — after login, pending SOPs are presented one at a time ("SOP 1 of 3"); the CP stays locked until all are confirmed. Direct URLs, reloads, the back button, parallel tabs and replayed requests cannot skip ahead.
- **Immutable versioning & re-consent** — every content change creates a new version; existing consents are invalidated and users must confirm again. Consents always reference the exact version text.
- **Revision-safe audit trail** — see who consented to which version, and when, even after later edits or deletion.
- **Own SQLite database** — SOPs and consents live in a dedicated `sop` connection, fully separate from your content and set up automatically.
- **Bypass rules** — super admins always bypass; additional roles and groups are configurable per project.
- **Fail-safe by design** — bypass is resolved without touching the SOP database, a broken database blocks the CP instead of silently unlocking it, and idempotent transactional writes make double-clicks and races harmless.
- **Native CP integration** — feels like part of Statamic: nav item, `manage sops` permission in the Roles UI, light & dark mode, English and German translations.
- **Tested** — 100+ tests covering the gate, sequencing, versioning, bypass, security and regressions.

## Requirements

- Statamic 6
- PHP 8.2+

## Installation

```bash
composer require takepart-media/statamic-sop
```

Then either run the install command, which creates the SOP database, runs the addon's migrations, and publishes `config/sop.php`:

```bash
php please sop:install
```

or do the same steps by hand:

```bash
php artisan migrate --database=sop --path=vendor/takepart-media/statamic-sop/database/migrations --realpath
php artisan vendor:publish --tag=sop-config
```

SOPs and consents live in their own SQLite database at `storage/app/sop/sop.sqlite` by default (see Configuration below) — a dedicated connection called `sop`, separate from the project's primary database.

## Configuration

`config/sop.php` (published via the command above, or the tag `sop-config`):

```php
return [

    // Global kill switch. When false, the CP is never blocked and the SOP
    // management screens stay available.
    'enabled' => env('SOP_ENABLED', true),

    // Point this at your primary connection instead if you'd rather keep
    // everything in one database.
    'database' => [
        'driver' => 'sqlite',
        'database' => env('SOP_DATABASE', storage_path('app/sop/sop.sqlite')),
        'prefix' => '',
        'foreign_key_constraints' => true,
    ],

    // Role and group handles whose users skip the gate entirely. Super
    // admins always bypass, regardless of this list.
    'bypass' => [
        'roles' => [],   // e.g. ['sop_exempt']
        'groups' => [],  // e.g. ['contractors']
    ],

    // Whether a consent additionally records the client IP / user agent.
    // Both are personal data under the GDPR and off by default — user,
    // version and timestamp already prove the acknowledgment. Enable only
    // with a documented legal basis.
    'audit' => [
        'ip' => false,
        'user_agent' => false,
    ],

];
```

Bypass handles are role/group *handles*, matching whatever is defined in `resources/users/roles.yaml` / `resources/users/groups.yaml`, for example:

```yaml
# resources/users/roles.yaml
sop_exempt:
  title: 'SOP Exempt'
  permissions:
    - 'access cp'
```

```php
'bypass' => [
    'roles' => ['sop_exempt'],
],
```

A user in a bypassed group inherits the bypass through that group's roles as well — `$user->isInGroup()` and `$user->hasRole()` (via group membership) are both checked.

## How versioning and re-consent work

Every time an SOP is saved, its title and content are hashed together (`sha256(title . "\0" . content)`). If the hash differs from the current version's hash, a new, immutable `sop_versions` row is written and the SOP is repointed at it — the old version is never edited or deleted. Consents reference a specific `sop_version_id`, not the SOP itself, so:

- Changing **either** the title or the content bumps the version and invalidates existing consents — a title change is treated the same as a content change, since it's the simplest rule to defend when someone asks exactly what a user agreed to.
- Toggling `active` or `sort_order` alone does **not** create a new version — nothing was agreed to changes.
- The audit trail (an SOP's "show" screen in the CP) always displays the exact text that was current for each version, so past consents remain verifiable even after later edits.

## Gate behavior

The gate is prepended to Statamic's `statamic.cp.authenticated` middleware group, so it runs before Statamic's own authorization and before anything other addons add — a user who isn't allowed into the CP at all still gets Statamic's own 403, not the SOP screen. For an authenticated, `access cp` user with pending SOPs:

- **Normal GET request** — the intended URL is remembered and the user is redirected to `sop/consent`; after the last confirmation they land back where they were headed.
- **JSON/AJAX request** — `423 Locked` with `{"message": "...", "redirect": "..."}`.
- **Inertia request** (`X-Inertia` header) — `409` with an `X-Inertia-Location` header, so the Inertia client performs a full visit to the consent screen instead of swallowing the response.
- **Non-GET request** (e.g. a form POST) — redirected without an intended URL; replaying the original action after consenting would be a surprise, not a convenience.

If the SOP database itself can't be reached, the middleware fails safe: bypassed users (super admins, configured roles/groups) are checked *before* the database is ever touched, so a broken database can never lock them out. Everyone else gets a standalone `503` page — with no dependency on the SOP database, and a logout link — instead of a stack trace.

## Permissions

The addon registers one permission, `manage sops`, under the "SOPs" group in the Roles UI. It gates the SOP management screens (`sop`, `sop/create`, `sop/{sop}`, edit, update, delete) via `can:manage sops` on the routes. Super admins have it automatically. Note that having `manage sops` does **not** exempt a user from the consent gate itself — a manager with their own pending SOPs is still redirected to the consent screen first; use the `bypass` config for that instead.

## Middleware ordering caveat

The gate uses `Router::prependMiddlewareToGroup()` from `bootAddon()`, which runs after every provider has booted — this reliably puts it ahead of Statamic's own `Authorize` middleware and ahead of whatever other addons append during their own boot. If **another** addon also prepends a gating middleware to the same group, the two prepends race based on provider boot order, which Statamic does not guarantee across addons. There is no in-process way to resolve that; if you run two prepending gate-style addons together, verify their effective order manually.

## Uninstalling

Removing the package (`composer remove takepart-media/statamic-sop`) is enough to disable the gate and remove the CP screens. The SQLite database at `storage/app/sop/sop.sqlite` (or wherever `SOP_DATABASE` points) is **not** deleted automatically — it holds the full consent audit trail. Delete that file by hand once you no longer need the history, or keep it for records.

## Support

Found a bug or have a feature request? Please open an issue on [GitHub](https://github.com/takepart-media/SOPmic/issues). See [CHANGELOG.md](CHANGELOG.md) for release notes.

## License

Open source under the [MIT license](LICENSE).

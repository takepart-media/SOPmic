# Changelog

All notable changes to this addon are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and versions follow
[Semantic Versioning](https://semver.org/).

## [1.2.1] - 2026-10-26

### Changed

- The exclusion fields on the SOP create/edit form now use Statamic's native searchable combobox (`<ui-combobox>`, multiple selection with search, badges and a clear button) instead of native `<select multiple>` lists.
- An explicitly emptied exclusion field is now posted as an empty value (`excluded_users=""`) and treated as "cleared", exactly like an absent field.

### Added

- Published control panel script asset (`resources/js/cp.js` → `public/vendor/statamic-sop/js/cp.js`, publish tag `sop`) that registers the `<sop-multi-select>` component. `php please sop:install` now publishes it as well.

### Upgrade notes

- After updating, run `php please sop:install` or `php artisan vendor:publish --tag=sop --force` so the control panel script is published. Without it, the exclusion fields on the SOP form do not render.

## [1.2.0] - 2026-10-26

### Added

- Per-SOP exclusions: individual users, roles and groups can be exempted from a single SOP in the SOP create/edit form. Excluded users are neither gated by that SOP nor shown it in the consent flow. Changing exclusions does not create a new version or trigger re-consent.

### Upgrade notes

- Run `php artisan migrate` (against the `sop` connection) or `php please sop:install` after updating to add the new exclusion columns.

## [1.1.0] - 2026-09-08

### Added

- Collapsible markdown cheat sheet below the content field on the SOP create/edit form (native `<details>`, no JavaScript).

## [1.0.0] - 2026-09-08

### Added

- Global consent gate for the Statamic control panel: users must read and confirm every active SOP, sequentially, before the CP unlocks. Direct URLs, reloads, back button, parallel tabs and replayed requests cannot skip the sequence.
- SOP management in the CP (list, create, edit, activate/deactivate, sort, soft delete) behind a registered `manage sops` permission, with a "SOPs" nav item.
- Immutable versioning: any title/content change creates a new version and invalidates existing consents; the audit view shows the exact text of every version and who consented when.
- Dedicated SQLite database (`sop` connection), created automatically; `php please sop:install` for one-shot setup.
- Bypass for super admins (always) plus configurable roles and groups, resolved without touching the SOP database (fail-safe on database errors: bypassed users are never locked out, everyone else gets a standalone 503 page).
- Idempotent, transactional consent writes (unique per user + version).
- Opt-in IP/user-agent capture (`sop.audit` config, off by default for GDPR data minimization).
- German and English translations, light and dark mode support.
- Test suite: 104 tests covering installation, CRUD, versioning, sequencing, bypass, gate coverage, security and regressions.

[1.2.1]: https://github.com/takepart-media/SOPmic/compare/v1.2.0...v1.2.1
[1.2.0]: https://github.com/takepart-media/SOPmic/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/takepart-media/SOPmic/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/takepart-media/SOPmic/releases/tag/v1.0.0

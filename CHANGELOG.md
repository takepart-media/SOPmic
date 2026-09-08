# Changelog

All notable changes to this addon are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and versions follow
[Semantic Versioning](https://semver.org/).

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

[1.1.0]: https://github.com/takepart-media/SOPmic/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/takepart-media/SOPmic/releases/tag/v1.0.0

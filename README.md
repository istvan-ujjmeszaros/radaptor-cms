# Radaptor CMS

`radaptor/core/cms` is the CMS and admin layer on top of the framework.
It provides resource-tree management, layouts/widgets/forms, ACL tooling,
and CMS-facing modules used by Radaptor consumer applications.

## Installation

End users should install through a consumer app, not by cloning this package directly:

- [`radaptor-app`](https://github.com/istvan-ujjmeszaros/radaptor-app)
- [`radaptor-portal`](https://github.com/istvan-ujjmeszaros/radaptor-portal)

The package is resolved from registry metadata during app install/update.

## Runtime Requirements

The CMS package requires PHP 8.5 or newer. The package metadata declares this as
`composer.require.php = ^8.5`, and CMS code may use PHP 8.5 syntax such as the
pipe operator (`|>`).

## Dependencies

From `.registry-package.json`:

- `radaptor/core/framework` (`^0.1.0`)
- PHP (`^8.5`)

## Development and Release

Maintain this package in `/apps/_RADAPTOR/packages-dev/core/cms`, not inside a consumer app's
`packages/registry/...` runtime copy. Validate through a consumer app `php` container; do not run
host PHP, Composer, PHPUnit, PHPStan, or PHP-CS-Fixer.

Release key:

- `core:cms`

Normal flow: package PR, `@codex review`, clean repo checks, squash merge, fast-forward local
`main`, `package:release core:cms`, commit the `.registry-package.json` version bump, then publish
the generated artifact through `radaptor_plugin_registry`.

Site snapshot export/import lives in this package. CLI entrypoints are exposed by the framework,
while the CMS service owns snapshot validation, destructive import replacement, upload-manifest
checks, and post-import maintenance.

## License

This package is distributed under the proprietary evaluation license in
[LICENSE](./LICENSE).
Evaluation-only: no production/commercial/distribution/derivative use without
a separate license agreement.

## Contact

istvan@radaptor.com

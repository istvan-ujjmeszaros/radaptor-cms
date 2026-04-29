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

## License

This package is distributed under the proprietary evaluation license in
[LICENSE](./LICENSE).
Evaluation-only: no production/commercial/distribution/derivative use without
a separate license agreement.

## Contact

istvan@radaptor.com

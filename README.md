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

## Layout Terminology

Radaptor separates CMS composition points from template render regions:

| Term | Meaning |
|---|---|
| slot | One entry returned by `iLayoutType::getSlots()`. A per-page CMS composition point that contains widget connections. |
| widget connection | One configured widget instance placed on one page in one slot. |
| layout component | A hardcoded structural component built by the layout, such as `header_audio` or `main_menu`. It is not configured through widget connections. |
| template region | A named output point in the layout template. It may contain slot content, layout components, or both. |

The layout tree API uses `contents:` for template regions. Do not use that name
for CMS slots; CMS slots are only the names returned by `getSlots()`.

```
layout type
  |
  +-- template regions (contents:)
        |
        +-- slot content
        |     |
        |     +-- widget connection
        |           |
        |           +-- widget instance render tree
        |
        +-- layout component render tree
```

## Partial Navigation

Layouts opt into htmx-style partial navigation by implementing
`iPartialNavigableLayout`. Opt-in layouts emit stable containers for slots,
widget connections, and layout components; layouts that do not implement the
interface keep the previous DOM shape and do not expose connection ids.

Stable containers are emitted consistently for the whole page composition:
slots render as `id="slot-{slot_name}"`, widget connections as
`id="widget-{connection_id}"`, and fragment-renderable layout components as
`id="component-{name}"`. Widget-internal partial templates are not wrapped
automatically; if a widget needs finer-grained htmx behavior inside its own
markup, the widget author must add those ids explicitly.

Fragment targets are type-prefixed:

- `slot:{slot_name}` renders all widget connections in that slot.
- `widget:{connection_id}` renders one widget connection and validates that it
  belongs to the resolved page.
- `component:{name}` renders one component from the layout's
  `getFragmentLayoutComponents()` map.

The canonical page URL provides the page context, ACL, locale, edit mode, and
preview state. A request with `context=fragment` and no explicit `targets[]`
uses the layout's `getPageFragmentTargets()` list. Responses are ordinary htmx
OOB swaps, so no custom SPA shell is required.

`CmsRenderVersion` touches `resource_tree.last_modified` when page widget
composition or widget attributes change. In v1 fragment responses bypass cache;
this touch infrastructure is preparation for future fragment/full-page cache
keys. The legacy persistent full-page cache still uses its short TTL.

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

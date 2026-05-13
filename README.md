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

- `radaptor/core/framework` (`^0.1.29`)
- PHP (`^8.5`)

## Resource Spec Compatibility

BREAKING (CMS resource-spec): `slots` is now partial by default. Only mentioned
slots are touched, omitted slots are preserved. To restore the previous
wipe-on-omit behavior, set `replace_slots: true`. Use
`radaptor resource-spec:compat-scan <path>` to find specs that may need this
flag.

Destructive CMS CLI commands use dry-run by default and require `--apply` to
write changes. `--apply --dry-run` is a hard error. CMS mutation audit rows are
stored in `cms_mutation_audit` with a correlation id; prune them with
`radaptor cms:mutation-audit-prune --days 180 --apply`.

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
slots render as `id="slot-{slot_name}"`, widget fragment targets as
`id="fragment-widget-{connection_id}"`, and fragment-renderable layout
components as `id="component-{name}"`. Edit-mode chrome uses its own
`edit-*` id namespace. Widget-internal partial templates are not wrapped
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

Fragment fallback responses emit `X-Radaptor-Fragment-Fallback: {reason}`. For
htmx requests this is sent next to `HX-Redirect`; for non-htmx fragment-context
requests it is sent with the error response. Treat this header as a diagnostic
signal that partial navigation did not return the requested fragment.

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

## Locale-Aware Content Routing

CMS resources may carry an explicit BCP 47 content locale. Descendant resources inherit the nearest
ancestor locale, and request rendering temporarily switches the request locale to the resolved
resource locale. The previous request locale is restored after rendering.

Locale home resources are computed per site context and locale. `LocaleHomeResourceService` refreshes
only the affected site context for normal resource mutations; full refreshes are explicit and used
only for full tree rebuilds. Resource-locale inheritance uses request-scope cache stored on
`RequestContext`, so repeated lookups during one render avoid duplicate ancestor queries without
leaking across Swoole requests.

Locale-switch return URLs are same-origin only and resource lookup paths reject traversal segments
such as `.` and `..` before resolving through the resource tree.

Schema feature-detection caches are positive-only. If a migration adds `resource_tree.locale`,
`locale_home_resources`, `richtext.locale`, or `cms_mutation_audit` while a long-running PHP process
is alive, a previous negative check is re-probed on later calls.

## Site Snapshots

Site snapshots intentionally include `migrations` and `seeds` metadata. Restores without those rows
can re-run historical package/app migrations or bootstrap seeds against an already-restored schema
and content set.

## License

This package is distributed under the proprietary evaluation license in
[LICENSE](./LICENSE).
Evaluation-only: no production/commercial/distribution/derivative use without
a separate license agreement.

## Contact

istvan@radaptor.com

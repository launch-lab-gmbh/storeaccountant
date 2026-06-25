# Vendor Scoping

StoreAccountant ships Composer dependencies inside the plugin release package.
WordPress does not isolate plugin dependencies at runtime: all active plugins run
in the same PHP process and classes are loaded into the same global namespace.
If another plugin loads a different version of a package such as Symfony,
Flysystem, or League Container first, StoreAccountant could otherwise end up
using an incompatible dependency version.

To reduce that risk, the release workflow prefixes bundled vendor dependencies.
This is release-only build work. Local development, linting, and the normal unit
test suite continue to run against the unscoped Composer vendor directory.

## Release Process

Vendor scoping is configured in `scoper.inc.php` and executed from
`.github/workflows/release.yml` after the production Composer dependencies have
been installed and copied into the release build directory.

The scoped release build:

- prefixes bundled Composer packages with the configured vendor namespace;
- keeps StoreAccountant classes in the `StoreAccountant` namespace;
- keeps WordPress, WooCommerce, Action Scheduler, and supported invoice plugin
  APIs unprefixed because those symbols are provided by the host application or
  other plugins;
- regenerates Composer autoload metadata after scoping;
- loads `vendor/scoper-autoload.php` in release builds, with
  `vendor/autoload.php` as the development fallback.

The scoper configuration deliberately treats WordPress and WooCommerce APIs as
external runtime APIs. New integrations that call global plugin functions or
classes may need matching excludes so PHP-Scoper does not create fallback
aliases for symbols that should be supplied by those plugins.

## Test Layers

Vendor scoping changes the release artifact. Passing tests against the source
tree and normal Composer vendor directory does not prove that the scoped release
package can boot.

The project should distinguish these test layers:

- Unit tests: run against source code and the normal Composer vendor directory.
  They should stay focused on PHP behavior and are not expected to validate
  vendor scoping.
- Normal integration tests: run against the development plugin checkout with
  normal vendor dependencies. They should cover WordPress and WooCommerce
  integration, service wiring, hooks, repositories, queues, renderers, and
  storage behavior.
- Release artifact smoke tests: run against the built release package with the
  scoped production vendor directory. They should at minimum install, activate,
  boot, and deactivate the plugin on every supported PHP version.
- Release artifact end-to-end tests: run selected user-facing workflows against
  the built release package, for example activating WooCommerce, creating test
  data, starting an export, processing queued work, and verifying the generated
  storage file.

Unit tests are not the right tool for validating vendor scoping because scoping
is a build transformation. The important checks are whether the transformed
release artifact can be installed and whether representative WordPress and
WooCommerce workflows still execute with the scoped autoloader.

# Changelog

All notable changes to `filament-url-image-uploader` will be documented in this file.

## 1.2.1 - 2026-09-19

### Fixed
- **1.2.0's `^8.2` floor was still wrong and briefly broke installs on PHP 8.2.** The locked
  `openspout/openspout` (a transitive dependency via `filament/actions`) requires
  `~8.3.0 || ~8.4.0 || ~8.5.0` — PHP 8.2 was never actually installable from this lock file. CI
  only caught this after 1.2.0 was tagged, because the local verification for that release used
  `composer install --dry-run` with a platform override, which doesn't enforce transitive PHP
  version constraints the way a real install does. Bumped the floor to `^8.3` (verified against a
  real `composer install`, and against CI actually going green on PHP 8.3 and 8.4) and dropped 8.2
  from the CI matrix.

## 1.2.0 - 2026-09-19

### Changed
- **Minimum PHP bumped to `^8.2`.** The `^8.1` constraint hadn't been accurate for a while: the
  locked dev dependencies (Symfony, in particular) already required PHP 8.2+, so a fresh
  `composer install` on PHP 8.1 was failing regardless of what composer.json claimed. This makes
  the declared constraint match what actually installs. CI now runs against PHP 8.2, 8.3, and 8.4.
- `.github/dependabot.yml` now configures a 7-day update cooldown (14 days for semver-major) —
  the last remaining gap in the Filament plugin directory's automated package-health scan.

### Added
- A dedicated Testbench/Livewire test suite for using the field inside a `Repeater` as an image
  gallery (`tests/Feature/RepeaterGalleryTest.php`): fresh multi-image upload, a no-op
  edit-and-save across separate page loads, deleting a middle row, reordering, adding a row to an
  existing gallery, deleting-then-adding (to catch a resurrected image), and an added-but-empty
  row saving as `null` instead of crashing. All pass against the current implementation.
- A "Using it in a Repeater" section in the README, including a documented gotcha: an `itemLabel()`
  closure that reads the row's image value directly receives the field's nested `{path, url}` live
  state, not the flat string the model attribute ends up with, and will throw if you type-hint a
  `string` return.

## 1.1.1 - 2026-09-19

Maintenance release — no code changes to the field itself. Addresses package-health gaps flagged
by the Filament plugin directory's automated Composer package scan.

### Added
- `SECURITY.md` — a real security policy file (previously only documented in the README).
- `.github/dependabot.yml` — weekly dependency update checks for Composer and GitHub Actions.
- `.github/workflows/tests.yml` — CI running the PHPUnit suite and PHPStan analysis on every push
  and pull request, against PHP 8.1–8.3, with all Actions pinned to a commit SHA.
- A README banner image (`art/banner.svg` / `art/banner.png`).

### Fixed
- **`composer.lock` was being shipped in the release dist archive.** Added `.gitattributes` with
  `export-ignore` rules for `composer.lock`, `tests/`, `.github/`, `.vscode/`, `art/`, and other
  dev-only files, so `composer require` and Composer's package archive no longer pull in files a
  consuming application doesn't need.

## 1.1.0 - 2026-07-17

1.0.3 fixed several real bugs but its `getChildComponentContainer()` override left the field broken
in the most common real-world setup — any Filament Resource page (`CreateRecord`/`EditRecord`),
which conventionally uses a `data` form state path — and it didn't fully close out the URL-tab
validation issue either. This release replaces that approach and fixes what was still broken,
verified end-to-end with a permanent Testbench/Livewire test suite (not just unit tests against the
component in isolation).

### Fixed
- **Critical: field crashed on every save inside a real Filament Resource form.** 1.0.3's
  `getChildComponentContainer()` override built the inner `FileUpload`/`TextInput` container with no
  `parentComponent()` and no `statePath()`, so it silently discarded all path context above it
  (the field's own container path, any `Repeater`/`Section` nesting, and critically the form's own
  root `statePath`). Since `CreateRecord`/`EditRecord` pages bind their form to a `data` property by
  convention, the inner fields ended up bound to the bare field name (e.g. `image`) instead of
  `data.image`, and Livewire threw `No property found for validation: [image]` the moment the form
  tried to validate — i.e. on every save. Fixed by dropping the container override entirely and
  going back to Filament's normal, cached child-container resolution, with the inner `FileUpload`
  and `TextInput` renamed to `path`/`url` (instead of reusing the parent field's own name) so they no
  longer collide with the parent's own state path in the first place.
- **The "URL Upload" tab's `->url()` rule could still block saving a plain file upload.** Replaced
  with a closure rule that only validates URL format when the value is non-blank, and also accepts
  root-relative paths (`Storage::disk(...)->url()` can legitimately return one depending on disk
  config).
- **Live preview used the wrong array key format.** `FileUpload` keeps its in-progress state as a
  UUID-keyed array (e.g. `['9f1c...' => 'images/photo.jpg']`), not a `[0 => ...]`-indexed one. The
  blade preview (and the field's own dehydration) read the value via `$path[0]`, which silently
  resolved to `null` — most visibly when editing a record that already has an image, since that's
  exactly when `FileUpload`'s hydration hook re-keys the value onto a UUID. Fixed by reading the
  first element with `Arr::first()` regardless of key, in both places.

### Added
- `->disk(string $disk)` on `UrlImageUploader` — the field previously hardcoded the `public` disk even
  though the inner `FileUpload` supported any disk; both the upload tab and the URL-fetch action now
  honour it.
- `->maxUrlFetchSize(int $bytes)` (default 10 MB) — the "fetch from URL" action now enforces a byte
  cap on the server-side download, on top of the existing `finfo` content-type check, and rejects any
  scheme other than `http`/`https` before fetching (the URL field's own format validation alone would
  still accept `file://`, `data:`, etc.).
- Larastan/PHPStan (level 5), wired up via `composer analyse`.
- A permanent Testbench + Livewire test suite (`composer test`) covering: an existing image surviving
  a no-op edit-and-save (twice in a row, and across separate page loads); a fresh file upload
  persisting to disk and to the model attribute; the live preview resolving correctly both right
  after upload and for an existing image's UUID-keyed hydration state; and the URL tab staying blank
  without blocking a file-tab save.

### Changed
- Documented the field's scope: single image per field, no `multiple()` support (use one field per
  image, or a `Repeater`, if you need several).

## 1.0.3 - 2025-01-01

### Fixed
- **ServiceProvider**: Removed incorrect `hasViewComponent()` registration that tried to register a Filament form field (`UrlImageUploader`) as a Blade view component (`Illuminate\View\Component`), causing a fatal error when the package was loaded
- **ServiceProvider**: Removed redundant `packageBooted()` override that double-registered the views namespace (already handled by `->hasViews()`)
- **UrlImageUploader**: Fixed state-path nesting bug — the inner `FileUpload::make($fieldName)` was resolving to state-path `{field}.{field}` (e.g. `image.image`) instead of the correct root-level `{field}` (`image`), meaning data was never saved to the right database column
- **UrlImageUploader**: Added `dehydrated(false)` to the outer wrapper field so it no longer competes with the inner `FileUpload`'s own dehydration
- **UrlImageUploader**: Removed broken `afterStateUpdated` that prematurely stored uploaded files manually (the `FileUpload` component handles this automatically in `beforeStateDehydrated`), preventing double file storage
- **UrlImageUploader**: Removed broken `afterStateHydrated` that overrode `BaseFileUpload`'s correct UUID-keyed state format with a plain array, breaking the FileUpload display in edit mode
- **UrlImageUploader**: Added `dehydrated(false)` to the URL `TextInput` so the URL value is not persisted to the database
- **UrlImageUploader**: Fixed URL fetch action — added 10-second HTTP timeout, `finfo` content-type validation (rejects non-image responses), filename sanitization, and a proper error notification when the URL is empty or invalid
- **Blade view**: Rewrote the image preview logic to correctly handle all state formats: plain string path, full URL string, `[uuid => path]` array (FileUpload internal format), and plain `[path]` array

### Added
- PHPUnit test suite with `orchestra/testbench` integration
- `phpunit.xml` configuration
- Basic unit tests covering instantiation, directory configuration, and dehydration behaviour

## 1.0.2 - 2024-01-19

### Fixed
- Image preview not displaying when editing existing records
- State hydration handling for both string and array values
- FileUpload component state format in edit mode

## 1.0.1 - 2024-01-18

### Fixed
- File upload state persistence issue
- Data saving functionality for uploaded files
- Storage handling for file uploads

## 1.0.0 - 2024-01-04

### Added
- Initial release
- Support for URL-based image uploads
- File upload support with preview
- Custom directory configuration
- Filename preservation option
- Integration with Laravel Storage
- Support for Filament 3.x
- Image validation and error handling
- Public URL generation for uploaded images
- Response format with both relative path and full URL

### Changed
- None

### Fixed
- None

### Deprecated
- None

### Removed
- None

### Security
- Added image validation
- Secure file handling
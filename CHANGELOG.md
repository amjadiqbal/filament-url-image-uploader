# Changelog

All notable changes to `filament-url-image-uploader` will be documented in this file.

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
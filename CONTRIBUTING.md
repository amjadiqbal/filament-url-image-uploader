# Contributing

## CI is local

GitHub workflows don't run checks for this repo (Amjad, 2026-10-10: failed runs flood his email). The `Tests`
workflow is disabled and its file (`.github/workflows/tests.yml`) stays only as a reference for what the local run
covers. Don't re-enable or trigger it. Dependabot version updates are off too (no `.github/dependabot.yml`):
dependency bumps are manual PRs that run the same local CI.

Every PR runs the same checks on the developer's machine and pastes the summary block into its description:

```bash
scripts/ci-local.sh                                      # PHP 8.3 and 8.4, each against Filament 5 and 4
PHP_VERSIONS=8.3 FILAMENT_VERSIONS=5 scripts/ci-local.sh   # one PHP version, one Filament major
```

It finds `php8.3`/`php8.4` on PATH or Homebrew's `php@8.x` and ends with one `pass`/`FAIL` line per step.
Filament 5 runs from the committed lock file; Filament 4 runs in a throwaway clone of HEAD with
`filament/filament` narrowed to `^4.0` and a fresh `composer update`, so commit before running it.

`composer.json` pins `config.platform.php` to 8.3, the package's floor, so `composer update` on a newer PHP can't
lock dependencies that 8.3 can't install.
